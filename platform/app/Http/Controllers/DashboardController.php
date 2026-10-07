<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Models\Question;
use App\Models\Group;
use App\Models\Category;
use App\Models\Package;
use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function dashboard()
    {
        // --- 🔒 SECURITY CONFIGURATION START ---
        $user = Auth::user();
        $isStaff = ($user && $user->ugroup_id != 0); // Check agar staff hai (Admin nahi)
        $groupIds = [];
        $tenantId = class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
        $showDemoStudents = SaasAccess::isPlatformAdmin() && Schema::hasColumn('students', 'is_demo');

        if ($isStaff) {
            $groupIds = getUserGroupIds();
        }

        // Exam-content groups are optional. When none are assigned, the staff
        // member sees all tenant data allowed by their Permission Role.
        $restrictByGroups = $isStaff && ! empty($groupIds);
        $dashboardAudience = $showDemoStudents ? 'platform' : ($isStaff ? 'staff' : 'admin');
        $dashboardCacheKey = 'admin.dashboard.v3.'.($tenantId ?: 'platform').'.'.$dashboardAudience.'.'.sha1(implode(',', $groupIds));
        $cachedDashboardData = Cache::get($dashboardCacheKey);

        if (is_array($cachedDashboardData)) {
            return view('dashboard', $cachedDashboardData);
        }
        // 1. Students Count (Filtered)
        $studentsQuery = Student::query()
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            });
        if ($restrictByGroups) {
            $studentsQuery->whereHas('groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $studentsCount = (clone $studentsQuery)
            ->when(Schema::hasColumn('students', 'is_demo'), function ($query) {
                $query->where(fn ($students) => $students->whereNull('is_demo')->orWhere('is_demo', false));
            })
            ->count();
        $demoStudentsCount = $showDemoStudents
            ? (clone $studentsQuery)->where('is_demo', true)->count()
            : 0;
        $totalStudentsCount = $studentsCount + $demoStudentsCount;

        // 2. Revenue & Orders (Filtered by Student's Group)
        $ordersQuery = Order::query()
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            });
        if ($restrictByGroups) {
            $ordersQuery->whereHas('student.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        // Note: Hum query clone kar rahe hain taaki dobara likhna na pade
        $totalRevenue = (clone $ordersQuery)->sum('total');
        $revenueLast30Days = (clone $ordersQuery)->where('created_at', '>=', Carbon::now()->subDays(30))->sum('total');

        // 3. New Students (Filtered)
        $newStudentsQuery = Student::query()
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->when(Schema::hasColumn('students', 'is_demo'), function ($query) {
                $query->where(fn ($students) => $students->whereNull('is_demo')->orWhere('is_demo', false));
            })
            ->where('created_at', '>=', Carbon::now()->subDays(7));
        if ($restrictByGroups) {
            $newStudentsQuery->whereHas('groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $newStudentsCount = $newStudentsQuery->count();

        // 4. Overall Performance (Filtered by Exams in User's Group)
        $performanceQuery = ExamResult::query()
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            });
        if ($restrictByGroups) {
            $performanceQuery->whereHas('exam.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $overallPerformance = $performanceQuery->selectRaw('AVG(percent) as average_score, COUNT(*) as total_attempts')->first();

        // 5. Difficult Exams (Filtered)
        $difficultExamsQuery = ExamResult::select('exam_id', DB::raw('AVG(percent) as average_percentage'))
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->groupBy('exam_id')
            ->orderBy('average_percentage', 'asc')
            ->with('exam:id,name');
        
        if ($restrictByGroups) {
            $difficultExamsQuery->whereHas('exam.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $difficultExams = $difficultExamsQuery->limit(5)->get();

        // 6. Top Packages (Filtered by Package Groups)
        // Logic: OrderItem -> Package -> PackageGroups
        $topPackagesQuery = OrderItem::select('package_id', DB::raw('COUNT(*) as sales_count'))
            ->whereNotNull('package_id')
            ->whereHas('package', function ($q) use ($tenantId) {
                $q->when($tenantId, function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                });
            })
            ->groupBy('package_id')
            ->orderBy('sales_count', 'desc')
            ->with('package:id,name');

        if ($restrictByGroups) {
            $topPackagesQuery->whereHas('package.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $topPackages = $topPackagesQuery->limit(5)->get();
        
        // 7. Problematic Questions (Filtered by Exam Groups)
        $probQuestionsQuery = ExamStat::select('question_id', DB::raw('SUM(CASE WHEN ques_status = "W" THEN 1 ELSE 0 END) as wrong_answers'), DB::raw('COUNT(id) as total_attempts'))
            ->whereHas('question')
            ->where('answered', 1)
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->groupBy('question_id')
            ->orderByRaw('(SUM(CASE WHEN ques_status = "W" THEN 1 ELSE 0 END) / COUNT(id)) DESC')
            ->havingRaw('COUNT(id) > 10')
            ->with('question:id,question');

        if ($restrictByGroups) {
            $probQuestionsQuery->whereHas('exam.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $problematicQuestions = $probQuestionsQuery->limit(5)->get();

        // 8. Question Counts by Type (Filtered by Subject Groups if possible, or Exam Groups)
        // Best approach: Filter questions that belong to subjects linked to the user's groups
        $questionCountsQuery = Question::select('qtype_id', DB::raw('count(*) as total'))
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->groupBy('qtype_id')
            ->with('qtype');

        if ($restrictByGroups) {
            // Assuming Question -> Subject -> Groups relationship exists based on DB schema
            $questionCountsQuery->whereHas('subject.groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $questionCounts = $questionCountsQuery->get()->toArray();

        // 9. Student Status (Filtered)
        $studentStatusQuery = Student::select('status', DB::raw('count(*) as total'))
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->when(Schema::hasColumn('students', 'is_demo'), function ($query) {
                $query->where(fn ($students) => $students->whereNull('is_demo')->orWhere('is_demo', false));
            })
            ->groupBy('status');
        
        if ($restrictByGroups) {
            $studentStatusQuery->whereHas('groups', function($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }
        $studentStatusCounts = $studentStatusQuery->get()->toArray();

        // --- ACTIVITY FEED (Filtered) ---

        // Recent Students
        $recentStudentsQuery = Student::query()
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->when(Schema::hasColumn('students', 'is_demo'), function ($query) {
                $query->where(fn ($students) => $students->whereNull('is_demo')->orWhere('is_demo', false));
            })
            ->latest()
            ->limit(3);
        if ($restrictByGroups) {
            $recentStudentsQuery->whereHas('groups', function($q) use ($groupIds) { $q->whereIn('groups.id', $groupIds); });
        }
        $recentStudents = $recentStudentsQuery->get()->map(function ($item) {
            if (empty($item->name)) return null; 
            return (object) [
                'type' => 'student_signup',
                'description' => $item->name . ' just registered.',
                'timestamp' => $item->created_at,
                'icon' => 'ri-user-add-line',
                'color' => 'primary'
            ];
        });

        // Recent Results
        $recentResultsQuery = ExamResult::whereNotNull('end_time')
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->latest()
            ->with('student:id,name', 'exam:id,name')
            ->limit(3);
        if ($restrictByGroups) {
            $recentResultsQuery->whereHas('exam.groups', function($q) use ($groupIds) { $q->whereIn('groups.id', $groupIds); });
        }
        $recentResults = $recentResultsQuery->get()->map(function ($item) {
            if (!$item->student || !$item->exam) return null;
            return (object) [
                'type' => 'exam_completed',
                'description' => $item->student->name . ' completed the exam: ' . $item->exam->name,
                'timestamp' => $item->end_time,
                'icon' => 'ri-file-text-line',
                'color' => 'success'
            ];
        });

        // Recent Orders
        $recentOrdersQuery = Order::where('status', 'completed')
            ->when($tenantId, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->latest()
            ->with('student:id,name')
            ->limit(3);
        if ($restrictByGroups) {
            $recentOrdersQuery->whereHas('student.groups', function($q) use ($groupIds) { $q->whereIn('groups.id', $groupIds); });
        }
        $recentOrders = $recentOrdersQuery->get()->map(function ($item) {
            if ($item->student) {
                $name = $item->student->name;
            } else {
                $name = $item->name ?? 'a guest';
            }
            return (object) [
                'type' => 'new_order',
                'description' => 'New order received from ' . $name . '.',
                'timestamp' => $item->created_at,
                'icon' => 'ri-shopping-cart-2-line',
                'color' => 'info'
            ];
        });
        
        $activityFeed = $recentStudents->merge($recentResults)->merge($recentOrders)
                        ->filter()
                        ->sortByDesc('timestamp')
                        ->take(5);

        $slowDashboardCacheKey = $dashboardCacheKey.'.slow';
        $setupChecklist = $this->setupChecklist($tenantId);
        $slowDashboardData = Cache::remember($slowDashboardCacheKey, now()->addSeconds(60), fn () => [
            'activityTrend' => $this->activityTrend($tenantId, $restrictByGroups, $groupIds),
            'platformSummary' => $this->platformSummary($tenantId, $restrictByGroups, $groupIds),
            'groupCoverage' => $this->groupCoverage($tenantId, $restrictByGroups, $groupIds),
            'categoryCoverage' => $this->categoryCoverage($tenantId, $restrictByGroups, $groupIds),
        ]);
        $activityTrend = $slowDashboardData['activityTrend'];
        $platformSummary = $slowDashboardData['platformSummary'];
        $groupCoverage = $slowDashboardData['groupCoverage'];
        $categoryCoverage = $slowDashboardData['categoryCoverage'];

        $viewData = compact(
            'studentsCount', 'demoStudentsCount', 'totalStudentsCount', 'showDemoStudents',
            'totalRevenue', 'revenueLast30Days', 'newStudentsCount', 'overallPerformance',
            'difficultExams', 'topPackages', 'problematicQuestions',
            'questionCounts', 'studentStatusCounts',
            'activityFeed',
            'setupChecklist', 'activityTrend',
            'platformSummary', 'groupCoverage', 'categoryCoverage'
        );
        Cache::put($dashboardCacheKey, $viewData, now()->addSeconds(60));

        return view('dashboard', $viewData);
    }

    private function platformSummary(?int $tenantId, bool $restrictByGroups, array $groupIds): array
    {
        $groups = Group::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, fn ($query) => $query->whereIn('id', $groupIds))
            ->count();

        $categories = Category::query()
            ->parents()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->count();

        $subcategories = Category::query()
            ->children()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->count();

        $packages = Package::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->count();

        $exams = Exam::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->count();

        $questions = Question::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->count();

        return compact('groups', 'categories', 'subcategories', 'packages', 'exams', 'questions');
    }

    private function groupCoverage(?int $tenantId, bool $restrictByGroups, array $groupIds): array
    {
        $hasDemoStudents = Schema::hasColumn('students', 'is_demo');
        $questionCount = DB::table('questions as coverage_questions')
            ->selectRaw('COUNT(DISTINCT coverage_questions.id)')
            ->where(function ($groupScope) {
                $groupScope
                    ->whereExists(function ($directGroupScope) {
                        $directGroupScope->selectRaw('1')
                            ->from('question_groups as coverage_question_groups')
                            ->whereColumn('coverage_question_groups.question_id', 'coverage_questions.id')
                            ->whereColumn('coverage_question_groups.group_id', 'groups.id');
                    })
                    ->orWhereExists(function ($examGroupScope) {
                        $examGroupScope->selectRaw('1')
                            ->from('exam_questions as coverage_exam_questions')
                            ->join('exam_groups as coverage_exam_groups', 'coverage_exam_groups.exam_id', '=', 'coverage_exam_questions.exam_id')
                            ->whereColumn('coverage_exam_questions.question_id', 'coverage_questions.id')
                            ->whereColumn('coverage_exam_groups.group_id', 'groups.id');
                    })
                    ->orWhereExists(function ($packageGroupScope) {
                        $packageGroupScope->selectRaw('1')
                            ->from('exam_questions as package_coverage_exam_questions')
                            ->join('exam_packages as package_coverage_exam_packages', 'package_coverage_exam_packages.exam_id', '=', 'package_coverage_exam_questions.exam_id')
                            ->join('package_groups as coverage_package_groups', 'coverage_package_groups.package_id', '=', 'package_coverage_exam_packages.package_id')
                            ->whereColumn('package_coverage_exam_questions.question_id', 'coverage_questions.id')
                            ->whereColumn('coverage_package_groups.group_id', 'groups.id');
                    });
            })
            ->when($tenantId, fn ($query, $id) => $query->where('coverage_questions.organization_id', $id));

        $examCount = DB::table('exams as coverage_exams')
            ->selectRaw('COUNT(DISTINCT coverage_exams.id)')
            ->where(function ($groupScope) {
                $groupScope
                    ->whereExists(function ($directGroupScope) {
                        $directGroupScope->selectRaw('1')
                            ->from('exam_groups as coverage_direct_exam_groups')
                            ->whereColumn('coverage_direct_exam_groups.exam_id', 'coverage_exams.id')
                            ->whereColumn('coverage_direct_exam_groups.group_id', 'groups.id');
                    })
                    ->orWhereExists(function ($packageGroupScope) {
                        $packageGroupScope->selectRaw('1')
                            ->from('exam_packages as coverage_exam_packages')
                            ->join('package_groups as coverage_exam_package_groups', 'coverage_exam_package_groups.package_id', '=', 'coverage_exam_packages.package_id')
                            ->whereColumn('coverage_exam_packages.exam_id', 'coverage_exams.id')
                            ->whereColumn('coverage_exam_package_groups.group_id', 'groups.id');
                    });
            })
            ->when($tenantId, fn ($query, $id) => $query->where('coverage_exams.organization_id', $id));

        $realStudentIds = Student::query()
            ->select('id')
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($hasDemoStudents, function ($query) {
                $query->where(fn ($students) => $students->whereNull('is_demo')->orWhere('is_demo', false));
            });
        $demoStudentIds = Student::query()
            ->select('id')
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($hasDemoStudents, fn ($query) => $query->where('is_demo', true))
            ->when(! $hasDemoStudents, fn ($query) => $query->whereRaw('1 = 0'));

        return Group::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->when($restrictByGroups, fn ($query) => $query->whereIn('id', $groupIds))
            ->select('groups.*')
            ->selectSub($questionCount, 'questions_count')
            ->selectSub($examCount, 'exams_count')
            ->withCount([
                'packageGroup as packages_count',
                'studentGroups as real_students_count' => fn ($query) => $query->whereIn('student_id', $realStudentIds),
                'studentGroups as demo_students_count' => fn ($query) => $query->whereIn('student_id', $demoStudentIds),
            ])
            ->orderByDesc('exams_count')
            ->orderByDesc('questions_count')
            ->limit(12)
            ->get()
            ->map(fn (Group $group) => [
                'name' => $this->plainLabel($group->group_name, 'Unnamed group'),
                'questions' => (int) $group->questions_count,
                'exams' => (int) $group->exams_count,
                'packages' => (int) $group->packages_count,
                'real_students' => (int) $group->real_students_count,
                'demo_students' => (int) $group->demo_students_count,
                'total_students' => (int) $group->real_students_count + (int) $group->demo_students_count,
            ])
            ->values()
            ->all();
    }

    private function categoryCoverage(?int $tenantId, bool $restrictByGroups, array $groupIds): array
    {
        $questionCount = DB::table('exams as category_exams')
            ->join('exam_questions as category_exam_questions', 'category_exam_questions.exam_id', '=', 'category_exams.id')
            ->selectRaw('COUNT(DISTINCT category_exam_questions.question_id)')
            ->where(function ($categoryScope) use ($tenantId) {
                $categoryScope->whereColumn('category_exams.category_level_1', 'category.id')
                    ->orWhereExists(function ($packageExamScope) use ($tenantId) {
                        $packageExamScope->selectRaw('1')
                            ->from('exam_packages as category_exam_package_links')
                            ->join('packages as category_exam_packages', 'category_exam_packages.id', '=', 'category_exam_package_links.package_id')
                            ->whereColumn('category_exam_package_links.exam_id', 'category_exams.id')
                            ->whereColumn('category_exam_packages.category_level_1', 'category.id')
                            ->when($tenantId, fn ($query, $id) => $query->where('category_exam_packages.organization_id', $id));
                    });
            });

        if ($tenantId) {
            $questionCount->where('category_exams.organization_id', $tenantId);
        }

        if ($restrictByGroups) {
            $questionCount
                ->join('exam_groups as category_exam_groups', 'category_exam_groups.exam_id', '=', 'category_exams.id')
                ->whereIn('category_exam_groups.group_id', $groupIds);
        }

        $examCount = DB::table('exams as category_count_exams')
            ->selectRaw('COUNT(DISTINCT category_count_exams.id)')
            ->where(function ($categoryScope) use ($tenantId) {
                $categoryScope->whereColumn('category_count_exams.category_level_1', 'category.id')
                    ->orWhereExists(function ($packageExamScope) use ($tenantId) {
                        $packageExamScope->selectRaw('1')
                            ->from('exam_packages as category_count_exam_links')
                            ->join('packages as category_count_packages', 'category_count_packages.id', '=', 'category_count_exam_links.package_id')
                            ->whereColumn('category_count_exam_links.exam_id', 'category_count_exams.id')
                            ->whereColumn('category_count_packages.category_level_1', 'category.id')
                            ->when($tenantId, fn ($query, $id) => $query->where('category_count_packages.organization_id', $id));
                    });
            });

        if ($tenantId) {
            $examCount->where('category_count_exams.organization_id', $tenantId);
        }

        if ($restrictByGroups) {
            $examCount
                ->join('exam_groups as category_count_exam_groups', 'category_count_exam_groups.exam_id', '=', 'category_count_exams.id')
                ->whereIn('category_count_exam_groups.group_id', $groupIds);
        }
        $query = Category::query()
            ->parents()
            ->when($tenantId, fn ($builder, $id) => $builder->where('organization_id', $id))
            ->when($restrictByGroups, function ($builder) use ($groupIds) {
                $builder->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
            })
            ->select('category.*')
            ->selectSub($questionCount, 'questions_count')
            ->selectSub($examCount, 'exams_count')
            ->withCount([
                'packages as packages_count' => function ($packages) use ($restrictByGroups, $groupIds) {
                    if ($restrictByGroups) {
                        $packages->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds));
                    }
                },
            ])
            ->orderByDesc('exams_count')
            ->orderByDesc('questions_count')
            ->limit(12);

        return $query->get()
            ->map(fn (Category $category) => [
                'name' => $this->plainLabel($category->title, 'Unnamed category'),
                'questions' => (int) $category->questions_count,
                'exams' => (int) $category->exams_count,
                'packages' => (int) $category->packages_count,
            ])
            ->values()
            ->all();
    }

    private function plainLabel(mixed $value, string $fallback): string
    {
        if (is_array($value)) {
            return (string) (reset($value) ?: $fallback);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return (string) (reset($decoded) ?: $fallback);
            }

            return trim($value) !== '' ? $value : $fallback;
        }

        return $fallback;
    }

    private function activityTrend(?int $tenantId, bool $restrictByGroups, array $groupIds): array
    {
        $startDate = Carbon::today()->subDays(13)->startOfDay();

        $studentTrendQuery = Student::query()
            ->selectRaw('DATE(created_at) as trend_date, COUNT(*) as total')
            ->where('created_at', '>=', $startDate)
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->groupByRaw('DATE(created_at)');

        if ($restrictByGroups) {
            $studentTrendQuery->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('groups.id', $groupIds);
            });
        }

        $attemptTrendQuery = ExamResult::query()
            ->selectRaw('DATE(end_time) as trend_date, COUNT(*) as total')
            ->whereNotNull('end_time')
            ->where('end_time', '>=', $startDate)
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->groupByRaw('DATE(end_time)');

        if ($restrictByGroups) {
            $attemptTrendQuery->whereHas('exam.groups', function ($query) use ($groupIds) {
                $query->whereIn('groups.id', $groupIds);
            });
        }

        $studentTotals = $studentTrendQuery->pluck('total', 'trend_date');
        $attemptTotals = $attemptTrendQuery->pluck('total', 'trend_date');
        $labels = [];
        $students = [];
        $attempts = [];

        for ($day = 0; $day < 14; $day++) {
            $date = $startDate->copy()->addDays($day);
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $students[] = (int) ($studentTotals[$key] ?? 0);
            $attempts[] = (int) ($attemptTotals[$key] ?? 0);
        }

        return compact('labels', 'students', 'attempts');
    }

    private function setupChecklist(?int $tenantId): array
    {
        $config = function_exists('getConfiguration') ? getConfiguration() : null;
        $publicWebsiteEnabled = SaasAccess::featureEnabled('public_website');

        $items = [
            [
                'label' => 'Branding',
                'done' => (bool) ($config?->name || $config?->organization_name || $config?->logo),
                'route' => route('configurations.logo-favicon'),
                'hint' => 'Add logo, favicon, and organization name.',
            ],
            [
                'label' => 'Groups',
                'done' => $this->tenantCount('groups', $tenantId) > 0,
                'route' => route('groups.index'),
                'hint' => 'Create at least one exam group.',
            ],
            [
                'label' => 'Packages',
                'done' => $this->tenantCount('packages', $tenantId) > 0,
                'route' => route('packages.index'),
                'hint' => 'Create a package for students.',
            ],
            [
                'label' => 'Exams',
                'done' => $this->tenantCount('exams', $tenantId) > 0,
                'route' => route('exams.index'),
                'hint' => 'Publish at least one exam.',
            ],
            [
                'label' => 'Questions',
                'done' => $this->tenantCount('questions', $tenantId) > 0,
                'route' => route('questions.index'),
                'hint' => 'Add questions to build usable exams.',
            ],
            [
                'label' => 'Students',
                'done' => $this->tenantCount('students', $tenantId) > 0,
                'route' => route('students.index'),
                'hint' => 'Add or import students.',
            ],
        ];

        if ($publicWebsiteEnabled) {
            $items[] = [
                'label' => 'Homepage',
                'done' => $this->tenantCount('features', $tenantId) > 0,
                'route' => route('homepage-content.index'),
                'hint' => 'Manage homepage benefits, statistics and reviews.',
            ];
        }

        return $items;
    }

    private function tenantCount(string $table, ?int $tenantId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->when($tenantId && Schema::hasColumn($table, 'organization_id'), function ($query) use ($tenantId, $table) {
                $query->where($table . '.organization_id', $tenantId);
            })
            ->count();
    }
}
