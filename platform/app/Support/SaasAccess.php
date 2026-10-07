<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SaasAccess
{
    private const PLAN_FEATURES = [
        'public_website',
        'student_self_registration',
        'guest_exams',
        'custom_theme',
        'ai_settings',
        'ai_generator',
        'ai_translation',
        'ai_content_generation',
        'ai_subjective_analysis',
        'ai_student_analysis',
        'ai_regeneration',
        'ai_seo',
        'email_messaging',
        'sms_messaging',
        'reports',
        'question_sharing',
        'paid_packages',
        'flashcards',
        'ai_flashcard_generation',
        'exam_quality_audit',
        'exam_quality_source',
        'exam_quality_visual',
        'exam_quality_ai',
    ];

    public static function organization(): ?Organization
    {
        if (! class_exists(Tenant::class)) {
            return null;
        }

        $hostId = Tenant::hostId(request()->getHost());

        return Organization::with('plan')->find($hostId ?: Tenant::id());
    }

    public static function isPlatformOrganization(?Organization $organization = null): bool
    {
        $organization = $organization ?: self::organization();

        return (bool) ($organization && $organization->slug === 'examelite');
    }

    public static function featureEnabled(string $feature, ?Organization $organization = null): bool
    {
        $organization = $organization ?: self::organization();

        if (! $organization) {
            return true;
        }

        if (self::isPlatformOrganization($organization) && in_array($feature, ['public_website', 'student_self_registration'], true)) {
            return true;
        }

        if (self::isPlatformOrganization($organization) && self::isPlatformAdmin()) {
            return true;
        }

        if ($organization->status !== 'active') {
            return false;
        }

        if ($organization->subscription_ends_at && $organization->subscription_ends_at->isPast()) {
            return false;
        }

        $features = $organization->plan?->features;

        if (! is_array($features)) {
            return ! in_array($feature, self::PLAN_FEATURES, true);
        }

        if (! array_key_exists($feature, $features)) {
            return ! in_array($feature, self::PLAN_FEATURES, true);
        }

        return (bool) $features[$feature];
    }

    public static function limit(string $resource, ?Organization $organization = null): ?int
    {
        $organization = $organization ?: self::organization();
        $limit = $organization?->plan?->limits[$resource] ?? null;

        return $limit === null || $limit === '' ? null : (int) $limit;
    }

    public static function usage(string $resource, ?Organization $organization = null): int
    {
        $organization = $organization ?: self::organization();

        if (! $organization) {
            return 0;
        }

        $table = [
            'admins' => 'organization_users',
            'students' => 'students',
            'exams' => 'exams',
            'packages' => 'packages',
            'questions' => 'questions',
        ][$resource] ?? null;

        if ($resource === 'quality_audits_monthly') {
            return Schema::hasTable('exam_quality_audits')
                ? DB::table('exam_quality_audits')->where('organization_id', $organization->id)->where('created_at', '>=', now()->startOfMonth())
                    ->where(fn ($query) => $query->whereNull('options->manual_editor')->orWhere('options->manual_editor', false))->count()
                : 0;
        }

        if ($resource === 'quality_repairs_monthly') {
            return Schema::hasTable('question_repair_drafts')
                ? DB::table('question_repair_drafts as drafts')->join('exam_quality_audits as audits', 'audits.id', '=', 'drafts.audit_id')
                    ->where('drafts.organization_id', $organization->id)->where('drafts.created_at', '>=', now()->startOfMonth())
                    ->where(fn ($query) => $query->whereNull('audits.options->manual_editor')->orWhere('audits.options->manual_editor', false))->count()
                : 0;
        }

        if (! $table || ! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if ($resource === 'admins') {
            return $query
                ->where('organization_id', $organization->id)
                ->whereIn('role', ['owner', 'admin', 'staff'])
                ->where('status', 1)
                ->count();
        }

        if (Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organization->id);
        }

        return $query->count();
    }

    public static function allowsMore(string $resource, ?Organization $organization = null): bool
    {
        $organization = $organization ?: self::organization();

        if (self::isPlatformOrganization($organization) && self::isPlatformAdmin()) {
            return true;
        }

        $limit = self::limit($resource, $organization);

        return $limit === null || self::usage($resource, $organization) < $limit;
    }

    public static function abortIfFeatureDisabled(string $feature): void
    {
        if (! self::featureEnabled($feature)) {
            abort(403, 'This feature is not enabled for your organization plan.');
        }
    }

    public static function featureForRoute(?string $routeName): ?string
    {
        $routeName = (string) $routeName;

        foreach ([
            'public_website' => [
                'heroslider',
                'features',
                'counters',
                'testimonial',
                'aboutus',
                'websitepages',
                'configurations.website',
                'website.title',
            ],
            'ai_settings' => ['configurations.ai'],
            'ai_generator' => ['ai.generator'],
            'ai_content_generation' => ['admin.ai-content', 'ai.content'],
            'ai_seo' => ['admin.seo'],
            'email_messaging' => ['email-templates', 'email-settings', 'send-email', 'students.email'],
            'sms_messaging' => ['sms-templates'],
            'reports' => ['results', 'exams.reports', 'exams.studyCardReports', 'sales-reports'],
            'question_sharing' => ['saas.question-sharing'],
            'paid_packages' => ['orders', 'transactions', 'coupons'],
            'flashcards' => ['flashcards', 'student.flashcards'],
            'exam_quality_audit' => ['exam-quality'],
        ] as $feature => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (Str::startsWith($routeName, $prefix)) {
                    return $feature;
                }
            }
        }

        return null;
    }

    public static function defaultWebsiteUrl(): string
    {
        $configuredUrl = rtrim((string) config('app.url'), '/');

        if ($configuredUrl) {
            return $configuredUrl;
        }

        $domain = Schema::hasTable('organizations')
            ? DB::table('organizations')->where('slug', 'examelite')->value('domain')
            : null;

        return $domain ? 'https://' . $domain : url('/');
    }

    public static function abortIfLimitReached(string $resource, ?Organization $organization = null): void
    {
        if (! self::allowsMore($resource, $organization)) {
            abort(403, 'Your organization plan limit for ' . str_replace('_', ' ', $resource) . ' has been reached.');
        }
    }

    public static function isPlatformOwner(): bool
    {
        return self::isPlatformAdmin();
    }

    public static function isPlatformAdmin(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_platform_admin')) {
            if ((bool) $user->is_platform_admin) {
                return true;
            }

            if (DB::table('users')->where('is_platform_admin', true)->exists()) {
                return false;
            }
        }

        return self::isLegacyPlatformOwner((int) $user->id);
    }

    private static function isLegacyPlatformOwner(int $userId): bool
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('organization_users')) {
            return false;
        }

        $platformOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id');

        return (bool) ($platformOrganizationId && DB::table('organization_users')
            ->where('organization_id', $platformOrganizationId)
            ->where('user_id', $userId)
            ->where('role', 'owner')
            ->where('status', 1)
            ->exists());
    }
}
