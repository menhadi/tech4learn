@extends('website.layouts.app')

@section('title', $saasPageTitle ?? 'ExamElite for Institutes')
@section('meta_description', $seo['description'] ?? 'Launch your own branded online exam platform for NGOs, schools, coaching institutes and training organizations with ExamElite.')

@section('content')
<style>
    .saas-page {
        --saas-primary: var(--theme-primary, #0f766e);
        --saas-secondary: var(--theme-secondary, #f59e0b);
        --saas-bg: var(--theme-body-bg, #f7faf9);
        --saas-card: var(--theme-card-bg, #ffffff);
        --saas-heading: var(--theme-heading, #0f172a);
        --saas-text: var(--theme-text, #64748b);
        --saas-border: color-mix(in srgb, var(--saas-primary) 22%, #ffffff);
        background: var(--saas-bg);
        color: var(--saas-heading);
        font-family: inherit;
        letter-spacing: 0;
    }

    .saas-wrap {
        width: min(1180px, calc(100% - 32px));
        margin: 0 auto;
    }

    .saas-hero {
        padding: 72px 0 48px;
        border-bottom: 1px solid var(--saas-border);
        background: linear-gradient(180deg, color-mix(in srgb, var(--saas-primary) 8%, #ffffff), var(--saas-bg));
    }

    .saas-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
        gap: 34px;
        align-items: center;
    }

    .saas-kicker,
    .saas-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: color-mix(in srgb, var(--saas-primary) 12%, #ffffff);
        border: 1px solid var(--saas-border);
        border-radius: 999px;
        color: var(--saas-primary);
        font-weight: 800;
        padding: 8px 14px;
        line-height: 1;
    }

    .saas-hero h1 {
        margin: 22px 0 16px;
        font-size: clamp(34px, 5vw, 62px);
        line-height: 1.05;
        letter-spacing: 0;
        color: var(--saas-heading);
    }

    .saas-lead {
        max-width: 760px;
        color: var(--saas-text);
        font-size: clamp(18px, 2vw, 22px);
        line-height: 1.65;
        margin: 0;
    }

    .saas-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 28px;
    }

    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 48px;
        border-radius: 999px;
        padding: 12px 22px;
        font-weight: 800;
        text-decoration: none;
        border: 1px solid transparent;
        transition: background .2s ease, border-color .2s ease, color .2s ease, transform .2s ease;
    }

    .saas-btn:hover,
    .saas-btn:focus {
        text-decoration: none;
        transform: translateY(-1px);
    }

    .saas-btn-primary {
        background: var(--saas-primary);
        border-color: var(--saas-primary);
        color: #ffffff;
    }

    .saas-btn-primary:hover,
    .saas-btn-primary:focus {
        background: color-mix(in srgb, var(--saas-primary) 88%, #000000);
        border-color: color-mix(in srgb, var(--saas-primary) 88%, #000000);
        color: #ffffff;
    }

    .saas-btn-secondary {
        background: var(--saas-secondary);
        border-color: var(--saas-secondary);
        color: #ffffff;
    }

    .saas-btn-secondary:hover,
    .saas-btn-secondary:focus {
        background: color-mix(in srgb, var(--saas-secondary) 88%, #000000);
        border-color: color-mix(in srgb, var(--saas-secondary) 88%, #000000);
        color: #ffffff;
    }

    .saas-btn-outline {
        background: #ffffff;
        border-color: var(--saas-primary);
        color: var(--saas-primary);
    }

    .saas-btn-outline:hover,
    .saas-btn-outline:focus {
        background: color-mix(in srgb, var(--saas-primary) 10%, #ffffff);
        color: var(--saas-primary);
    }

    .saas-hero-card,
    .saas-card,
    .saas-panel {
        background: var(--saas-card);
        border: 1px solid var(--saas-border);
        border-radius: 18px;
        box-shadow: 0 18px 48px rgba(15, 23, 42, .07);
    }

    .saas-hero-card {
        padding: 26px;
    }

    .saas-hero-card h2 {
        margin: 12px 0 10px;
        color: var(--saas-heading);
        font-size: 28px;
    }

    .saas-check-list {
        display: grid;
        gap: 12px;
        padding: 0;
        margin: 20px 0 0;
        list-style: none;
    }

    .saas-check-list li {
        display: flex;
        gap: 10px;
        color: var(--saas-text);
        line-height: 1.5;
    }

    .saas-check-list i {
        color: var(--saas-primary);
        font-size: 20px;
        flex: 0 0 auto;
    }

    .saas-section {
        padding: 54px 0;
    }

    .saas-section-head {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: end;
        margin-bottom: 22px;
    }

    .saas-section h2 {
        font-size: clamp(28px, 3vw, 42px);
        margin: 0;
        color: var(--saas-heading);
    }

    .saas-section-head p,
    .saas-muted {
        color: var(--saas-text);
        margin: 8px 0 0;
        line-height: 1.6;
    }

    .saas-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .saas-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .saas-card {
        padding: 24px;
    }

    .saas-feature-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: var(--saas-primary);
        color: #ffffff;
        font-size: 26px;
        margin-bottom: 16px;
    }

    .saas-card h3,
    .saas-plan h3 {
        margin: 0 0 10px;
        color: var(--saas-heading);
        font-size: 22px;
        line-height: 1.25;
    }

    .saas-card p,
    .saas-plan p {
        color: var(--saas-text);
        line-height: 1.6;
        margin: 0;
    }

    .saas-plan {
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 26px;
        overflow: hidden;
    }

    .saas-plan.featured {
        border-color: var(--saas-secondary);
        box-shadow: 0 22px 56px rgba(15, 23, 42, .11);
    }

    .saas-plan-badge {
        width: max-content;
        margin-bottom: 16px;
        border-radius: 999px;
        padding: 7px 12px;
        font-weight: 800;
        background: color-mix(in srgb, var(--saas-secondary) 16%, #ffffff);
        color: color-mix(in srgb, var(--saas-secondary) 76%, #000000);
    }

    .saas-price {
        color: var(--saas-primary);
        font-size: 34px;
        font-weight: 900;
        margin: 14px 0 6px;
    }

    .saas-price span {
        color: var(--saas-text);
        font-size: 16px;
        font-weight: 700;
    }

    .saas-plan .saas-btn {
        width: 100%;
        margin-top: auto;
    }

    .saas-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--saas-border);
        border-radius: 16px;
        background: #ffffff;
    }


    .saas-editable-content {
        font-size: 18px;
        line-height: 1.75;
        color: var(--saas-text);
    }

    .saas-editable-content h1,
    .saas-editable-content h2,
    .saas-editable-content h3,
    .saas-editable-content h4 {
        color: var(--saas-heading);
        margin: 28px 0 12px;
        line-height: 1.2;
    }

    .saas-editable-content h1 { font-size: clamp(34px, 4vw, 54px); }
    .saas-editable-content h2 { font-size: clamp(28px, 3vw, 40px); }
    .saas-editable-content h3 { font-size: clamp(22px, 2.2vw, 30px); }
    .saas-editable-content p,
    .saas-editable-content ul,
    .saas-editable-content ol { margin-bottom: 18px; }
    .saas-editable-content ul,
    .saas-editable-content ol { padding-left: 24px; }
    .saas-editable-content li { margin-bottom: 10px; }
    .saas-editable-content a { color: var(--saas-primary); font-weight: 800; }

    .saas-editable-section {
        margin: 0;
        padding: 0;
        background: transparent;
    }

    .saas-editable-section .saas-editable-content {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
    }

    .saas-editable-section .saas-editable-content > :first-child {
        margin-top: 0;
    }

    .saas-table {
        width: 100%;
        min-width: 780px;
        border-collapse: collapse;
    }

    .saas-table th,
    .saas-table td {
        padding: 16px 18px;
        border-bottom: 1px solid var(--saas-border);
        text-align: left;
        vertical-align: top;
    }

    .saas-table th {
        background: color-mix(in srgb, var(--saas-primary) 11%, #ffffff);
        color: var(--saas-heading);
        font-weight: 900;
    }

    .saas-table td {
        color: var(--saas-text);
    }

    .saas-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .saas-field label {
        display: block;
        margin-bottom: 8px;
        font-weight: 800;
        color: var(--saas-heading);
    }

    .saas-field input,
    .saas-field select,
    .saas-field textarea {
        width: 100%;
        min-height: 50px;
        border: 1px solid var(--saas-border);
        border-radius: 12px;
        background: #ffffff;
        color: var(--saas-heading);
        font-family: inherit;
        padding: 12px 14px;
        outline: none;
    }

    .saas-field textarea {
        min-height: 120px;
        resize: vertical;
    }

    .saas-field input:focus,
    .saas-field select:focus,
    .saas-field textarea:focus {
        border-color: var(--saas-primary);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--saas-primary) 14%, transparent);
    }

    .saas-field-full {
        grid-column: 1 / -1;
    }

    .saas-alert {
        margin-bottom: 18px;
        padding: 14px 16px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--saas-primary) 12%, #ffffff);
        border: 1px solid var(--saas-border);
        color: var(--saas-primary);
        font-weight: 800;
    }

    .saas-error {
        color: #b91c1c;
        font-size: 14px;
        margin-top: 6px;
    }

    @media (max-width: 991px) {
        .saas-hero-grid,
        .saas-grid-3,
        .saas-grid-4 {
            grid-template-columns: 1fr;
        }

        .saas-section-head {
            display: block;
        }
    }

    @media (max-width: 640px) {
        .saas-wrap {
            width: min(100% - 20px, 1180px);
        }

        .saas-hero {
            padding: 42px 0 34px;
        }

        .saas-actions,
        .saas-form-grid {
            grid-template-columns: 1fr;
        }

        .saas-actions .saas-btn {
            width: 100%;
        }

        .saas-card,
        .saas-plan,
        .saas-hero-card {
            padding: 20px;
        }
    }
</style>

<main class="saas-page">
    @if(!empty($hasEditablePageBody))
        <section class="saas-editable-section">
            <div class="saas-editable-content">
                {!! $saasPageBody !!}
            </div>
        </section>
    @else
    <section class="saas-hero">
        <div class="saas-wrap saas-hero-grid">
            <div>
                <span class="saas-kicker"><i class="ri-building-4-line"></i> {{ __('ui.for_institutes') }}</span>
                <h1>{{ $saasPageTitle ?? 'Launch your own online exam platform with ExamElite' }}</h1>
                <div class="saas-lead">
                    @if(!empty($saasPageIntro))
                        {!! $saasPageIntro !!}
                    @else
                        <p>{{ __('ui.institute_intro') }}</p>
                    @endif
                </div>
                <div class="saas-actions">
                    <a href="#plans" class="saas-btn saas-btn-primary"><i class="ri-price-tag-3-line"></i> {{ __('ui.view_plans') }}</a>
                    <a href="#request-demo" class="saas-btn saas-btn-secondary"><i class="ri-message-3-line"></i> {{ __('ui.request_demo') }}</a>
                </div>
            </div>

            <aside class="saas-hero-card">
                <span class="saas-pill"><i class="ri-shield-check-line"></i> {{ __('ui.nontechnical_teams') }}</span>
                <h2>{{ __('ui.institute_gets') }}</h2>
                <ul class="saas-check-list">
                    <li><i class="ri-check-line"></i><span>{{ __('ui.institute_benefit_brand') }}</span></li>
                    <li><i class="ri-check-line"></i><span>{{ __('ui.institute_benefit_tools') }}</span></li>
                    <li><i class="ri-check-line"></i><span>{{ __('ui.institute_benefit_controls') }}</span></li>
                </ul>
            </aside>
        </div>
    </section>

    <section class="saas-section">
        <div class="saas-wrap">
            <div class="saas-section-head">
                <div>
                    <span class="saas-kicker"><i class="ri-compass-3-line"></i> {{ __('ui.clear_use_cases') }}</span>
                    <h2>{{ __('ui.simple_exam_delivery') }}</h2>
                </div>
                <p>{{ __('ui.use_cases_copy') }}</p>
            </div>
            <div class="saas-grid-4">
                <div class="saas-card"><div class="saas-feature-icon"><i class="ri-hand-heart-line"></i></div><h3>{{ __('ui.ngos') }}</h3><p>{{ __('ui.ngo_plan_copy') }}</p></div>
                <div class="saas-card"><div class="saas-feature-icon"><i class="ri-school-line"></i></div><h3>{{ __('ui.schools') }}</h3><p>{{ __('ui.school_plan_copy') }}</p></div>
                <div class="saas-card"><div class="saas-feature-icon"><i class="ri-graduation-cap-line"></i></div><h3>{{ __('ui.coaching') }}</h3><p>{{ __('ui.coaching_plan_copy') }}</p></div>
                <div class="saas-card"><div class="saas-feature-icon"><i class="ri-team-line"></i></div><h3>{{ __('ui.training_teams') }}</h3><p>{{ __('ui.training_plan_copy') }}</p></div>
            </div>
        </div>
    </section>

    <section class="saas-section" id="plans">
        <div class="saas-wrap">
            <div class="saas-section-head">
                <div>
                    <span class="saas-kicker"><i class="ri-stack-line"></i> {{ __('ui.plans') }}</span>
                    <h2>{{ __('ui.choose_institute_plan') }}</h2>
                </div>
                <p>{{ __('ui.plans_copy') }}</p>
            </div>

            <div class="saas-grid-3">
                @foreach(($landingPlans ?? []) as $index => $plan)
                    <article class="saas-card saas-plan {{ $index === 1 ? 'featured' : '' }}">
                        <span class="saas-plan-badge">{{ $plan['badge'] ?? $plan['name'] ?? 'Plan' }}</span>
                        <h3>{{ $plan['name'] ?? 'Institute Plan' }}</h3>
                        <p>{{ $plan['subtitle'] ?? 'For institutes that want a clear online exam workflow.' }}</p>
                        <div class="saas-price">{{ $plan['price'] ?? 'Contact us' }} <span>/ {{ $plan['period'] ?? 'plan' }}</span></div>
                        <ul class="saas-check-list">
                            @foreach(($plan['features'] ?? []) as $feature)
                                <li><i class="ri-check-line"></i><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>
                        <a href="#request-demo" class="saas-btn {{ $index === 1 ? 'saas-btn-primary' : ($index === 2 ? 'saas-btn-secondary' : 'saas-btn-outline') }}">{{ $plan['cta'] ?? ('Ask About ' . ($plan['name'] ?? 'This Plan')) }}</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="saas-section">
        <div class="saas-wrap">
            <div class="saas-section-head">
                <div>
                    <span class="saas-kicker"><i class="ri-list-check-3"></i> {{ __('ui.feature_summary') }}</span>
                    <h2>{{ __('ui.enabled_institute') }}</h2>
                </div>
            </div>
            <div class="saas-table-wrap">
                <table class="saas-table">
                    <thead>
                        <tr><th>{{ __('ui.feature') }}</th><th>{{ __('website.course_free') }}</th><th>{{ __('ui.growth') }}</th><th>{{ __('ui.professional') }}</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>{{ __('ui.branded_website_dashboard') }}</td><td>{{ __('ui.included') }}</td><td>{{ __('ui.included') }}</td><td>{{ __('ui.included') }}</td></tr>
                        <tr><td>{{ __('ui.online_exams_reports') }}</td><td>{{ __('ui.basic') }}</td><td>{{ __('ui.advanced') }}</td><td>{{ __('ui.advanced_priority') }}</td></tr>
                        <tr><td>{{ __('ui.study_cards_support') }}</td><td>{{ __('ui.included') }}</td><td>{{ __('ui.included') }}</td><td>{{ __('ui.included') }}</td></tr>
                        <tr><td>{{ __('ui.paid_checkout_flow') }}</td><td>{{ __('ui.not_included') }}</td><td>{{ __('ui.optional') }}</td><td>{{ __('ui.optional') }}</td></tr>
                        <tr><td>{{ __('ui.ai_tools') }}</td><td>{{ __('ui.not_included') }}</td><td>{{ __('ui.optional_addon') }}</td><td>{{ __('ui.optional_custom') }}</td></tr>
                        <tr><td>{{ __('ui.student_limit') }}</td><td>{{ __('ui.less_than_50') }}</td><td>{{ __('ui.as_per_plan') }}</td><td>{{ __('ui.custom_scale') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="saas-section" id="request-demo">
        <div class="saas-wrap">
            <div class="saas-panel saas-card">
                <div class="saas-section-head">
                    <div>
                        <span class="saas-kicker"><i class="ri-send-plane-line"></i> {{ __('ui.get_started') }}</span>
                        <h2>{{ __('ui.tell_institute') }}</h2>
                    </div>
                    <p>{{ __('ui.institute_help_copy') }}</p>
                </div>

                @if(session('saas_lead_success'))
                    <div class="saas-alert">{{ session('saas_lead_success') }}</div>
                @endif

                <form action="{{ route('website.forInstitutes.store') }}" method="POST">
                    @csrf
                    <div class="saas-form-grid">
                        <div class="saas-field">
                            <label for="preferred_plan">{{ __('ui.interested_plan') }}</label>
                            <select name="preferred_plan" id="preferred_plan">
                                <option value="">{{ __('ui.help_decide') }}</option>
                                @foreach(($landingPlans ?? []) as $plan)
                                    <option value="{{ $plan['name'] ?? '' }}" @selected(old('preferred_plan') === ($plan['name'] ?? ''))>{{ $plan['name'] ?? 'Institute Plan' }}</option>
                                @endforeach
                            </select>
                            @error('preferred_plan')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="institute_type">{{ __('ui.institute_type') }}</label>
                            <select name="institute_type" id="institute_type">
                                <option value="">{{ __('ui.select_type') }}</option>
                                @foreach(['NGO','School','Coaching institute','College','Training organization','Other'] as $type)
                                    <option value="{{ $type }}" @selected(old('institute_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('institute_type')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="institute_name">{{ __('ui.institute_name') }}</label>
                            <input type="text" name="institute_name" id="institute_name" value="{{ old('institute_name') }}" required>
                            @error('institute_name')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="contact_name">{{ __('ui.your_name') }}</label>
                            <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name') }}" required>
                            @error('contact_name')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="email">{{ __('ui.email') }}</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required>
                            @error('email')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="phone">{{ __('website.checkout_phone') }}</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}">
                            @error('phone')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field">
                            <label for="expected_students">{{ __('ui.expected_students') }}</label>
                            <input type="number" name="expected_students" id="expected_students" min="0" value="{{ old('expected_students') }}" placeholder="Example: 45">
                            @error('expected_students')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="saas-field saas-field-full">
                            <label for="message">{{ __('ui.what_run') }}</label>
                            <textarea name="message" id="message" placeholder="Example: scholarship test, school exams, mock tests, NGO training program...">{{ old('message') }}</textarea>
                            @error('message')<div class="saas-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="saas-actions">
                        <button type="submit" class="saas-btn saas-btn-primary"><i class="ri-send-plane-line"></i> {{ __('ui.submit_request') }}</button>
                        <a href="{{ route('courses.index') }}" class="saas-btn saas-btn-outline">{{ __('ui.explore_first') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
    @endif
</main>
@endsection
