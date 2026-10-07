<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ $action }}" class="modal-content">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            @php
                $limits = isset($plan) && is_array($plan->limits) ? $plan->limits : [];
                $features = isset($plan) && is_array($plan->features) ? $plan->features : [];
            @endphp

            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Price</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $plan->price ?? '0.00') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Billing Cycle</label>
                        <select name="billing_cycle" class="form-select" required>
                            @foreach(['monthly', 'yearly', 'lifetime'] as $cycle)
                                <option value="{{ $cycle }}" {{ old('billing_cycle', $plan->billing_cycle ?? 'monthly') === $cycle ? 'selected' : '' }}>
                                    {{ ucfirst($cycle) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="1" {{ old('status', isset($plan) ? (int) $plan->status : 1) == 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('status', isset($plan) ? (int) $plan->status : 1) == 0 ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="is_default" value="0">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="{{ $modalId }}Default" {{ old('is_default', $plan->is_default ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $modalId }}Default">Default plan</label>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-4">
                    <h6 class="mb-3">Plan Limits</h6>
                    <p class="text-muted small mb-3">Leave blank for unlimited.</p>

                    <div class="row g-3">
                        @foreach([
                            'organizations' => 'Organizations',
                            'admins' => 'Admins',
                            'students' => 'Students',
                            'exams' => 'Exams',
                            'packages' => 'Packages',
                            'questions' => 'Questions',
                            'quality_audits_monthly' => 'Quality Audits / Month',
                            'quality_questions_per_audit' => 'Questions / Audit',
                            'quality_source_per_audit' => 'Source Checks / Audit',
                            'quality_ai_per_audit' => 'AI Reviews / Audit',
                            'quality_visual_per_audit' => 'Visual Checks / Audit',
                            'quality_repairs_monthly' => 'AI Repairs / Month',
                        ] as $key => $label)
                            <div class="col-md-4">
                                <label class="form-label">{{ $label }}</label>
                                <input
                                    type="number"
                                    min="0"
                                    name="limit_{{ $key }}"
                                    class="form-control"
                                    value="{{ old('limit_' . $key, $limits[$key] ?? '') }}"
                                    placeholder="Unlimited"
                                >
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border rounded p-3">
                    <h6 class="mb-3">Plan Features</h6>

                    <div class="row g-3">
                        @foreach([
                            'public_website' => 'Public Website',
                            'paid_packages' => 'Paid Packages',
                            'guest_exams' => 'Guest Exams',
                            'ai_generator' => 'AI Question Generator',
                            'ai_translation' => 'AI Translation',
                            'ai_regeneration' => 'AI Question Regeneration',
                            'ai_content_generation' => 'AI Content Generation',
                            'ai_subjective_analysis' => 'AI Subjective Analysis',
                            'ai_student_analysis' => 'AI Student Performance Analysis',
                            'ai_seo' => 'AI SEO',
                            'ai_platform_api' => 'Use Platform AI API',
                            'ai_settings' => 'Organization AI Settings',
                            'email_messaging' => 'Email Messaging',
                            'sms_messaging' => 'SMS Messaging',
                            'reports' => 'Reports',
                            'question_sharing' => 'Question Sharing',
                            'flashcards' => 'Study Cards',
                            'ai_flashcard_generation' => 'AI Study Card Generation',
                            'exam_quality_audit' => 'Exam Quality Audit (Rule Bot)',
                            'exam_quality_source' => 'Audit Source Comparison',
                            'exam_quality_visual' => 'Audit Browser Visual Bot',
                            'exam_quality_ai' => 'Audit AI Review & Repair',
                            'custom_theme' => 'Custom Theme',
                            'student_self_registration' => 'Student Self Registration',
                        ] as $key => $label)
                            <div class="col-md-4">
                                <input type="hidden" name="feature_{{ $key }}" value="0">
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="feature_{{ $key }}"
                                        value="1"
                                        id="{{ $modalId }}{{ str_replace('_', '', $key) }}"
                                        {{ old('feature_' . $key, array_key_exists($key, $features) ? $features[$key] : !str_starts_with($key, 'exam_quality_')) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="{{ $modalId }}{{ str_replace('_', '', $key) }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn el-btn-primary">Save Plan</button>
            </div>
        </form>
    </div>
</div>
