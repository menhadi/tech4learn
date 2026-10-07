<style>
    .student-type-tabs {
        align-items: center;
        background: color-mix(in srgb, var(--vz-primary) 6%, var(--vz-card-bg));
        border: 1px solid color-mix(in srgb, var(--vz-primary) 16%, var(--vz-border-color));
        border-radius: 12px;
        display: inline-flex;
        gap: 6px;
        padding: 5px;
    }
    .student-type-tabs .student-type-tab {
        align-items: center;
        border-radius: 8px;
        color: var(--vz-secondary-color);
        display: inline-flex;
        font-weight: 700;
        gap: 7px;
        padding: 9px 16px;
        text-decoration: none;
        transition: background-color .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .student-type-tabs .student-type-tab:hover {
        background: color-mix(in srgb, var(--vz-primary) 10%, var(--vz-card-bg));
        color: var(--vz-primary);
    }
    .student-type-tabs .student-type-tab.active {
        background: var(--vz-primary);
        box-shadow: 0 7px 16px rgba(var(--vz-primary-rgb), .18);
        color: #ffffff;
    }
    @media (max-width: 575.98px) {
        .student-type-tabs { display: flex; width: 100%; }
        .student-type-tabs .student-type-tab { flex: 1 1 0; justify-content: center; padding-inline: 10px; }
    }
</style>

@if(\App\Support\SaasAccess::isPlatformAdmin() && \Illuminate\Support\Facades\Route::has('demo-students.index'))
<div class="student-type-tabs mb-3" role="navigation" aria-label="Student type">
    <a href="{{ route('students.index') }}" class="student-type-tab {{ request()->routeIs('students.*') ? 'active' : '' }}">
        <i class="ri-user-line"></i> Real Students
    </a>
        <a href="{{ route('demo-students.index') }}" class="student-type-tab {{ request()->routeIs('demo-students.*') ? 'active' : '' }}">
            <i class="ri-robot-2-line"></i> Demo Students
        </a>
</div>
@endif
