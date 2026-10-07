@extends('website.layouts.app')

@php
    $displayText = static function ($value): string {
        if (is_array($value)) {
            $candidate = $value['en'] ?? reset($value);
            return is_scalar($candidate) ? (string) $candidate : '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded['en'] ?? reset($decoded);
                return is_scalar($candidate) ? (string) $candidate : '';
            }
        }

        return is_scalar($value) ? (string) $value : '';
    };

    $examName = $displayText($exam->name);
    $packageName = $package ? $displayText($package->name) : null;
    $packageSlug = $package ? ($package->slug ?: $package->id) : null;
    $groupId = $package && $package->groups->isNotEmpty() ? $package->groups->first()->id : null;
    $allowGuestExamAttempts = getConfiguration()->allow_guest_exam_attempts ?? true;
    $packageType = strtolower($package->package_type ?? 'free');
    $showPdf = $package ? ($package->show_pdf_download ?? true) : false;
    $showSolutionPdf = $package ? ($package->show_solution_pdf_download ?? true) : false;
    $price = $package ? ((!empty($package->discounted_amount) && $package->discounted_amount > 0) ? $package->discounted_amount : ($package->amount ?? 0)) : 0;
    $hasVisibleContent = static function ($value): bool {
        $html = trim((string) $value);
        if ($html === '') return false;
        if (preg_match('/<(img|picture|video|audio|iframe|table)\b/i', $html)) return true;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $text);
        return $text !== '';
    };
    $showInstructions = $hasVisibleContent($exam->instruction);
    $showSyllabus = $hasVisibleContent($exam->syllabus);
@endphp

@section('title', $displayText($exam->meta_title) ?: $examName)

@section('content')

<style>
    .exam-landing {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 5%, var(--theme-body-bg, #ffffff));
        padding: 22px 0 52px;
    }

    .exam-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        color: var(--theme-text, #64748b);
        font-size: 13px;
        font-weight: 700;
    }

    .exam-breadcrumb a {
        color: var(--theme-primary, #0f766e);
        text-decoration: none;
    }

    .exam-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 350px;
        gap: 22px;
        align-items: start;
    }

    .exam-panel {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .exam-main {
        padding: clamp(18px, 4vw, 30px);
    }

    .exam-kicker {
        align-items: center;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #ffffff);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 12px;
        font-weight: 900;
        gap: 6px;
        margin-bottom: 14px;
        padding: 7px 11px;
        text-transform: uppercase;
    }

    .exam-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.35rem, 5.5vw, 2.25rem);
        font-weight: 900;
        line-height: 1.14;
        margin-bottom: 12px;
        overflow-wrap: anywhere;
    }

    .exam-copy {
        color: var(--theme-text, #64748b);
        font-size: 1rem;
        line-height: 1.65;
        max-width: 760px;
    }

    .exam-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 20px;
    }

    .exam-stat {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 5%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        border-radius: 10px;
        padding: 14px;
    }

    .exam-stat span {
        color: var(--theme-text, #64748b);
        display: block;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .exam-stat strong {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 850;
    }

    .exam-content-section {
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        padding: 20px clamp(18px, 4vw, 30px);
    }

    .exam-content-section h2 {
        color: var(--theme-heading, #0f172a);
        font-size: 1.05rem;
        font-weight: 900;
        margin-bottom: 10px;
    }

    .exam-content-section .content-body {
        color: var(--theme-text, #64748b);
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .exam-content-section .content-body img,
    .exam-content-section .content-body table {
        max-width: 100%;
    }

    .exam-action-card {
        padding: 20px;
        position: sticky;
        top: 92px;
    }

    .exam-action-title {
        color: var(--theme-heading, #0f172a);
        font-size: 1.05rem;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .exam-action-note {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        line-height: 1.55;
        margin-bottom: 16px;
    }

    .exam-price {
        color: var(--theme-primary, #0f766e);
        font-size: 1.3rem;
        font-weight: 800;
        margin-bottom: 16px;
    }

    .exam-btn {
        align-items: center;
        border: 0;
        border-radius: 10px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-weight: 800;
        gap: 8px;
        justify-content: center;
        min-height: 46px;
        padding: 11px 16px;
        text-decoration: none;
        width: 100%;
    }

    .exam-btn-primary {
        background: var(--theme-primary, #0f766e);
    }

    .exam-btn-secondary {
        background: var(--theme-secondary, #f59e0b);
    }

    .exam-side-list {
        display: grid;
        gap: 10px;
        margin: 16px 0 0;
    }

    .exam-side-item {
        align-items: center;
        color: var(--theme-text, #64748b);
        display: flex;
        font-size: 13px;
        gap: 10px;
    }

    .exam-side-item i {
        align-items: center;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        border-radius: 8px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        height: 32px;
        justify-content: center;
        width: 32px;
    }

    @media (max-width: 991.98px) {
        .exam-shell {
            grid-template-columns: 1fr;
        }

        .exam-action-card {
            position: static;
        }
    }

    @media (max-width: 575.98px) {
        .exam-stats {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .exam-title {
            font-size: 1.32rem;
        }
    }
</style>

<section class="exam-landing">
    <div class="container">
        <nav class="exam-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">{{ __('website.home') }}</a>
            <span>/</span>
            <a href="{{ route('courses.index') }}">{{ __('ui.exam_packages') }}</a>
            @if($package)
                <span>/</span>
                <a href="{{ route('courses.detail', $packageSlug) }}">{{ $packageName }}</a>
            @endif
            <span>/</span>
            <span>{{ $examName }}</span>
        </nav>

        <div class="exam-shell">
            <main class="exam-panel">
                <div class="exam-main">
                    <span class="exam-kicker"><i class="ri-file-list-3-line"></i> {{ __('ui.exam_details') }}</span>
                    <h1 class="exam-title">{{ $examName }}</h1>
                    <p class="exam-copy">
                        Review the key details, then start the test when you are ready. You can also open the full package to see related papers.
                    </p>

                    <div class="exam-stats">
                        <div class="exam-stat">
                            <span>{{ __('ui.questions') }}</span>
                            <strong>{{ $exam->questions_count ?? 0 }}</strong>
                        </div>
                        <div class="exam-stat">
                            <span>{{ __('ui.duration') }}</span>
                            <strong>{{ $exam->duration ? $exam->duration . ' mins' : 'Not set' }}</strong>
                        </div>
                        <div class="exam-stat">
                            <span>{{ __('ui.package') }}</span>
                            <strong>{{ $packageName ?: 'Available in packages' }}</strong>
                        </div>
                    </div>
                </div>

                @if($showInstructions)
                    <section class="exam-content-section">
                        <h2>{{ __('ui.instructions') }}</h2>
                        <div class="content-body">{!! $exam->instruction !!}</div>
                    </section>
                @endif

                @if($showSyllabus)
                    <section class="exam-content-section">
                        <h2>{{ __('ui.syllabus') }}</h2>
                        <div class="content-body">{!! $exam->syllabus !!}</div>
                    </section>
                @endif
            </main>

            <aside class="exam-panel exam-action-card">
                <h2 class="exam-action-title">{{ !$exam->canAttemptOnline() ? 'Download Paper' : ($packageType === 'paid' ? 'Enroll to Start' : 'Start This Exam') }}</h2>
                @if($package)
                    <p class="exam-action-note">
                        {{ $packageType === 'paid'
                            ? 'This paid paper is included in ' . $packageName . '. Complete checkout once to access included papers.'
                            : 'This paper is included in ' . $packageName . '. Start directly or open the package to see every paper.' }}
                    </p>

                    @if($packageType === 'paid')
                        <div class="exam-price">
                            {{ $configuration_detail->currency }}{{ number_format($price, 2) }}
                        </div>
                    @else
                        <div class="exam-price">{{ __('website.course_free') }}</div>
                    @endif

                    @if(!$exam->canAttemptOnline())
                        <p class="exam-action-note">PDF only. Online attempts become available when questions are published.</p>
                    @elseif($packageType === 'free' && !$allowGuestExamAttempts)
                        <a href="{{ route('student.signin', ['action' => 'start_exam', 'package_id' => $package->id, 'exam_id' => $exam->id, 'group_id' => $groupId]) }}" class="exam-btn exam-btn-primary">
                            Login to Start <i class="ri-login-circle-line"></i>
                        </a>
                    @else
                        <button class="exam-btn exam-btn-primary startExamBtn" data-groupid="{{ $groupId }}" data-id="{{ $package->id }}" data-exam="{{ $exam->slug ?: $exam->id }}">
                            {{ $packageType === 'paid' ? 'Checkout / Attempt' : 'Attempt' }} <i class="ri-arrow-right-line"></i>
                        </button>
                    @endif

                    @if($showPdf)
                        @php
                            $paperLanguages = app(App\Services\ExamLanguageService::class)->available($exam)->map(fn ($language) => [
                                'id' => $language->id, 'code' => $language->code, 'name' => $language->name,
                            ])->values();
                            $preferredPaperLanguage = auth('student')->user()?->language ?: session('preferred_exam_language', 'en');
                        @endphp
                        <button class="exam-btn exam-btn-secondary downloadPdfBtn mt-2" data-pdf-intent-url="{{ route('exam.print.intent', ['id' => $exam->slug ?: $exam->id]) }}" data-package="{{ $package->slug ?: $package->id }}" data-exam-id="{{ $exam->slug ?: $exam->id }}" data-exam-name="{{ $examName }}"
                            data-pdf-languages='@json($paperLanguages)' data-preferred-language="{{ $preferredPaperLanguage }}">
                            <i class="ri-download-2-line"></i> {{ __('ui.paper_pdf') }}
                        </button>
                    @endif
                    @if($showSolutionPdf && $exam->canAttemptOnline())
                        <a href="{{ route('student.exam.solution.download', ['id' => $exam->slug ?: $exam->id, 'package' => $package->slug ?: $package->id]) }}" class="exam-btn exam-btn-secondary protectedSolutionPdfBtn mt-2" data-login-required="{{ auth('student')->check() ? '0' : '1' }}" data-solution-activity-url="{{ route('exam.solution.activity', ['id' => $exam->slug ?: $exam->id]) }}" data-package="{{ $package->slug ?: $package->id }}" title="{{ __('ui.download_solutions') }}">
                            <i class="ri-download-2-line"></i> {{ __('ui.solution') }}
                        </a>
                    @endif

                    <a href="{{ route('courses.detail', $packageSlug) }}" class="exam-btn exam-btn-secondary mt-2">
                        View Full Package <i class="ri-stack-line"></i>
                    </a>
                @else
                    @if(!$exam->packages()->exists() && app(App\Services\ExamPdfPublicationService::class)->source($exam))
                    <button class="exam-btn exam-btn-secondary downloadPdfBtn" data-pdf-intent-url="{{ route('exam.print.intent', ['id' => $exam->slug ?: $exam->id]) }}" data-exam-id="{{ $exam->slug ?: $exam->id }}" data-exam-name="{{ $examName }}" data-pdf-languages='[]'>Download official PDF</button>
                    @if($exam->canAttemptOnline())<a class="exam-btn exam-btn-primary mt-2" href="{{ route('student.instructions', ['id' => $exam->id]) }}">Attempt</a>@endif
                    @else<p class="exam-action-note">{{ __('ui.exam_not_public') }}</p>@endif
                    <a href="{{ route('courses.index') }}" class="exam-btn exam-btn-primary">
                        Browse Packages <i class="ri-arrow-right-line"></i>
                    </a>
                @endif

                <div class="exam-side-list">
                    <div class="exam-side-item"><i class="ri-question-line"></i> {{ $exam->questions_count ?? 0 }} questions</div>
                    <div class="exam-side-item"><i class="ri-time-line"></i> {{ $exam->duration ? $exam->duration . ' minutes' : 'Duration not set' }}</div>
                    @if($package)
                        <div class="exam-side-item"><i class="ri-book-open-line"></i> {{ $packageName }}</div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
