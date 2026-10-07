<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Package;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Support\Tenant;
use App\Services\ExamLanguageService;
use App\Services\ExamDocumentLifecycleService;
use App\Services\ExamPdfCacheService;
use App\Services\ExamTranslationService;
use App\Services\QuestionAnswerEvaluator;
use App\Services\StudentActivityTracker;
use App\Services\StudentPostAuthService;

class ExamPrintController extends Controller
{
    public function __construct(private StudentPostAuthService $postAuthService)
    {
    }

    public function download(Request $request, $id)
    {
        $isAuthenticated = Auth::guard('student')->check() || Auth::check();
        if (! $isAuthenticated && ! $request->hasValidSignature()) {
            abort(403, 'Please use the PDF download button to create a valid download link.');
        }

        return $this->serveApprovedDocument($request, $id, false);
    }

    public function downloadIntent(Request $request, $id)
    {
        $tenantId = Tenant::hostId($request->getHost());
        $exam = $this->tenantExam($tenantId, $id);
        $package = $this->tenantExamPackage($tenantId, $exam, $request->input('package'));
        $this->ensurePaperDownloadAvailable($exam, $package);
        $language = app(ExamPdfCacheService::class)->resolveLanguage($exam, $request->input('lang'));
        $build = app(ExamDocumentLifecycleService::class)->ready($exam, $package, $language, 'questions');

        if (! $build && ! app(\App\Services\ExamPdfPublicationService::class)->source($exam)) {
            return response()->json([
                'message' => 'This PDF is not available yet. An administrator must prepare and approve it.',
                'preparing' => false,
            ], 409);
        }

        return response()->json([
            'download_url' => URL::temporarySignedRoute('exam.print.download', now()->addMinutes(10), [
                'id' => $exam->slug ?: $exam->id,
                'package' => $package ? ($package->slug ?: $package->id) : null,
                'lang' => $language?->id,
            ]),
            'language' => $build ? ($language?->code ?: 'en') : 'original',
            'original_source' => ! $build,
        ]);
    }
    public function solutionDownload(Request $request, $id)
    {
        $tenantId = Tenant::hostId($request->getHost());
        $student = Auth::guard('student')->user();
        abort_unless($student, 401);

        $exam = $this->tenantExam($tenantId, $id);
        $package = $this->tenantExamPackage($tenantId, $exam, $request->query('package'));
        abort_unless($exam->canAttemptOnline() && $package && ($package->show_solution_pdf_download ?? true), 404);

        $ownsPackage = $this->studentOwnsPackage($student->id, $package->id, $tenantId);
        if (! $ownsPackage) {
            if (strtolower((string) $package->package_type) !== 'free') {
                return redirect()->route('courses.detail', $package->slug ?: $package->id)
                    ->with('error', 'Please purchase this package before downloading its solutions.');
            }

            DB::transaction(function () use ($student, $package, $tenantId) {
                if ($this->studentOwnsPackage($student->id, $package->id, $tenantId)) {
                    return;
                }

                $order = Order::create([
                    'organization_id' => $tenantId,
                    'student_id' => $student->id,
                    'total' => 0,
                    'discount' => 0,
                    'payment_method' => 'free',
                    'payment_status' => 'Completed',
                    'status' => 'completed',
                    'notes' => 'Automatically activated for solution PDF access.',
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'package_id' => $package->id,
                    'name' => $package->name,
                    'price' => 0,
                    'quantity' => 1,
                ]);

                $student->groups()->syncWithoutDetaching($package->groups()->pluck('groups.id')->all());
            });
        }

        return $this->serveApprovedDocument($request, $id, true);
    }

    public function solutionActivity(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:prompted,login_selected',
            'package' => 'nullable|string|max:255',
        ]);
        $tenantId = Tenant::hostId($request->getHost());
        $exam = $this->tenantExam($tenantId, $id);
        $package = $this->tenantExamPackage($tenantId, $exam, $validated['package'] ?? $request->query('package'));
        abort_unless($exam->canAttemptOnline() && $package && ($package->show_solution_pdf_download ?? true), 404);

        $guestId = session('guest_id') ?? $request->cookie('guest_id');
        if (! Auth::guard('student')->check() && ! Auth::check() && ! $guestId) {
            $guestId = (string) Str::uuid();
            $request->session()->put('guest_id', $guestId);
        }

        $eventName = $validated['action'] === 'login_selected'
            ? StudentActivityTracker::SOLUTION_PDF_LOGIN_SELECTED
            : StudentActivityTracker::SOLUTION_PDF_PROMPTED;

        if ($validated['action'] === 'login_selected' && ! Auth::guard('student')->check()) {
            $this->postAuthService->remember([
                'action' => 'solution_pdf',
                'package_id' => $package->id,
                'exam_id' => $exam->id,
                'group_id' => $package->groups()->value('groups.id'),
            ]);
        }

        StudentActivityTracker::track($eventName, [
            'organization_id' => $tenantId,
            'guest_id' => $guestId,
            'exam_id' => $exam->id,
            'package_id' => $package->id,
            'source' => Auth::guard('student')->check() ? 'student' : (Auth::check() ? 'admin' : 'guest'),
            'metadata' => [
                'document_name' => $exam->name,
                'document_context' => 'exam_solutions',
                'access_action' => $validated['action'],
            ],
        ], $request);

        return response()->noContent();
    }

    public function adminSolutionDownload(Request $request, $id)
    {
        abort_unless(Auth::check(), 403);

        return $this->serveApprovedDocument($request, $id, true, true);
    }

    private function serveApprovedDocument(Request $request, $id, bool $isSolution, bool $bypassAvailability = false)
    {
        $tenantId = Tenant::hostId($request->getHost());
        $exam = $this->tenantExam($tenantId, $id);
        $package = $this->tenantExamPackage($tenantId, $exam, $request->query('package'));
        if ($isSolution) abort_unless($package && ($bypassAvailability || $exam->canAttemptOnline()), 404);
        else $this->ensurePaperDownloadAvailable($exam, $package);

        $visible = $isSolution
            ? ($package->show_solution_pdf_download ?? true)
            : ($package->show_pdf_download ?? true);
        abort_unless($visible || $bypassAvailability, 404);

        $language = app(ExamPdfCacheService::class)->resolveLanguage($exam, $request->query('lang'));
        $build = app(ExamDocumentLifecycleService::class)->ready(
            $exam,
            $package,
            $language,
            $isSolution ? 'solutions' : 'questions'
        );

        if (! $build && ! $isSolution) {
            $source = app(\App\Services\ExamPdfPublicationService::class)->source($exam);
            if ($source) {
                StudentActivityTracker::trackPdfDownload([
                    'organization_id' => $tenantId, 'exam_id' => $exam->id, 'package_id' => $package?->id,
                    'metadata' => ['document_context' => 'exam_questions', 'delivery' => 'official_source_download', 'language' => 'original'],
                ], $request);
                return \Illuminate\Support\Facades\Storage::disk(app(\App\Services\ExamQualitySourceStorage::class)->diskFor($source))
                    ->download($source->file_path, (Str::slug($exam->name) ?: 'exam-'.$exam->id).'-official.pdf', [
                        'Content-Type' => 'application/pdf', 'X-Exam-PDF-Cache' => 'OFFICIAL-SOURCE',
                        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                    ]);
            }
        }
        abort_unless($build, 409, 'The approved PDF is not available yet. Please contact the administrator.');
        $display = app(ExamLanguageService::class)->display($exam, $language);
        $filename = (Str::slug((string) ($display['name'] ?: $exam->name)) ?: 'exam-'.$exam->id)
            .'-'.(Str::slug($language?->code ?: 'en') ?: 'en')
            .($isSolution ? '-solutions.pdf' : '.pdf');

        StudentActivityTracker::trackPdfDownload([
            'organization_id' => $tenantId,
            'exam_id' => $exam->id,
            'package_id' => $package?->id,
            'metadata' => [
                'document_context' => $isSolution ? 'exam_solutions' : 'exam_questions',
                'delivery' => 'approved_cached_download',
                'language' => $language?->code ?: 'en',
            ],
        ], $request);

        return response()->download($build->current_path, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Exam-PDF-Cache' => 'APPROVED',
        ]);
    }
    private function generateDownload(Request $request, $id, bool $isSolution, bool $bypassAvailability = false)
    {
        $tenantId = Tenant::hostId($request->getHost());
        $exam = Exam::query()
            ->where('organization_id', $tenantId)
            ->where(function ($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                }
                $query->orWhere('slug', $id);
            })
            ->firstOrFail();

        $package = null;
        $packageKey = $request->query('package');
        if ($packageKey) {
            $package = Package::query()
                ->where('organization_id', $tenantId)
                ->where(function ($query) use ($packageKey) {
                    if (is_numeric($packageKey)) {
                        $query->where('id', $packageKey);
                    }
                    $query->orWhere('slug', $packageKey);
                })
                ->whereHas('exams', fn ($query) => $query->where('exams.id', $exam->id))
                ->first();

            if (! $package) {
                abort(404);
            }

            $downloadEnabled = $isSolution
                ? ($package->show_solution_pdf_download ?? true)
                : ($package->show_pdf_download ?? true);

            if (! $downloadEnabled && ! $bypassAvailability) {
                abort(404);
            }
        }

        if (empty($exam->slug)) {
            $exam->forceFill(['slug' => $this->uniqueExamSlug($exam)])->save();
        }

        $pdfCache = app(ExamPdfCacheService::class);
        $language = $pdfCache->resolveLanguage($exam, $request->query('lang'));
        if ($language && ! app(ExamLanguageService::class)->isEnglish($language)
            && $exam->languages()->whereKey($language->id)->first()?->pivot?->translation_status !== 'ready') {
            $language = app(ExamLanguageService::class)->resolve($exam, 'en');
        }
        $display = app(ExamLanguageService::class)->display($exam, $language);
        $documentName = (string) ($display['name'] ?: $exam->name);
        $fingerprint = $pdfCache->fingerprint($exam, $language, $package, $isSolution);
        $cachedPath = $pdfCache->path($exam, $language, $package, $isSolution, $fingerprint);
        $filenameBase = Str::slug($documentName) ?: 'exam-'.$exam->id;
        $filename = $filenameBase
            .'-'.(Str::slug($language?->code ?: 'en') ?: 'en')
            .($isSolution ? '-solutions.pdf' : '.pdf');

        if (is_file($cachedPath) && filesize($cachedPath) >= 1000) {
            return response()->download($cachedPath, $filename, [
                'Content-Type' => 'application/pdf',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'X-Exam-PDF-Cache' => 'HIT',
            ]);
        }

        $binary = collect([
            env('PDF_CHROMIUM_PATH'),
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
        ])->first(fn ($candidate) => filled($candidate) && is_executable($candidate));

        $printParameters = array_filter([
            'id' => $exam->slug,
            'package' => $package?->slug ?: $package?->id,
            'lang' => $language?->id,
            'pdf_render' => 1,
            'solution' => $isSolution ? 1 : null,
        ]);
        $printUrl = $isSolution
            ? URL::temporarySignedRoute('exam.print', now()->addMinutes(5), $printParameters)
            : route('exam.print', $printParameters);

        if (!$binary) {
            return redirect($printUrl)->with('error', 'Direct download is unavailable; use Save as PDF.')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        $directory = dirname($cachedPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            abort(500, 'Unable to prepare the PDF cache directory.');
        }

        $temporaryPath = tempnam($directory, 'exam-');
        if ($temporaryPath === false) {
            abort(500, 'Unable to prepare the temporary PDF file.');
        }
        $pdfPath = $temporaryPath.'.pdf';
        @unlink($temporaryPath);
        $lock = Cache::lock('exam-pdf:'.$fingerprint, 240);
        if (! $lock->get()) {
            @unlink($pdfPath);
            abort(409, 'This PDF is already being prepared. Please try again shortly.');
        }
        if (is_file($cachedPath) && filesize($cachedPath) >= 1000) {
            $lock->release();
            @unlink($pdfPath);
            return response()->download($cachedPath, $filename, ['Content-Type' => 'application/pdf', 'X-Exam-PDF-Cache' => 'HIT']);
        }

        try {
            $result = Process::timeout(180)->run([
                $binary,
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-pdf-header-footer',
                '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=15000',
                '--print-to-pdf='.$pdfPath,
                $printUrl,
            ]);

            if ($result->failed() || !is_file($pdfPath) || filesize($pdfPath) < 1000) {
                @unlink($pdfPath);
                report(new \RuntimeException('On-demand PDF generation failed: '.$result->errorOutput()));
                return redirect($printUrl)->with('error', 'Direct download failed; use Save as PDF.')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
            }
            if (! @rename($pdfPath, $cachedPath)) {
                @unlink($pdfPath);
                throw new \RuntimeException('Unable to activate the generated PDF cache file.');
            }
            $pdfCache->removeSuperseded($cachedPath);

            StudentActivityTracker::trackPdfDownload([
                'organization_id' => $tenantId,
                'exam_id' => $exam->id,
                'package_id' => $package?->id,
                'metadata' => [
                    'document_name' => $documentName,
                    'document_context' => $isSolution ? 'exam_solutions' : 'exam_questions',
                    'delivery' => 'persistent_cached_download',
                    'language' => $language?->code ?: 'en',
                ],
            ], $request);

            return response()
                ->download($cachedPath, $filename, ['Content-Type' => 'application/pdf', 'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'X-Exam-PDF-Cache' => 'MISS']);
        } catch (\Throwable $exception) {
            @unlink($pdfPath);
            report($exception);

            return redirect($printUrl)->with('error', 'Direct download failed; use Save as PDF.')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        } finally {
            $lock->release();
        }
    }

    public function print(Request $request, $id)
    {
        $tenantId = Tenant::hostId($request->getHost());
        $isSolution = $request->boolean('solution');
        if ($isSolution && ! $request->hasValidSignature()) {
            abort(403);
        }

        $exam = Exam::query()
            ->where('organization_id', $tenantId)
            ->where(function ($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                }

                $query->orWhere('slug', $id);
            })
            ->first();
        
        if (!$exam) {
            return "Exam not found. ID: " . $id;
        }

        $package = null;
        $packageKey = $request->query('package');
        if ($packageKey) {
            $package = Package::query()
                ->where('organization_id', $tenantId)
                ->where(function ($query) use ($packageKey) {
                    if (is_numeric($packageKey)) {
                        $query->where('id', $packageKey);
                    }

                    $query->orWhere('slug', $packageKey);
                })
                ->whereHas('exams', function ($query) use ($exam) {
                    $query->where('exams.id', $exam->id);
                })
                ->first();

            $printEnabled = $isSolution
                ? ($package->show_solution_pdf_download ?? true)
                : ($package->show_pdf_download ?? true);

            if (! $package || ! $printEnabled) {
                abort(404);
            }
        }

        if (empty($exam->slug)) {
            $exam->forceFill(['slug' => $this->uniqueExamSlug($exam)])->save();
        }
        $pdfLanguage = app(ExamPdfCacheService::class)->resolveLanguage($exam, $request->query('lang'));
        if ($pdfLanguage && ! app(ExamLanguageService::class)->isEnglish($pdfLanguage)
            && $exam->languages()->whereKey($pdfLanguage->id)->first()?->pivot?->translation_status !== 'ready') {
            $pdfLanguage = app(ExamLanguageService::class)->resolve($exam, 'en');
        }
        $pdfDisplay = app(ExamLanguageService::class)->display($exam, $pdfLanguage);

        if ($request->isMethod('GET') && is_numeric($id) && !empty($exam->slug)) {
            return redirect()->route('exam.print', array_filter([
                'id' => $exam->slug,
                'package' => $packageKey,
                'lang' => $pdfLanguage?->id,
            ]), 301);
        }

        $configuration = function_exists('getConfiguration') ? getConfiguration() : null;
        $brandName = $configuration->organization_name ?? config('app.name', 'ExamElite');
        $brandLogo = $configuration?->logo ? asset($configuration->logo) : null;
        $siteUrl = url('/');
        $localizedExamName = (string) ($pdfDisplay['name'] ?: $exam->name);
        $documentTitle = $isSolution ? $localizedExamName . ' - Solutions' : $localizedExamName;
        $pdfTitleText = $isSolution
            ? trim((string) ($package?->solution_pdf_title_text ?? ''))
            : trim((string) ($package?->pdf_title_text ?? ''));
        $pdfHeaderText = $isSolution
            ? (trim((string) ($package?->solution_pdf_header_text ?? '')) ?: $documentTitle)
            : (trim((string) ($package?->pdf_header_text ?? '')) ?: $documentTitle);
        $pdfFooterText = $isSolution
            ? (trim((string) ($package?->solution_pdf_footer_text ?? '')) ?: $brandName)
            : (trim((string) ($package?->pdf_footer_text ?? '')) ?: $brandName);
        $pdfWatermarkText = $isSolution
            ? trim((string) ($package?->solution_pdf_watermark_text ?? ''))
            : trim((string) ($package?->pdf_watermark_text ?? ''));
        $brandNameCss = json_encode($brandName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdfHeaderCss = json_encode($pdfHeaderText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdfFooterCss = json_encode($pdfFooterText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $siteUrlCss = json_encode($siteUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $questions = DB::table('questions')
            ->join('exam_questions', 'questions.id', '=', 'exam_questions.question_id')
            ->leftJoin('qtypes', 'questions.qtype_id', '=', 'qtypes.id')
            ->leftJoin('subjects', 'questions.subject_id', '=', 'subjects.id')
            ->leftJoin('exam_sections', 'exam_questions.exam_section_id', '=', 'exam_sections.id')
            ->where('exam_questions.exam_id', $exam->id)
            ->where('questions.organization_id', $tenantId)
            ->select(
                'questions.*',
                'exam_questions.exam_section_id as pdf_exam_section_id',
                'qtypes.question_type as pdf_question_type',
                'qtypes.type as pdf_question_type_code',
                'subjects.subject_name as pdf_subject_name',
                'exam_sections.name as pdf_section_name',
                'exam_sections.duration as pdf_section_duration'
            )
            ->orderBy('exam_questions.id')
            ->get();
        $questions = app(ExamPdfCacheService::class)->overlayTranslations($questions, $pdfLanguage);
        
        if ($questions->isEmpty()) {
            return "No questions found for exam: " . htmlspecialchars($exam->name);
        }

        if (!$request->boolean('pdf_render')) {
        StudentActivityTracker::trackPdfDownload([
            'organization_id' => $tenantId,
            'exam_id' => $exam->id,
            'package_id' => $package?->id,
            'metadata' => [
                'document_name' => $exam->name,
                'document_context' => 'exam_questions',
                'delivery' => 'print_view',
            ],
        ], $request);
        }
        
        $repairContent = static function ($value): string {
            return app(\App\Services\MathContentNormalizer::class)->repairForDisplay((string) ($value ?? ''));
        };
        $pdfMathFontCss = $request->boolean('pdf_render')
            ? '@font-face {
                    font-family: "STIX Two Math";
                    src: url("/fonts/noto/STIXTwoMath-Regular.woff2") format("woff2");
                    font-style: normal;
                    font-weight: 400;
                    font-display: block;
                }
                math, math * {
                    font-family: "STIX Two Math" !important;
                    font-weight: 400 !important;
                }'
            : '';
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
            <meta name="referrer" content="no-referrer">
            <title>' . htmlspecialchars($documentTitle) . '</title>            <script>
                window.MathJax = {
                    loader: { load: ["[tex]/mhchem"] },
                    tex: {
                        packages: { "[+]": ["mhchem"] },
                        inlineMath: [["$", "$"], ["\\\\(", "\\\\)"]],
                        displayMath: [["\\\\[", "\\\\]"], ["$$", "$$"]],
                        processEscapes: true
                    },
                    svg: { fontCache: "global" }
                };
            </script>
            <script async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-svg.js"></script>
            <script>
                window.addEventListener("load", function () {
                    var finish = function () {
                        var typeset = (window.MathJax && window.MathJax.typesetPromise) ? window.MathJax.typesetPromise() : Promise.resolve();
                        Promise.resolve(typeset).then(function () { return document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve(); })
                            .then(function () { return Promise.all(Array.prototype.map.call(document.images, function (image) { return image.complete ? Promise.resolve() : new Promise(function (resolve) { image.addEventListener("load", resolve, { once: true }); image.addEventListener("error", resolve, { once: true }); }); })); })
                            .then(function () { document.documentElement.setAttribute("data-mathjax-ready", "1"); });
                    };
                    if (window.MathJax && window.MathJax.startup && window.MathJax.startup.promise) window.MathJax.startup.promise.then(finish, finish); else finish();
                });
            </script>
            
            <style>
                ' . $pdfMathFontCss . '

                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: "Noto Sans Devanagari", "Nirmala UI", Mangal, "Times New Roman", Georgia, serif;
                    background: white;
                    line-height: 1.55;
                }
                
                .container {
                    max-width: 100%;
                    background: white;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 35px;
                    padding-bottom: 15px;
                    border-bottom: 1.5px solid #333;
                }
                
                .exam-title {
                    font-size: 20pt;
                    font-weight: bold;
                    color: #1a1a1a;
                    margin-bottom: 8px;
                }
                
                .brand-logo {
                    max-height: 54px;
                    max-width: 220px;
                    margin-bottom: 8px;
                }
                
                .brand-name {
                    font-size: 13pt;
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 6px;
                }
                
                .exam-meta {
                    color: #555;
                    font-size: 10pt;
                    margin-top: 3px;
                }
                
                .question {
                    margin-bottom: 32px;
                    page-break-inside: avoid;
                }

                .question-group-heading {
                    align-items: center;
                    background: #f2f8f7;
                    border-left: 4px solid #176f68;
                    border-radius: 0 7px 7px 0;
                    break-after: avoid-page;
                    color: #174f4a;
                    display: flex;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 12pt;
                    font-weight: 700;
                    justify-content: space-between;
                    margin: 8px 0 22px;
                    padding: 10px 13px;
                    page-break-after: avoid;
                }

                .question-group-time {
                    background: #ffffff;
                    border: 1px solid #b9d8d5;
                    border-radius: 999px;
                    color: #176f68;
                    font-size: 8.5pt;
                    font-weight: 700;
                    margin-left: 12px;
                    padding: 3px 9px;
                    white-space: nowrap;
                }
                
                .question-heading {
                    display: flex;
                    align-items: baseline;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 6px 18px;
                    margin-bottom: 10px;
                }

                .question-number {
                    font-weight: bold;
                    font-size: 12pt;
                    color: #1a1a1a;
                }

                .question-details {
                    display: inline-flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 5px;
                    color: #555;
                    font-size: 9.5pt;
                }

                .question-type {
                    color: #176f68;
                    font-weight: 700;
                }

                .question-detail-separator {
                    color: #aaa;
                }
                
                .question-text {
                    margin-bottom: 15px;
                    font-size: 11pt;
                    line-height: 1.6;
                    color: #2c2c2c;
                }
                
                .question-text p {
                    margin-bottom: 8px;
                }
                
                .question-text img {
                    max-width: 100%;
                    height: auto;
                    margin: 10px 0;
                    display: block;
                }
                
                .options {
                    margin-top: 12px;
                    margin-bottom: 8px;
                }
                
                .option {
                    display: block;
                    margin-bottom: 8px;
                    font-size: 11pt;
                    line-height: 1.5;
                    color: #2c2c2c;
                }
                
                .option > p {
                    display: inline;
                    margin: 0;
                }


                .option-letter {
                    font-weight: bold;
                    display: inline-block;
                    width: 30px;
                    color: #1a1a1a;
                }
                
                .solution-panel {
                    margin-top: 15px;
                    padding: 12px 14px;
                    border-left: 3px solid #176f68;
                    border-radius: 0 6px 6px 0;
                    background: #f2f8f7;
                    color: #263238;
                    font-size: 10.5pt;
                    line-height: 1.55;
                    break-inside: avoid-page;
                    page-break-inside: avoid;
                }

                .solution-row + .solution-row {
                    margin-top: 9px;
                    padding-top: 9px;
                    border-top: 1px solid #d6e5e3;
                }

                .solution-label {
                    color: #176f68;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 9pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    margin-bottom: 3px;
                }

                .solution-content p:last-child {
                    margin-bottom: 0;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    font-size: 9pt;
                    color: #888;
                }
                
                .pdf-watermark {
                    display: none;
                }

                /* Action Buttons - Mobile Optimized */
                .action-buttons {
                    text-align: center;
                    margin-bottom: 25px;
                    position: sticky;
                    top: 10px;
                    z-index: 100;
                    background: white;
                    padding: 12px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .btn {
                    display: inline-block;
                    padding: 12px 28px;
                    margin: 0 10px;
                    background: #2c5282;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 600;
                    font-family: Arial, sans-serif;
                    text-decoration: none;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }
                
                .btn:hover {
                    background: #1a365d;
                    transform: translateY(-2px);
                }
                
                .btn:active {
                    transform: translateY(0px);
                }
                
                /* Mobile Responsive */
                @media screen and (max-width: 768px) {
                    body {
                        padding: 10px 0;
                    }
                    
                    .container {
                        padding: 20px 25px;
                    }
                    
                    .exam-title {
                        font-size: 18pt;
                    }
                    
                    .question-number {
                        font-size: 11pt;
                    }
                    
                    .question-text {
                        font-size: 10pt;
                    }
                    
                    .option {
                        font-size: 10pt;
                    }
                    
                    /* Mobile buttons - bigger and clearer */
                    .btn {
                        display: block;
                        width: 100%;
                        margin: 8px 0;
                        padding: 14px 20px;
                        font-size: 16px;
                        text-align: center;
                    }
                    
                    .action-buttons {
                        position: relative;
                        top: 0;
                        margin-bottom: 20px;
                        padding: 10px;
                    }
                }
                
                /* Small Mobile Devices */
                @media screen and (max-width: 480px) {
                    .container {
                        padding: 15px 18px;
                    }
                    
                    .btn {
                        padding: 15px 20px;
                        font-size: 16px;
                    }
                    
                    .question {
                        margin-bottom: 25px;
                    }
                }
                
                @media screen {
                    body {
                        background: #e8e8e8;
                        padding: 20px 0;
                    }
                    .container {
                        background: white;
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 35px 45px;
                        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
                    }
                }
                
                @media print {
                    @page {
                        size: A4;
                        margin: 2.25cm 1.7cm 2.25cm;

                        @top-left {
                            content: ' . $brandNameCss . ';
                            color: #176f68;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 8.5pt;
                            font-weight: 700;
                            letter-spacing: 0.02em;
                            text-align: left;
                            border-bottom: 1px solid #b9b9b9;
                            vertical-align: bottom;
                            padding-bottom: 5px;
                            margin-bottom: 0.22cm;
                        }

                        @top-center {
                            content: "";
                            border-bottom: 1px solid #b9b9b9;
                            margin-bottom: 0.22cm;
                        }

                        @top-right {
                            content: ' . $pdfHeaderCss . ';
                            color: #344054;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 8.5pt;
                            font-weight: 600;
                            letter-spacing: 0.01em;
                            text-align: right;
                            border-bottom: 1px solid #b9b9b9;
                            vertical-align: bottom;
                            padding-bottom: 5px;
                            margin-bottom: 0.22cm;
                        }

                        @bottom-left {
                            content: ' . $pdfFooterCss . ';
                            color: #176f68;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 8.5pt;
                            font-weight: 700;
                            letter-spacing: 0.02em;
                            text-align: left;
                            border-top: 1px solid #b9b9b9;
                            vertical-align: top;
                            padding-top: 6px;
                            margin-top: 0.22cm;
                        }

                        @bottom-center {
                            content: counter(page) " of " counter(pages);
                            color: #475467;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 8.5pt;
                            font-weight: 600;
                            text-align: center;
                            border-top: 1px solid #b9b9b9;
                            vertical-align: top;
                            padding-top: 6px;
                            margin-top: 0.22cm;
                        }

                        @bottom-right {
                            content: ' . $siteUrlCss . ';
                            color: #176f68;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 8.5pt;
                            font-weight: 700;
                            letter-spacing: 0.01em;
                            text-align: right;
                            border-top: 1px solid #b9b9b9;
                            vertical-align: top;
                            padding-top: 6px;
                            margin-top: 0.22cm;
                        }
                    }

                    @page :first {
                        margin-top: 1.25cm;

                        @top-left {
                            content: none;
                            border-bottom: none;
                        }

                        @top-center {
                            content: none;
                            border-bottom: none;
                        }

                        @top-right {
                            content: none;
                            border-bottom: none;
                        }
                    }
                    body {
                        background: white;
                        padding: 0 !important;
                        margin: 0;
                    }
                    .pdf-watermark {
                        display: block;
                        position: fixed;
                        top: 46%;
                        left: 50%;
                        width: 85%;
                        transform: translate(-50%, -50%) rotate(-32deg);
                        color: rgba(30, 105, 98, 0.055);
                        font-size: 52pt;
                        font-weight: 700;
                        text-align: center;
                        letter-spacing: 0.08em;
                        pointer-events: none;
                        z-index: 0;
                    }

                    .container {
                        position: relative;
                        z-index: 1;
                        background: transparent;
                    }

                .footer {
                        display: none;
                    }
                    
                    .container {
                        padding: 0;
                        margin: 0;
                        box-shadow: none;
                    }
                    
                    .action-buttons {
                        display: none;
                    }
                    
                    .question {
                        page-break-inside: auto;
                        break-inside: auto;
                    }

                    .question-number {
                        break-after: avoid-page;
                        page-break-after: avoid;
                    }

                    .option {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }
                    
                    .header {
                        margin-bottom: 25px;
                        margin-top: 0;
                    }
                    
                    .question {
                        margin-bottom: 25px;
                    }
                    
                    .option {
                        display: block;
                        margin-bottom: 8px;
                    }
                }
            </style>
        </head>
        <body>
            ' . ($pdfWatermarkText !== '' ? '<div class="pdf-watermark">' . htmlspecialchars($pdfWatermarkText) . '</div>' : '') . '

            <div class="container">
                <div class="action-buttons">
                    <button class="btn" onclick="window.print();">📄 Save as PDF</button>
                    <button class="btn" onclick="window.close();">❌ Close Window</button>
                </div>
                
                <div class="header">
                    ' . ($brandLogo ? '<img class="brand-logo" src="' . htmlspecialchars($brandLogo) . '" alt="' . htmlspecialchars($brandName) . '">' : '') . '
                    ' . ($pdfTitleText !== '' ? '<div class="brand-name">' . htmlspecialchars($pdfTitleText) . '</div>' : '') . '
                    <div class="exam-title">' . htmlspecialchars($documentTitle) . '</div>
                    <div class="exam-meta">
                        Total Questions: ' . $questions->count() . ' | Duration: ' . ($exam->duration ?? 'N/A') . ' minutes
                    </div>
                </div>';
        
        $groupingMode = (string) ($exam->grouping_mode ?: ($exam->timer_mode === 'section' ? 'section' : 'subject'));
        $groupingMode = in_array($groupingMode, ['subject', 'section'], true) ? $groupingMode : 'none';
        $timerMode = (string) ($exam->timer_mode ?: ($exam->is_subject_timer ? $groupingMode : 'none'));
        $subjectDurations = $timerMode === 'subject'
            ? DB::table('exam_subject_durations')->where('exam_id', $exam->id)->pluck('duration', 'subject_id')
            : collect();
        $currentGroupKey = null;
        $counter = 1;
        foreach ($questions as $q) {
            $groupKey = null;
            $groupName = null;
            $groupDuration = null;

            if ($groupingMode === 'section' && filled($q->pdf_section_name ?? null)) {
                $groupKey = 'section-'.(int) ($q->pdf_exam_section_id ?? 0);
                $groupName = trim((string) $q->pdf_section_name);
                if ($timerMode === 'section' && (int) ($q->pdf_section_duration ?? 0) > 0) {
                    $groupDuration = (int) $q->pdf_section_duration;
                }
            } elseif ($groupingMode === 'subject' && filled($q->pdf_subject_name ?? null)) {
                $groupKey = 'subject-'.(int) ($q->subject_id ?? 0);
                $groupName = trim((string) $q->pdf_subject_name);
                $configuredDuration = (int) $subjectDurations->get((int) ($q->subject_id ?? 0), 0);
                if ($configuredDuration > 0) {
                    $groupDuration = $configuredDuration;
                }
            }

            if ($groupKey !== null && $groupKey !== $currentGroupKey) {
                $html .= '<div class="question-group-heading"><span>'.htmlspecialchars($groupName).'</span>';
                if ($groupDuration !== null) {
                    $html .= '<span class="question-group-time">Time: '.$groupDuration.' minutes</span>';
                }
                $html .= '</div>';
                $currentGroupKey = $groupKey;
            }

            $html .= '<div class="question">';
            $typeLabel = $this->pdfQuestionTypeLabel($q->pdf_question_type ?? null);
            $positiveMarks = number_format((float) ($q->marks ?? 0), 1, '.', '');
            $negativeMarks = number_format(abs((float) ($q->negative_marks ?? 0)), 1, '.', '');
            $html .= '<div class="question-heading">';
            $html .= '<div class="question-number">Question ' . $counter . '</div>';
            $html .= '<div class="question-details"><span class="question-type">' . htmlspecialchars($typeLabel) . '</span><span class="question-detail-separator">&middot;</span><span>Marks: ' . $positiveMarks . ' | ' . $negativeMarks . '</span></div>';
            $html .= '</div>';
            $html .= '<div class="question-text">' . $repairContent($q->question) . '</div>';
            $html .= '<div class="options">';
            
            if (!empty($q->option1) && trim($q->option1) != '') 
                $html .= '<div class="option"><span class="option-letter">A.</span> '  . $repairContent($q->option1) . '</div>';
            if (!empty($q->option2) && trim($q->option2) != '') 
                $html .= '<div class="option"><span class="option-letter">B.</span> '  . $repairContent($q->option2) . '</div>';
            if (!empty($q->option3) && trim($q->option3) != '') 
                $html .= '<div class="option"><span class="option-letter">C.</span> '  . $repairContent($q->option3) . '</div>';
            if (!empty($q->option4) && trim($q->option4) != '') 
                $html .= '<div class="option"><span class="option-letter">D.</span> '  . $repairContent($q->option4) . '</div>';
            if (!empty($q->option5) && trim($q->option5) != '') 
                $html .= '<div class="option"><span class="option-letter">E.</span> '  . $repairContent($q->option5) . '</div>';
            if (!empty($q->option6) && trim($q->option6) != '') 
                $html .= '<div class="option"><span class="option-letter">F.</span> '  . $repairContent($q->option6) . '</div>';
            
            $html .= '</div>';

            if ($isSolution) {
                $answerHtml = $repairContent($this->solutionAnswerHtml($q));
                $explanationHtml = $repairContent($q->explanation);
                $html .= '<div class="solution-panel">';
                $html .= '<div class="solution-row"><div class="solution-label">Correct Answer</div><div class="solution-content">' . ($answerHtml !== '' ? $answerHtml : 'Not provided') . '</div></div>';
                $html .= '<div class="solution-row"><div class="solution-label">Explanation</div><div class="solution-content">' . ($explanationHtml !== '' ? $explanationHtml : 'No explanation provided.') . '</div></div>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $counter++;
        }
        
        $html .= '<div class="footer">' . htmlspecialchars($brandName) . ' - Exam Paper</div>';
        $html .= '</div>';
        $html .= '</body></html>';
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function tenantExam(int $tenantId, $id): Exam
    {
        return Exam::query()
            ->where('organization_id', $tenantId)
            ->where(function ($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                }
                $query->orWhere('slug', $id);
            })
            ->firstOrFail();
    }

    private function ensurePaperDownloadAvailable(Exam $exam, ?Package $package): void
    {
        abort_unless($exam->status === 'Active', 404);
        if ($package) {
            abort_unless($package->status && ($package->show_pdf_download ?? true), 404);
        } else {
            // A missing/invalid package cannot bypass the package download setting.
            abort_if($exam->packages()->exists(), 404);
        }
    }

    private function tenantExamPackage(int $tenantId, Exam $exam, $packageKey): ?Package
    {
        if (! $packageKey) {
            return null;
        }

        return Package::query()
            ->where('organization_id', $tenantId)
            ->where(function ($query) use ($packageKey) {
                if (is_numeric($packageKey)) {
                    $query->where('id', $packageKey);
                }
                $query->orWhere('slug', $packageKey);
            })
            ->whereHas('exams', fn ($query) => $query->where('exams.id', $exam->id))
            ->first();
    }

    private function studentOwnsPackage(int $studentId, int $packageId, int $tenantId): bool
    {
        return Order::query()
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->where(function ($query) use ($tenantId) {
                $query->where('organization_id', $tenantId)->orWhereNull('organization_id');
            })
            ->whereHas('items', fn ($query) => $query->where('package_id', $packageId))
            ->exists();
    }
    private function solutionAnswerHtml(object $question): string
    {
        $type = strtoupper(trim((string) ($question->pdf_question_type_code ?? '')));

        if ($type === 'T') {
            return htmlspecialchars(ucfirst(strtolower(trim((string) ($question->true_false ?? '')))));
        }

        if (in_array($type, ['F', 'B'], true)) {
            $config = json_decode((string) ($question->fill_blank_config ?? ''), true);
            $blanks = collect($config['blanks'] ?? [])->map(fn ($blank) => implode(' / ', $blank['answers'] ?? []))->filter();
            return $blanks->isNotEmpty() ? e($blanks->implode(' | ')) : trim((string) ($question->fill_blank ?? ''));
        }

        if ($type === 'NAT') {
            $config = json_decode((string) ($question->nat_config ?? ''), true) ?: [];
            return e(match ($config['mode'] ?? 'exact') {
                'range' => ($config['min'] ?? '').' to '.($config['max'] ?? ''),
                'tolerance' => ($config['value'] ?? '').' ± '.($config['tolerance'] ?? 0),
                default => (string) ($config['value'] ?? ''),
            });
        }

        if ($type === 'S') {
            return trim((string) ($question->si_answer1 ?? ''));
        }

        $evaluator = app(QuestionAnswerEvaluator::class);
        $answers = collect($evaluator->correctOptionIndices($question))->map(function ($index) use ($question, $evaluator) {
            $option = trim((string) $evaluator->optionValue($question, $index));
            return '<div><strong>'.chr(64 + $index).'.</strong> '.$option.'</div>';
        })->all();
        if ($answers !== []) return implode('', $answers);

        return trim((string) ($question->answer ?? ''));
    }
    private function pdfQuestionTypeLabel(?string $questionType): string
    {
        $label = trim((string) $questionType);
        $normalized = strtolower($label);

        return match (true) {
            str_contains($normalized, 'true') && str_contains($normalized, 'false') => 'True/False',
            str_contains($normalized, 'multiple choice'), str_contains($normalized, 'mcq'), str_contains($normalized, 'objective') => 'MCQ',
            str_contains($normalized, 'fill') => 'Fill in the Blank',
            str_contains($normalized, 'numerical'), str_contains($normalized, 'nat') => 'NAT',
            str_contains($normalized, 'descriptive'), str_contains($normalized, 'subjective'), str_contains($normalized, 'essay') => 'Descriptive',
            default => $label !== '' ? $label : 'Question',
        };
    }

    private function uniqueExamSlug(Exam $exam): string
    {
        $baseSlug = Str::slug($exam->name ?: 'exam-' . $exam->id) ?: 'exam-' . $exam->id;
        $slug = $baseSlug;
        $counter = 2;

        while (Exam::where('slug', $slug)->where('id', '!=', $exam->id)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}
