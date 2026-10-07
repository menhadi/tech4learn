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
            <textarea name="message" id="message" placeholder="Example: scholarship test, school exams, mock tests, study cards, NGO training program...">{{ old('message') }}</textarea>
            @error('message')<div class="saas-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="saas-actions">
        <button type="submit" class="saas-btn saas-btn-primary"><i class="ri-send-plane-line"></i> {{ __('ui.submit_request') }}</button>
        <a href="{{ route('courses.index') }}" class="saas-btn saas-btn-outline">{{ __('ui.explore_first') }}</a>
    </div>
</form>
