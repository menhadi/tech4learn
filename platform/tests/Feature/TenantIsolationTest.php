<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\EmailSetting;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Group;
use App\Models\Organization;
use App\Models\SaasPlan;
use App\Models\Student;
use App\Services\StudentWelcomeEmailService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://platform.test']);
        Cache::flush();
        Tenant::clear();
    }

    public function test_unknown_host_does_not_fall_back_to_an_active_tenant(): void
    {
        $this->organization('Tenant A', 'tenant-a.test');

        $this->get('https://unknown.test/robots.txt')
            ->assertNotFound();
    }

    public function test_disabled_student_password_does_not_open_a_web_session(): void
    {
        $tenant=$this->organization('Tenant A','tenant-a.test');
        $student=$this->student($tenant,'disabled@example.test','9000000091');
        $student->update(['status'=>'Inactive']);
        $this->from('https://tenant-a.test/student/signin')->post('https://tenant-a.test/student/signin',[
            'login'=>$student->email,'password'=>'password123',
        ])->assertRedirect('https://tenant-a.test/student/signin')->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_disabled_student_existing_web_and_api_sessions_are_rejected(): void
    {
        $tenant=$this->organization('Tenant A','tenant-a.test');
        $student=$this->student($tenant,'revoked@example.test','9000000092');
        $this->actingAs($student,'student');
        Student::whereKey($student->id)->update(['status'=>'Inactive']);
        $this->get('https://tenant-a.test/student/my-exams')->assertForbidden();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        Sanctum::actingAs($student, [], 'student-api');
        $this->getJson('https://tenant-a.test/api/student/me')->assertForbidden();
    }

    public function test_mobile_signup_is_scoped_to_the_host_tenant_and_its_groups(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        $groupA = Group::create(['organization_id' => $tenantA->id, 'group_name' => 'Tenant A Group']);
        $groupB = Group::create(['organization_id' => $tenantB->id, 'group_name' => 'Tenant B Group']);
        $this->mock(StudentWelcomeEmailService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOnce')->once()->andReturnTrue();
        });


        $this->postJson('https://tenant-a.test/api/student/signup', [
                'name' => 'Tenant A Student',
                'email' => 'shared@example.test',
                'phone' => '9000000001',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('student.organization_id', $tenantA->id);

        $student = Student::where('email', 'shared@example.test')->firstOrFail();
        $this->assertSame([$groupA->id], $student->groups()->pluck('groups.id')->all());
        $this->assertFalse($student->groups()->whereKey($groupB->id)->exists());
    }

    public function test_mobile_login_cannot_authenticate_a_student_from_another_tenant(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        $student = $this->student($tenantB, 'student@example.test', '9000000002');

        $this->postJson('https://tenant-a.test/api/student/signin', [
                'login' => $student->email,
                'password' => 'password123',
            ])
            ->assertUnauthorized();

        $this->assertDatabaseHas('organizations', ['id' => $tenantA->id]);
    }

    public function test_student_token_cannot_be_reused_on_another_tenant_host(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $this->organization('Tenant B', 'tenant-b.test');
        $student = $this->student($tenantA, 'student@example.test', '9000000003');
        Sanctum::actingAs($student, [], 'student-api');

        $this->getJson('https://tenant-b.test/api/student/me')
            ->assertNotFound();
    }

    public function test_exam_details_reject_cross_tenant_ids_and_require_entitlement(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        $groupA = Group::create(['organization_id' => $tenantA->id, 'group_name' => 'Tenant A Group']);
        $groupB = Group::create(['organization_id' => $tenantB->id, 'group_name' => 'Tenant B Group']);
        $student = $this->student($tenantA, 'student@example.test', '9000000004');
        $student->groups()->attach($groupA->id);
        $examA = $this->exam($tenantA, 'Tenant A Exam');
        $examB = $this->exam($tenantB, 'Tenant B Exam');
        $examA->groups()->attach($groupA->id);
        $examB->groups()->attach($groupB->id);
        Sanctum::actingAs($student, [], 'student-api');

        $this->getJson('https://tenant-a.test/api/student/exam-details/'.$examB->id)
            ->assertNotFound();

        $this->getJson('https://tenant-a.test/api/student/exam-details/'.$examA->id)
            ->assertOk()
            ->assertJsonPath('exam.id', $examA->id);
    }

    public function test_mobile_dashboard_and_results_ignore_cross_tenant_rows(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        $student = $this->student($tenantA, 'dashboard@example.test', '9000000005');
        $examA = $this->exam($tenantA, 'Tenant A Result');
        $examB = $this->exam($tenantB, 'Tenant B Result');

        $visibleResult = ExamResult::create([
            'organization_id' => $tenantA->id,
            'exam_id' => $examA->id,
            'student_id' => $student->id,
            'start_time' => now()->subMinutes(30),
            'end_time' => now(),
            'total_test_time' => 1800,
            'total_question' => 10,
            'obtained_marks' => 80,
            'result' => 'Pass',
            'percent' => 80,
        ]);
        ExamResult::create([
            'organization_id' => $tenantB->id,
            'exam_id' => $examB->id,
            'student_id' => $student->id,
            'start_time' => now()->subMinutes(20),
            'end_time' => now(),
            'total_test_time' => 1800,
            'total_question' => 10,
            'obtained_marks' => 10,
            'result' => 'Fail',
            'percent' => 10,
        ]);
        Sanctum::actingAs($student, [], 'student-api');

        $this->getJson('https://tenant-a.test/api/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totalExams', 1)
            ->assertJsonPath('data.bestResultCount', 1);

        $this->getJson('https://tenant-a.test/api/student/results')
            ->assertOk()
            ->assertJsonPath('stats.total_attempts', 1)
            ->assertJsonPath('stats.passed_count', 1)
            ->assertJsonPath('stats.failed_count', 0)
            ->assertJsonPath('stats.highest_score', 80)
            ->assertJsonCount(1, 'results.data')
            ->assertJsonPath('results.data.0.id', $visibleResult->id);
    }

    public function test_public_storage_route_cannot_escape_its_root(): void
    {
        $this->organization('Tenant A', 'tenant-a.test');
        $secret = storage_path('app/tenant-isolation-secret.txt');
        file_put_contents($secret, 'not public');

        try {
            $this->get('https://tenant-a.test/storage/%2e%2e/tenant-isolation-secret.txt')
                ->assertNotFound();
        } finally {
            @unlink($secret);
        }
    }

    public function test_request_runtime_uses_only_the_resolved_tenant_configuration(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        Configuration::create(['organization_id' => $tenantA->id, 'timezone' => 'Asia/Kolkata']);
        Configuration::create(['organization_id' => $tenantB->id, 'timezone' => 'UTC']);
        EmailSetting::create([
            'organization_id' => $tenantB->id,
            'type' => 'smtp',
            'host' => 'tenant-b-smtp.example.test',
            'username' => 'tenant-b@example.test',
            'password' => 'secret',
            'port' => 587,
            'tls' => true,
        ]);
        $originalMailHost = config('mail.mailers.smtp.host');

        $this->get('https://tenant-a.test/robots.txt')
            ->assertOk();

        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame($originalMailHost, config('mail.mailers.smtp.host'));
    }

    private function organization(string $name, string $domain): Organization
    {
        $plan = SaasPlan::firstOrCreate(
            ['slug' => 'tenant-test-plan'],
            [
                'name' => 'Tenant Test Plan',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'features' => ['public_website' => true, 'student_self_registration' => true],
                'limits' => [],
                'status' => true,
            ]
        );

        return Organization::create([
            'saas_plan_id' => $plan->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'domain' => $domain,
            'status' => 'active',
        ]);
    }

    private function student(Organization $organization, string $email, string $phone): Student
    {
        return Student::create([
            'organization_id' => $organization->id,
            'name' => 'Test Student',
            'email' => $email,
            'password' => Hash::make('password123'),
            'address' => 'Test Address',
            'phone' => $phone,
            'status' => 'Active',
        ]);
    }

    private function exam(Organization $organization, string $name): Exam
    {
        return Exam::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'passing_percentage' => 50,
            'duration' => 60,
            'attempt_count' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'mode' => 'Exam',
            'status' => 'Active',
        ]);
    }
}
