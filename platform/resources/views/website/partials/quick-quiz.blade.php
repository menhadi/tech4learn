@php
    $quizPageMode = (bool) ($quickQuizPageMode ?? false);
    $quizDisplayConfig = $configuration_detail ?? $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $quickQuizPromptFrequency = (string) (data_get($quizDisplayConfig, 'quick_quiz_prompt_frequency') ?: 'daily');
    if (! in_array($quickQuizPromptFrequency, ['never', 'session', 'daily', 'three_days', 'weekly'], true)) $quickQuizPromptFrequency = 'daily';
    $quizGroups = collect($quizGroups ?? [])->map(function ($group) {
        $name = $group->group_name;
        if (is_array($name)) {
            $name = $name['en'] ?? reset($name) ?: '';
        } elseif (is_string($name) && str_starts_with(trim($name), '{')) {
            $decoded = json_decode($name, true);
            $name = is_array($decoded) ? ($decoded['en'] ?? reset($decoded) ?: $name) : $name;
        }

        return ['id' => $group->id, 'label' => (string) $name];
    })->filter(fn ($group) => filled($group['label']))->values();
@endphp

<div
    id="quickQuizApp"
    class="{{ $quizPageMode ? 'qq-page-mode' : '' }}"
    data-options-url="{{ route('quick-quiz.options') }}"
    data-start-url="{{ route('quick-quiz.start') }}"
    data-history-url="{{ route('quick-quiz.history') }}"
    data-show-url="{{ route('quick-quiz.show', ['publicId' => '__SESSION__']) }}"
    data-answer-url="{{ route('quick-quiz.answer', ['publicId' => '__SESSION__']) }}"
    data-explanation-url="{{ route('quick-quiz.explanation', ['publicId' => '__SESSION__', 'question' => '__QUESTION__']) }}"
    data-activity-url="{{ route('quick-quiz.activity') }}"
    data-resume-session="{{ request('quick_quiz') }}"
    data-auto-open="{{ $quizPageMode || request()->boolean('open_quick_quiz') ? '1' : '0' }}"
    data-page-mode="{{ $quizPageMode ? '1' : '0' }}"
    data-prompt-frequency="{{ $quickQuizPromptFrequency }}"
    data-default-group="{{ $quickQuizDefaultGroupId ?? '' }}"
    data-explain-question="{{ request('explain') }}"
    data-authenticated="{{ auth('student')->check() ? '1' : '0' }}"
    data-student-page-url="{{ auth('student')->check() && \Illuminate\Support\Facades\Route::has('student.quick-quizzes') ? route('student.quick-quizzes') : '' }}">

    <aside class="qq-nudge" data-qq-nudge hidden aria-label="Quick quiz invitation">
        <button type="button" class="qq-nudge-close" data-qq-dismiss aria-label="Dismiss quick quiz invitation">
            <i class="ri-close-line"></i>
        </button>
        <span class="qq-nudge-icon"><i class="ri-flashlight-line"></i></span>
        <div class="qq-nudge-copy">
            <strong>{{ __('ui.ready_quick_challenge') }}</strong>
            <span>{{ __('ui.quick_nudge_detail') }}</span>
        </div>
        <button type="button" class="qq-nudge-action" data-quick-quiz-open data-qq-source="delayed_prompt">
            Start quiz <i class="ri-arrow-right-line"></i>
        </button>
    </aside>

    <div class="qq-overlay" data-qq-overlay hidden>
        <section class="qq-dialog" role="dialog" aria-modal="true" aria-labelledby="qqTitle">
            <header class="qq-header">
                <div class="qq-brand">
                    <span class="qq-brand-icon"><i class="ri-flashlight-line"></i></span>
                    <span>
                        <strong id="qqTitle">{{ __('ui.quick_quiz') }}</strong>
                        <small>{{ __('ui.quick_quiz_copy') }}</small>
                    </span>
                </div>
                <button type="button" class="qq-close" data-qq-close aria-label="{{ __('ui.close_quick_quiz') }}">
                    <i class="ri-close-line"></i>
                </button>
            </header>

            <div class="qq-progress-wrap" data-qq-progress-wrap hidden>
                <div class="qq-progress-copy">
                    <span data-qq-progress-label>{{ __('ui.question_progress_sample') }}</span>
                    <strong data-qq-score-label>{{ __('ui.correct_count', ['count' => 0]) }}</strong>
                </div>
                <div class="qq-progress"><span data-qq-progress-bar></span></div>
            </div>

            <div class="qq-body">
                <div class="qq-view" data-qq-home hidden>
                    <div class="qq-history-heading">
                        <div>
                            <span class="qq-eyebrow">{{ __('ui.your_practice') }}</span>
                            <h2>{{ __('ui.your_quick_quizzes') }}</h2>
                            <p>{{ __('ui.quick_history_copy') }}</p>
                        </div>
                        <button type="button" class="qq-primary qq-new-quiz" data-qq-new-quiz>
                            {{ __('ui.start_new_quiz') }} <i class="ri-add-line"></i>
                        </button>
                    </div>
                    <div class="qq-history-state" data-qq-history-state>{{ __('ui.loading_quizzes') }}</div>
                    <div class="qq-history-list" data-qq-history-list></div>
                </div>

                <div class="qq-view" data-qq-setup>
                    <div class="qq-intro">
                        <span class="qq-eyebrow">{{ __('ui.start_two_clicks') }}</span>
                        <h2>{{ __('ui.what_practise') }}</h2>
                        <p>{{ __('ui.quick_setup_copy') }}</p>
                    </div>

                    <form data-qq-form>
                        <input type="hidden" name="package_id" data-qq-package>
                        <input type="hidden" name="pyp_only" data-qq-pyp-only>
                        <label class="qq-field">
                            <span>{{ __('ui.exam_group') }} <em>{{ __('ui.required') }}</em></span>
                            <select name="group_id" required data-qq-group>
                                <option value="">{{ __('ui.choose_exam_group_your') }}</option>
                                @foreach($quizGroups as $quizGroup)
                                    <option value="{{ $quizGroup['id'] }}">{{ $quizGroup['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="qq-count-row">
                            <span>{{ __('ui.quiz_length') }}</span>
                            <div class="qq-segments" role="radiogroup" aria-label="{{ __('ui.quiz_length') }}">
                                <label><input type="radio" name="question_count" value="5" checked><span>{{ __('ui.questions_count', ['count' => 5]) }}</span></label>
                                <label><input type="radio" name="question_count" value="10"><span>{{ __('ui.questions_count', ['count' => 10]) }}</span></label>
                            </div>
                        </div>

                        <button type="button" class="qq-customize-toggle" data-qq-customize aria-expanded="false" hidden>
                            <span><i class="ri-equalizer-2-line"></i> {{ __('ui.focus_filters') }}</span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>

                        <div class="qq-customize" data-qq-customize-panel hidden>
                            <div class="qq-filter-grid">
                                <label class="qq-field" data-qq-category-field hidden>
                                    <span>{{ __('ui.category') }}</span>
                                    <select name="category_id" data-qq-category disabled>
                                        <option value="">{{ __('ui.any_category') }}</option>
                                    </select>
                                </label>
                                <label class="qq-field" data-qq-subject-field hidden>
                                    <span>{{ __('ui.subject') }}</span>
                                    <select name="subject_id" data-qq-subject disabled>
                                        <option value="">{{ __('ui.any_subject') }}</option>
                                    </select>
                                </label>
                                <label class="qq-field" data-qq-topic-field hidden>
                                    <span>{{ __('ui.topic') }}</span>
                                    <select name="topic_id" data-qq-topic-filter disabled>
                                        <option value="">{{ __('ui.any_topic') }}</option>
                                    </select>
                                </label>
                                <label class="qq-field" data-qq-subtopic-field hidden>
                                    <span>{{ __('ui.subtopic') }}</span>
                                    <select name="stopic_id" data-qq-subtopic disabled>
                                        <option value="">{{ __('ui.any_subtopic') }}</option>
                                    </select>
                                </label>
                            </div>
                            <p class="qq-availability" data-qq-availability>{{ __('ui.choose_group_filters') }}</p>
                        </div>

                        <div class="qq-error" data-qq-error hidden></div>
                        <button type="submit" class="qq-primary" data-qq-start disabled>
                            <span>{{ __('ui.start_quick_quiz') }}</span><i class="ri-arrow-right-line"></i>
                        </button>
                        <p class="qq-privacy"><i class="ri-shield-check-line"></i> {{ __('ui.quick_guest_copy') }}</p>
                    </form>
                </div>

                <div class="qq-view" data-qq-question hidden>
                    <div class="qq-question-meta">
                        <span data-qq-question-subject></span>
                        <span data-qq-topic></span>
                    </div>
                    <div class="qq-question-text" data-qq-question-text></div>
                    <div class="qq-answer-area" data-qq-answer-area></div>
                    <div class="qq-feedback" data-qq-feedback hidden></div>
                    <div class="qq-explanation" data-qq-explanation hidden></div>
                    <div class="qq-error" data-qq-answer-error hidden></div>
                    <div class="qq-question-actions">
                        <button type="button" class="qq-primary" data-qq-submit-answer disabled>
                            Check answer <i class="ri-check-line"></i>
                        </button>
                        <button type="button" class="qq-primary" data-qq-next hidden>
                            Next question <i class="ri-arrow-right-line"></i>
                        </button>
                    </div>
                </div>

                <div class="qq-view qq-result" data-qq-result hidden>
                    <div class="qq-result-ring" data-qq-result-score>0%</div>
                    <span class="qq-eyebrow">{{ __('ui.quiz_complete') }}</span>
                    <h2 data-qq-result-title>{{ __('ui.good_start') }}</h2>
                    <p data-qq-result-copy></p>
                    <div class="qq-result-stats">
                        <div><strong data-qq-result-correct>0</strong><span>{{ __('ui.correct') }}</span></div>
                        <div><strong data-qq-result-wrong>0</strong><span>{{ __('ui.wrong') }}</span></div>
                        <div><strong data-qq-result-total>5</strong><span>{{ __('ui.questions') }}</span></div>
                    </div>
                    <section class="qq-review" data-qq-review hidden>
                        <div class="qq-review-heading"><span><i class="ri-lightbulb-line"></i> {{ __('ui.review_explanations') }}</span><small>{{ __('ui.available_after_quiz') }}</small></div>
                        <div class="qq-review-list" data-qq-review-list></div>
                    </section>
                    <div class="qq-result-buttons">
                        <div class="qq-result-actions" data-qq-result-actions></div>
                        <button type="button" class="qq-text-button" data-qq-restart>{{ __('ui.start_new_quiz') }}</button>
                    </div>
                    <button type="button" class="qq-history-back" data-qq-history-back hidden><i class="ri-history-line"></i> {{ __('ui.back_quiz_history') }}</button>
                </div>
            </div>
        </section>
    </div>
</div>

<style>
    body.qq-is-open { overflow: hidden; }
    .qq-nudge {
        align-items: center; background: var(--theme-card-bg,#fff); border: 1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 24%,#dbe5e3);
        border-radius: 18px; bottom: 22px; box-shadow: 0 22px 60px rgba(15,23,42,.2); display: grid; gap: 12px;
        grid-template-columns: 48px minmax(0,1fr); max-width: 390px; padding: 16px; position: fixed; right: 22px; z-index: 1040;
        animation: qqNudgeIn .35s ease both;
    }
    .qq-nudge[hidden], .qq-overlay[hidden], .qq-view[hidden], [data-qq-customize-panel][hidden], [data-qq-progress-wrap][hidden], [data-qq-review][hidden], [data-qq-history-back][hidden] { display: none !important; }
    .qq-nudge-icon, .qq-brand-icon { align-items:center; background:color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#fff); border-radius:14px; color:var(--theme-primary,#0f766e); display:inline-flex; font-size:23px; height:48px; justify-content:center; width:48px; }
    .qq-nudge-copy { display:flex; flex-direction:column; gap:3px; padding-right:18px; }
    .qq-nudge-copy strong { color:var(--theme-heading,#0f172a); font-size:15px; }
    .qq-nudge-copy span { color:var(--theme-text,#64748b); font-size:12px; line-height:1.45; }
    .qq-nudge-action { background:var(--theme-primary,#0f766e); border:0; border-radius:11px; color:var(--theme-button-text,#fff); font-weight:850; grid-column:1/-1; min-height:42px; }
    .qq-nudge-close { background:transparent; border:0; color:#64748b; font-size:20px; padding:4px; position:absolute; right:8px; top:7px; }
    .qq-overlay { align-items:center; background:rgba(2,6,23,.68); display:flex; inset:0; justify-content:center; padding:18px; position:fixed; z-index:1085; backdrop-filter:blur(5px); }
    .qq-dialog { background:var(--theme-card-bg,#fff); border-radius:24px; box-shadow:0 30px 90px rgba(2,6,23,.35); max-height:min(800px,calc(100vh - 30px)); max-width:760px; overflow:auto; width:100%; animation:qqDialogIn .24s ease both; }
    .qq-header { align-items:center; border-bottom:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#e2e8f0); display:flex; justify-content:space-between; padding:18px 22px; position:sticky; top:0; z-index:3; background:color-mix(in srgb,var(--theme-card-bg,#fff) 94%,transparent); backdrop-filter:blur(12px); }
    .qq-brand { align-items:center; display:flex; gap:12px; }
    .qq-brand-icon { height:42px; width:42px; }
    .qq-brand strong { color:var(--theme-heading,#0f172a); display:block; font-size:17px; }
    .qq-brand small { color:var(--theme-text,#64748b); display:block; font-size:12px; }
    .qq-close { align-items:center; background:color-mix(in srgb,var(--theme-primary,#0f766e) 7%,#fff); border:0; border-radius:50%; color:var(--theme-heading,#0f172a); display:flex; font-size:21px; height:40px; justify-content:center; width:40px; }
    .qq-body { padding:26px; }
    .qq-intro { margin-bottom:22px; text-align:center; }
    .qq-eyebrow { color:var(--theme-primary,#0f766e); font-size:12px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .qq-intro h2, .qq-result h2 { color:var(--theme-heading,#0f172a); font-size:clamp(1.45rem,4vw,2rem); font-weight:900; margin:8px 0; }
    .qq-intro p, .qq-result>p { color:var(--theme-text,#64748b); margin:0; }
    .qq-field { display:flex; flex-direction:column; gap:7px; }
    .qq-field>span, .qq-count-row>span { color:var(--theme-heading,#0f172a); font-size:13px; font-weight:850; }
    .qq-field em { color:var(--theme-primary,#0f766e); font-size:10px; font-style:normal; margin-left:6px; text-transform:uppercase; }
    .qq-field select { appearance:none; background:color-mix(in srgb,var(--theme-primary,#0f766e) 3%,#fff) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='m2 4 4 4 4-4'/%3E%3C/svg%3E") no-repeat right 15px center; border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#d8e1e0); border-radius:12px; color:var(--theme-heading,#0f172a); font-weight:700; min-height:50px; padding:0 42px 0 14px; width:100%; }
    .qq-field select:focus { border-color:var(--theme-primary,#0f766e); box-shadow:0 0 0 3px color-mix(in srgb,var(--theme-primary,#0f766e) 12%,transparent); outline:0; }
    .qq-field select:disabled { cursor:not-allowed; opacity:.58; }
    .qq-count-row { align-items:center; display:flex; justify-content:space-between; gap:14px; margin:18px 0; }
    .qq-segments { background:color-mix(in srgb,var(--theme-primary,#0f766e) 6%,#fff); border-radius:12px; display:flex; padding:4px; }
    .qq-segments input { position:absolute; opacity:0; pointer-events:none; }
    .qq-segments span { border-radius:9px; color:var(--theme-text,#64748b); cursor:pointer; display:block; font-size:12px; font-weight:850; padding:9px 13px; }
    .qq-segments input:checked+span { background:#fff; color:var(--theme-primary,#0f766e); box-shadow:0 3px 12px rgba(15,23,42,.1); }
    .qq-customize-toggle { align-items:center; background:transparent; border:0; border-top:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#e2e8f0); color:var(--theme-primary,#0f766e); display:flex; font-size:13px; font-weight:850; justify-content:space-between; padding:16px 2px 12px; width:100%; }
    .qq-customize { background:color-mix(in srgb,var(--theme-primary,#0f766e) 3%,#fff); border-radius:14px; margin-bottom:16px; padding:14px; }
    .qq-filter-grid { display:grid; gap:13px; grid-template-columns:repeat(2,minmax(0,1fr)); }
    .qq-availability { color:var(--theme-text,#64748b); font-size:12px; margin:10px 0 0; }
    .qq-primary { align-items:center; background:var(--theme-primary,#0f766e); border:0; border-radius:12px; color:var(--theme-button-text,#fff); display:flex; font-weight:900; gap:8px; justify-content:center; min-height:50px; padding:0 20px; width:100%; }
    .qq-primary:disabled { cursor:not-allowed; opacity:.55; }
    .qq-primary:hover, .qq-primary:focus { background:color-mix(in srgb,var(--theme-primary,#0f766e) 88%,#000); color:var(--theme-button-text,#fff) !important; }
    .qq-result-actions .qq-primary:hover, .qq-result-actions .qq-primary:focus { color:var(--theme-button-text,#fff) !important; }
    .qq-privacy { color:var(--theme-text,#64748b); font-size:11px; line-height:1.5; margin:11px 0 0; text-align:center; }
    .qq-privacy i { color:var(--theme-primary,#0f766e); }
    .qq-error { background:#fff1f2; border:1px solid #fecdd3; border-radius:10px; color:#be123c; font-size:13px; margin:0 0 12px; padding:10px 12px; }
    .qq-progress-wrap { background:color-mix(in srgb,var(--theme-primary,#0f766e) 3%,#fff); padding:12px 24px; }
    .qq-progress-copy { color:var(--theme-text,#64748b); display:flex; font-size:12px; font-weight:800; justify-content:space-between; margin-bottom:7px; }
    .qq-progress-copy strong { color:var(--theme-primary,#0f766e); }
    .qq-progress { background:color-mix(in srgb,var(--theme-primary,#0f766e) 10%,#e2e8f0); border-radius:99px; height:6px; overflow:hidden; }
    .qq-progress span { background:var(--theme-primary,#0f766e); border-radius:inherit; display:block; height:100%; transition:width .3s ease; width:0; }
    .qq-question-meta { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:12px; }
    .qq-question-meta span:not(:empty) { background:color-mix(in srgb,var(--theme-primary,#0f766e) 8%,#fff); border-radius:99px; color:var(--theme-primary,#0f766e); font-size:11px; font-weight:850; padding:5px 9px; }
    .qq-question-text { color:var(--theme-heading,#0f172a); font-size:clamp(1rem,3vw,1.2rem); font-weight:750; line-height:1.65; margin-bottom:18px; overflow-wrap:anywhere; }
    .qq-options { display:grid; gap:10px; }
    .qq-option { align-items:flex-start; background:#fff; border:1px solid #dbe4e2; border-radius:13px; color:var(--theme-heading,#0f172a); display:flex; gap:11px; line-height:1.5; padding:13px; text-align:left; transition:.16s ease; width:100%; }
    .qq-option:hover:not(:disabled), .qq-option.is-selected { background:color-mix(in srgb,var(--theme-primary,#0f766e) 6%,#fff); border-color:var(--theme-primary,#0f766e); }
    .qq-option-letter { align-items:center; background:color-mix(in srgb,var(--theme-primary,#0f766e) 9%,#fff); border-radius:8px; color:var(--theme-primary,#0f766e); display:flex; flex:0 0 30px; font-size:12px; font-weight:900; height:30px; justify-content:center; }
    .qq-option.is-correct { background:#ecfdf5; border-color:#10b981; }
    .qq-option.is-wrong { background:#fff1f2; border-color:#f43f5e; }
    .qq-text-answer { border:1px solid #dbe4e2; border-radius:12px; min-height:50px; padding:12px 14px; width:100%; }
    .qq-question-actions { margin-top:18px; }
    .qq-feedback { align-items:flex-start; border-radius:12px; display:flex; gap:10px; margin-top:15px; padding:13px; }
    .qq-feedback.is-correct { background:#ecfdf5; color:#047857; }
    .qq-feedback.is-wrong { background:#fff1f2; color:#be123c; }
    .qq-feedback strong { display:block; }
    .qq-feedback span { display:block; font-size:12px; margin-top:2px; }
    .qq-explanation { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; color:#78350f; line-height:1.6; margin-top:12px; padding:14px; }
    .qq-explanation strong { display:block; margin-bottom:5px; }
    .qq-result { text-align:center; }
    .qq-result-ring { align-items:center; background:conic-gradient(var(--theme-primary,#0f766e) var(--qq-score,0%),color-mix(in srgb,var(--theme-primary,#0f766e) 10%,#e2e8f0) 0); border-radius:50%; color:var(--theme-heading,#0f172a); display:flex; font-size:1.45rem; font-weight:950; height:112px; justify-content:center; margin:0 auto 18px; position:relative; width:112px; }
    .qq-result-ring:before { background:#fff; border-radius:50%; content:""; inset:9px; position:absolute; }
    .qq-result-ring { isolation:isolate; } .qq-result-ring::after { content:attr(data-score); position:relative; z-index:1; }
    .qq-result-stats { display:grid; gap:10px; grid-template-columns:repeat(3,1fr); margin:22px 0; }
    .qq-result-stats div { background:color-mix(in srgb,var(--theme-primary,#0f766e) 4%,#fff); border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#e2e8f0); border-radius:12px; padding:14px 8px; }
    .qq-result-stats strong { color:var(--theme-heading,#0f172a); display:block; font-size:1.35rem; }
    .qq-result-stats span { color:var(--theme-text,#64748b); font-size:11px; }
    .qq-result-buttons { display:grid; gap:12px; grid-template-columns:minmax(0,1fr) minmax(0,1fr); margin-top:13px; }
    .qq-result-actions { display:grid; }
    .qq-result-actions a { text-decoration:none; }
    .qq-result-actions .qq-primary,
    .qq-result-actions .qq-primary:link,
    .qq-result-actions .qq-primary:visited,
    .qq-result-actions .qq-primary:hover,
    .qq-result-actions .qq-primary:focus,
    .qq-result-actions .qq-primary:active {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
        min-height:48px;
        opacity:1 !important;
    }
    .qq-result-actions .qq-primary * {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }
    .qq-text-button { background:var(--theme-secondary,#f59e0b); border:1px solid var(--theme-secondary,#f59e0b); border-radius:12px; color:#111827 !important; font-weight:900; min-height:48px; padding:0 18px; width:100%; }
    .qq-text-button:hover, .qq-text-button:focus { background:color-mix(in srgb,var(--theme-secondary,#f59e0b) 88%,#000); border-color:color-mix(in srgb,var(--theme-secondary,#f59e0b) 88%,#000); color:#111827 !important; }
    .qq-history-heading { align-items:flex-start; display:flex; gap:18px; justify-content:space-between; margin-bottom:22px; }
    .qq-history-heading h2 { color:var(--theme-heading,#0f172a); font-size:clamp(1.4rem,4vw,1.9rem); font-weight:900; margin:7px 0; }
    .qq-history-heading p { color:var(--theme-text,#64748b); margin:0; max-width:480px; }
    .qq-new-quiz { flex:0 0 auto; min-height:44px; width:auto; }
    .qq-history-state { background:color-mix(in srgb,var(--theme-primary,#0f766e) 4%,#fff); border-radius:12px; color:var(--theme-text,#64748b); padding:18px; text-align:center; }
    .qq-history-list { display:grid; gap:10px; }
    .qq-history-item { align-items:center; background:#fff; border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 14%,#e2e8f0); border-radius:14px; display:grid; gap:14px; grid-template-columns:minmax(0,1fr) auto auto; padding:14px; text-align:left; width:100%; }
    .qq-history-item:hover { border-color:var(--theme-primary,#0f766e); box-shadow:0 8px 24px rgba(15,23,42,.08); }
    .qq-history-copy strong, .qq-history-copy span { display:block; }
    .qq-history-copy strong { color:var(--theme-heading,#0f172a); }
    .qq-history-copy span { color:var(--theme-text,#64748b); font-size:11px; margin-top:3px; }
    .qq-history-score { color:var(--theme-primary,#0f766e); font-size:1.1rem; font-weight:950; text-align:center; }
    .qq-history-score small { color:var(--theme-text,#64748b); display:block; font-size:10px; font-weight:700; }
    .qq-history-open { color:var(--theme-primary,#0f766e); font-size:20px; }
    .qq-review { border-top:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 14%,#e2e8f0); margin-top:24px; padding-top:20px; text-align:left; }
    .qq-review-heading { align-items:center; display:flex; justify-content:space-between; margin-bottom:12px; }
    .qq-review-heading span { color:var(--theme-heading,#0f172a); font-weight:900; }
    .qq-review-heading span i { color:var(--theme-secondary,#f59e0b); }
    .qq-review-heading small { color:var(--theme-text,#64748b); }
    .qq-review-list { display:grid; gap:10px; }
    .qq-review-item { border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
    .qq-review-item[open] { border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 30%,#e2e8f0); }
    .qq-review-item summary { align-items:center; background:#fff; color:var(--theme-heading,#0f172a); cursor:pointer; display:flex; gap:10px; font-weight:800; list-style:none; padding:13px; }
    .qq-review-item summary::-webkit-details-marker { display:none; }
    .qq-review-status { align-items:center; border-radius:50%; color:#fff; display:inline-flex; flex:0 0 24px; height:24px; justify-content:center; }
    .qq-review-status.is-correct { background:#10b981; }
    .qq-review-status.is-wrong { background:#f43f5e; }
    .qq-review-body { background:#f8fafc; color:var(--theme-text,#64748b); line-height:1.55; padding:14px; }
    .qq-review-question { color:var(--theme-heading,#0f172a); font-weight:750; margin-bottom:10px; }
    .qq-review-answer { font-size:12px; margin:4px 0; }
    .qq-review-explanation { background:#fffbeb; border-radius:10px; color:#78350f; margin-top:11px; padding:12px; }
    .qq-history-back { background:transparent; border:0; color:var(--theme-primary,#0f766e); font-weight:850; margin-top:14px; }
    @keyframes qqNudgeIn { from { opacity:0; transform:translateY(18px); } }
    @keyframes qqDialogIn { from { opacity:0; transform:translateY(14px) scale(.98); } }
    @media (max-width:640px) {
        .qq-result-buttons { grid-template-columns:1fr; }
        .qq-history-heading { flex-direction:column; }
        .qq-new-quiz { width:100%; }
        .qq-history-item { grid-template-columns:minmax(0,1fr) auto; }
        .qq-history-open { display:none; }
        .qq-review-heading { align-items:flex-start; flex-direction:column; gap:3px; }
        .qq-overlay { align-items:flex-end; padding:0; }
        .qq-dialog { border-radius:22px 22px 0 0; max-height:94vh; }
        .qq-body { padding:20px 16px 24px; }
        .qq-header { padding:14px 16px; }
        .qq-brand small { display:none; }
        .qq-filter-grid { grid-template-columns:1fr; }
        .qq-count-row { align-items:flex-start; flex-direction:column; }
        .qq-segments { width:100%; } .qq-segments label { flex:1; text-align:center; }
        .qq-nudge { bottom:12px; left:12px; max-width:none; right:12px; }
    }
    .qq-page-mode .qq-nudge { display:none !important; }
    .qq-page-mode .qq-overlay { align-items:stretch; backdrop-filter:none; background:transparent; display:block; inset:auto; justify-content:stretch; padding:0; position:relative; z-index:1; }
    .qq-page-mode .qq-dialog { animation:none; border:1px solid var(--el-border,#dbe4e2); border-radius:12px; box-shadow:0 8px 28px rgba(15,23,42,.06); max-height:none; max-width:none; overflow:visible; width:100%; }
    .qq-page-mode .qq-header { border-radius:12px 12px 0 0; position:relative; top:auto; }
    .qq-page-mode .qq-close { display:none; }
    .qq-page-mode .qq-body { padding:clamp(20px,3vw,32px); }
    @media (prefers-reduced-motion:reduce) { .qq-nudge,.qq-dialog { animation:none; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('quickQuizApp');
    if (!app) return;
    const pageMode = app.dataset.pageMode === '1';

    const qs = (selector, root = app) => root.querySelector(selector);
    const qsa = (selector, root = app) => Array.from(root.querySelectorAll(selector));
    const overlay = qs('[data-qq-overlay]');
    const nudge = qs('[data-qq-nudge]');
    const homeView = qs('[data-qq-home]');
    const setupView = qs('[data-qq-setup]');
    const questionView = qs('[data-qq-question]');
    const resultView = qs('[data-qq-result]');
    const progressWrap = qs('[data-qq-progress-wrap]');
    const form = qs('[data-qq-form]');
    const groupSelect = qs('[data-qq-group]');
    const subjectSelect = qs('[data-qq-subject]');
    const categorySelect = qs('[data-qq-category]');
    const categoryField = qs('[data-qq-category-field]');
    const topicSelect = qs('[data-qq-topic-filter]');
    const subtopicSelect = qs('[data-qq-subtopic]');
    const subjectField = qs('[data-qq-subject-field]');
    const topicField = qs('[data-qq-topic-field]');
    const subtopicField = qs('[data-qq-subtopic-field]');
    const customizeToggle = qs('[data-qq-customize]');
    const customizePanel = qs('[data-qq-customize-panel]');
    const startButton = qs('[data-qq-start]');
    const packageInput = qs('[data-qq-package]');
    const pypOnlyInput = qs('[data-qq-pyp-only]');
    const submitButton = qs('[data-qq-submit-answer]');
    const nextButton = qs('[data-qq-next]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urls = {
        options: app.dataset.optionsUrl,
        start: app.dataset.startUrl,
        history: app.dataset.historyUrl,
        show: app.dataset.showUrl,
        answer: app.dataset.answerUrl,
        explanation: app.dataset.explanationUrl,
        activity: app.dataset.activityUrl
    };
    let sessionId = null;
    let currentQuestion = null;
    let pendingNext = null;
    let pendingSummary = null;
    let pendingProgress = null;
    let selectedOptions = [];
    let selectedAnswer = '';
    let answerPending = false;
    let optionsRequestId = 0;
    let loadedOptionsGroupId = '';
    let rootOptionsPromise = null;
    let opener = null;
    let scopedSelection = {};

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
            ...options
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw Object.assign(new Error(data.message || 'Something went wrong. Please try again.'), { response, data });
        return data;
    };

    const track = (action, extra = {}) => request(urls.activity, {
        method: 'POST',
        body: JSON.stringify({ action, session_id: sessionId, source: extra.source || 'homepage', question_id: extra.question_id || null })
    }).catch(() => {});

    const showOnly = (view) => {
        [homeView, setupView, questionView, resultView].forEach(item => item.hidden = item !== view);
        progressWrap.hidden = view !== questionView;
    };

    const showSetup = () => {
        showOnly(setupView);
        startButton.disabled = !groupSelect.value;
        window.setTimeout(() => groupSelect.focus(), 50);
    };

    const renderHistory = sessions => {
        const state = qs('[data-qq-history-state]');
        const list = qs('[data-qq-history-list]');
        if (!sessions.length) {
            state.textContent = 'No previous quick quizzes yet. Start your first one now.';
            state.hidden = false;
            list.innerHTML = '';
            return;
        }

        state.hidden = true;
        list.innerHTML = sessions.map(item => {
            const completed = item.status === 'completed';
            const detail = completed
                ? `${item.correct} of ${item.total} correct · ${escapeHtml(item.taken_label || '')}`
                : `${item.answered} of ${item.total} answered · ${escapeHtml(item.taken_label || '')}`;
            return `<button type="button" class="qq-history-item" data-qq-history-session="${item.session_id}">
                <span class="qq-history-copy"><strong>${escapeHtml(item.group)}</strong><span>${detail}</span></span>
                <span class="qq-history-score">${completed ? `${item.score_percent}%` : 'Continue'}<small>${completed ? 'Score' : 'In progress'}</small></span>
                <i class="ri-arrow-right-s-line qq-history-open" aria-hidden="true"></i>
            </button>`;
        }).join('');
    };

    const showHistory = async () => {
        sessionId = null;
        currentQuestion = null;
        showOnly(homeView);
        const state = qs('[data-qq-history-state]');
        const list = qs('[data-qq-history-list]');
        state.hidden = false;
        state.textContent = 'Loading your quizzes...';
        list.innerHTML = '';
        try {
            const response = await request(urls.history);
            renderHistory(response.sessions || []);
        } catch (error) {
            state.textContent = 'Your quiz history could not be loaded. You can still start a new quiz.';
        }
    };
    const openModal = async (source = 'homepage_button', scope = {}) => {
        scopedSelection = scope || {};
        if (!pageMode && app.dataset.authenticated === '1' && app.dataset.studentPageUrl && !scopedSelection.packageId) {
            window.location.assign(app.dataset.studentPageUrl);
            return;
        }
        opener = document.activeElement;
        overlay.hidden = false;
        nudge.hidden = true;
        if (!pageMode) document.body.classList.add('qq-is-open');
        track('opened', { source });
        const savedSession = app.dataset.resumeSession || localStorage.getItem('quickQuizActiveSession');
        if (savedSession) {
            try {
                await resumeQuiz(savedSession);
                return;
            } catch (_) {
                localStorage.removeItem('quickQuizActiveSession');
                app.dataset.resumeSession = '';
            }
        }
        if (app.dataset.authenticated === '1' && !scopedSelection.packageId) {
            await showHistory();
        } else {
            showSetup();
            await applyScopedSelection();
        }
    };

    const closeModal = () => {
        if (pageMode) return;
        overlay.hidden = true;
        document.body.classList.remove('qq-is-open');
        if (opener && typeof opener.focus === 'function') opener.focus();
    };

    qsa('[data-quick-quiz-open]', document).forEach(button => button.addEventListener('click', event => {
        event.preventDefault();
        openModal(button.dataset.qqSource || 'homepage_button', {
            groupId: button.dataset.qqGroupId || '',
            packageId: button.dataset.qqPackageId || '',
            pypOnly: button.dataset.qqPypOnly || '',
            subjectId: button.dataset.qqSubjectId || '',
            subjectLabel: button.dataset.qqSubjectLabel || 'Selected subject',
            topicId: button.dataset.qqTopicId || '',
            topicLabel: button.dataset.qqTopicLabel || 'Selected topic',
            subtopicId: button.dataset.qqSubtopicId || '',
            subtopicLabel: button.dataset.qqSubtopicLabel || 'Selected subtopic'
        });
    }));
    qs('[data-qq-new-quiz]').addEventListener('click', showSetup);
    qs('[data-qq-history-list]').addEventListener('click', event => {
        const item = event.target.closest('[data-qq-history-session]');
        if (item) resumeQuiz(item.dataset.qqHistorySession);
    });
    qs('[data-qq-history-back]').addEventListener('click', showHistory);
    qs('[data-qq-close]').addEventListener('click', closeModal);
    overlay.addEventListener('click', event => { if (!pageMode && event.target === overlay) closeModal(); });
    document.addEventListener('keydown', event => { if (!pageMode && event.key === 'Escape' && !overlay.hidden) closeModal(); });

    qs('[data-qq-dismiss]').addEventListener('click', () => {
        nudge.hidden = true;
        localStorage.setItem('quickQuizPromptDismissedAt', String(Date.now()));
        track('dismissed', { source: 'delayed_prompt' });
    });

    const promptFrequency = app.dataset.promptFrequency || 'daily';
    const promptCooldowns = {
        session: 0,
        daily: 24 * 60 * 60 * 1000,
        three_days: 3 * 24 * 60 * 60 * 1000,
        weekly: 7 * 24 * 60 * 60 * 1000
    };
    const maybeShowNudge = () => {
        const dismissedAt = Number(localStorage.getItem('quickQuizPromptDismissedAt') || 0);
        const shownAt = Number(localStorage.getItem('quickQuizPromptShownAt') || 0);
        const lastPromptAt = Math.max(dismissedAt, shownAt);
        const cooldown = promptCooldowns[promptFrequency] ?? promptCooldowns.daily;
        const interacting = ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName);
        if (document.hidden || interacting || !overlay.hidden || sessionStorage.getItem('quickQuizPromptSeen') || (cooldown > 0 && Date.now() - lastPromptAt < cooldown)) return;
        sessionStorage.setItem('quickQuizPromptSeen', '1');
        localStorage.setItem('quickQuizPromptShownAt', String(Date.now()));
        nudge.hidden = false;
        track('prompt_shown', { source: 'delayed_prompt' });
    };
    if (!pageMode && promptFrequency !== 'never' && app.dataset.authenticated !== '1' && !app.dataset.resumeSession && !localStorage.getItem('quickQuizActiveSession')) window.setTimeout(maybeShowNudge, 12000);

    const fillSelect = (select, field, items, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}">${escapeHtml(item.label)}</option>`).join('');
        select.disabled = !groupSelect.value || items.length === 0;
        field.hidden = items.length === 0;
    };

    const resetHierarchy = () => {
        fillSelect(categorySelect, categoryField, [], 'Any category');
        fillSelect(subjectSelect, subjectField, [], 'Any subject');
        fillSelect(topicSelect, topicField, [], 'Any topic');
        fillSelect(subtopicSelect, subtopicField, [], 'Any subtopic');
    };

    const loadOptions = async (params, statusText) => {
        const groupId = groupSelect.value;
        if (!groupId) return null;
        const requestId = ++optionsRequestId;
        const search = new URLSearchParams({ group_id: groupId });
        Object.entries(params).forEach(([key, value]) => { if (value) search.set(key, value); });
        if (packageInput.value) search.set('package_id', packageInput.value);
        if (pypOnlyInput.value) search.set('pyp_only', pypOnlyInput.value);
        qs('[data-qq-availability]').textContent = statusText;
        try {
            const response = await request(`${urls.options}?${search.toString()}`);
            if (requestId !== optionsRequestId || groupSelect.value !== groupId) return null;
            return response;
        } catch (error) {
            if (requestId === optionsRequestId) qs('[data-qq-availability]').textContent = error.message;
            return null;
        }
    };

    const loadRootOptions = () => {
        if (loadedOptionsGroupId === groupSelect.value) return Promise.resolve();
        if (rootOptionsPromise) return rootOptionsPromise;
        const promise = (async () => {
            const response = await loadOptions({}, 'Loading available categories and subjects…');
            if (!response) return;
            const categories = response.categories || [];
            const subjects = response.subjects || [];
            fillSelect(categorySelect, categoryField, categories, 'Any category');
            fillSelect(subjectSelect, subjectField, subjects, 'Any subject');
            loadedOptionsGroupId = groupSelect.value;
            qs('[data-qq-availability]').textContent = categories.length || subjects.length
                ? 'Select any available level; each next filter is loaded from matching questions.'
                : 'No additional filters are available for this group.';
            if (categories.length === 0 && subjects.length === 0) {
                customizeToggle.hidden = true;
                customizeToggle.setAttribute('aria-expanded', 'false');
                customizePanel.hidden = true;
            }
        })();
        rootOptionsPromise = promise;
        return promise.finally(() => { if (rootOptionsPromise === promise) rootOptionsPromise = null; });
    };

    const applyScopedSelection = async () => {
        if (!scopedSelection.groupId) return;
        packageInput.value = scopedSelection.packageId || '';
        pypOnlyInput.value = scopedSelection.pypOnly || '';
        groupSelect.value = scopedSelection.groupId;
        groupSelect.dispatchEvent(new Event('change'));
        await loadRootOptions();
        customizeToggle.hidden = false;
        customizePanel.hidden = false;
        customizeToggle.setAttribute('aria-expanded', 'true');

        const ensureOption = (select, id, label) => {
            if (!id || Array.from(select.options).some(option => option.value === id)) return;
            select.add(new Option(label, id));
            select.disabled = false;
            select.closest('[data-qq-subject-field],[data-qq-topic-field],[data-qq-subtopic-field]')?.removeAttribute('hidden');
        };
        ensureOption(subjectSelect, scopedSelection.subjectId, scopedSelection.subjectLabel);
        if (scopedSelection.subjectId) {
            subjectSelect.value = scopedSelection.subjectId;
            const topicResponse = await loadOptions({ subject_id: scopedSelection.subjectId }, 'Loading matching topics…');
            if (topicResponse) fillSelect(topicSelect, topicField, topicResponse.topics || [], 'Any topic');
        }
        ensureOption(topicSelect, scopedSelection.topicId, scopedSelection.topicLabel);
        if (scopedSelection.topicId) {
            topicSelect.value = scopedSelection.topicId;
            const subtopicResponse = await loadOptions(
                { subject_id: scopedSelection.subjectId, topic_id: scopedSelection.topicId },
                'Loading matching subtopics…'
            );
            if (subtopicResponse) fillSelect(subtopicSelect, subtopicField, subtopicResponse.subtopics || [], 'Any subtopic');
        }
        ensureOption(subtopicSelect, scopedSelection.subtopicId, scopedSelection.subtopicLabel);
        if (scopedSelection.subtopicId) {
            subtopicSelect.value = scopedSelection.subtopicId;
        }
        qs('[data-qq-availability]').textContent = 'This quiz is limited to the selected previous-year question collection.';
    };

    customizeToggle.addEventListener('click', async function () {
        if (!groupSelect.value) return;
        customizePanel.hidden = !customizePanel.hidden;
        this.setAttribute('aria-expanded', customizePanel.hidden ? 'false' : 'true');
        if (!customizePanel.hidden) {
            this.disabled = true;
            await loadRootOptions();
            this.disabled = false;
        }
    });

    groupSelect.addEventListener('change', () => {
        ++optionsRequestId;
        loadedOptionsGroupId = '';
        rootOptionsPromise = null;
        startButton.disabled = !groupSelect.value;
        resetHierarchy();
        customizeToggle.hidden = !groupSelect.value;
        customizeToggle.disabled = false;
        customizeToggle.setAttribute('aria-expanded', 'false');
        customizePanel.hidden = true;
        qs('[data-qq-availability]').textContent = 'Open filters to load available categories and subjects.';
        if (groupSelect.value) loadRootOptions();
    });

    categorySelect.addEventListener('change', async () => {
        fillSelect(subjectSelect, subjectField, [], 'Any subject');
        fillSelect(topicSelect, topicField, [], 'Any topic');
        fillSelect(subtopicSelect, subtopicField, [], 'Any subtopic');
        const response = await loadOptions(
            { category_id: categorySelect.value },
            'Loading matching subjects…'
        );
        if (!response) return;
        fillSelect(subjectSelect, subjectField, response.subjects || [], 'Any subject');
        qs('[data-qq-availability]').textContent = 'Subjects updated for the selected category.';
    });

    subjectSelect.addEventListener('change', async () => {
        fillSelect(topicSelect, topicField, [], 'Any topic');
        fillSelect(subtopicSelect, subtopicField, [], 'Any subtopic');
        if (!subjectSelect.value) return;
        const response = await loadOptions(
            { category_id: categorySelect.value, subject_id: subjectSelect.value },
            'Loading matching topics…'
        );
        if (!response) return;
        fillSelect(topicSelect, topicField, response.topics || [], 'Any topic');
        qs('[data-qq-availability]').textContent = (response.topics || []).length
            ? 'Topics updated for the selected subject.'
            : 'No topic filter is available for this subject.';
    });

    topicSelect.addEventListener('change', async () => {
        fillSelect(subtopicSelect, subtopicField, [], 'Any subtopic');
        if (!topicSelect.value) return;
        const response = await loadOptions(
            { category_id: categorySelect.value, subject_id: subjectSelect.value, topic_id: topicSelect.value },
            'Loading matching subtopics…'
        );
        if (!response) return;
        fillSelect(subtopicSelect, subtopicField, response.subtopics || [], 'Any subtopic');
        qs('[data-qq-availability]').textContent = (response.subtopics || []).length
            ? 'Subtopics updated for the selected topic.'
            : 'No subtopic filter is available for this topic.';
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const errorBox = qs('[data-qq-error]');
        errorBox.hidden = true;
        startButton.disabled = true;
        startButton.innerHTML = '<span>{{ __('ui.preparing_quiz') }}</span><i class="ri-loader-4-line"></i>';
        const data = Object.fromEntries(new FormData(form).entries());
        ['category_id', 'subject_id', 'topic_id', 'stopic_id'].forEach(key => { if (!data[key]) delete data[key]; });
        data.source = scopedSelection.packageId ? 'pyp_page' : (pageMode ? 'student_page' : 'homepage');
        try {
            const response = await request(urls.start, { method: 'POST', body: JSON.stringify(data) });
            sessionId = response.session_id;
            localStorage.setItem('quickQuizActiveSession', sessionId);
            renderQuestion(response.question, response.progress);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
        } finally {
            startButton.disabled = !groupSelect.value;
            startButton.innerHTML = '<span>{{ __('ui.start_quick_quiz') }}</span><i class="ri-arrow-right-line"></i>';
        }
    });

    const renderQuestion = (question, progress) => {
        currentQuestion = question;
        pendingNext = null;
        pendingSummary = null;
        pendingProgress = null;
        selectedOptions = [];
        selectedAnswer = '';
        answerPending = false;
        showOnly(questionView);
        qs('[data-qq-feedback]').hidden = true;
        qs('[data-qq-explanation]').hidden = true;
        qs('[data-qq-answer-error]').hidden = true;
        submitButton.hidden = false;
        submitButton.disabled = true;
        nextButton.hidden = true;
        qs('[data-qq-question-subject]').textContent = question.subject || '';
        qs('[data-qq-topic]').textContent = question.topic || '';
        qs('[data-qq-question-text]').innerHTML = question.html || '';
        const area = qs('[data-qq-answer-area]');

        if (['multiple_choice_radio', 'multiple_choice_checkbox', 'true_false'].includes(question.type)) {
            area.innerHTML = '<div class="qq-options">' + (question.options || []).map(option =>
                `<button type="button" class="qq-option" data-option-index="${option.index}" data-answer-value="${escapeHtml(option.value || '')}">
                    <span class="qq-option-letter">${escapeHtml(option.label)}</span><span>${option.html || ''}</span>
                </button>`
            ).join('') + '</div>';
            qsa('.qq-option', area).forEach(button => button.addEventListener('click', async () => {
                if (answerPending) return;
                selectOption(button, question);
                if (! question.multiple) await submitCurrentAnswer();
            }));
            submitButton.hidden = ! question.multiple;
        } else {
            const inputMode = question.type === 'nat' ? 'decimal' : 'text';
            area.innerHTML = `<input class="qq-text-answer" data-qq-text-answer inputmode="${inputMode}" autocomplete="off" placeholder="${question.type === 'nat' ? 'Enter your numerical answer' : 'Type your answer'}">`;
            qs('[data-qq-text-answer]', area).addEventListener('input', event => {
                selectedAnswer = event.target.value.trim();
                submitButton.disabled = !selectedAnswer;
            });
            submitButton.hidden = false;
        }
        updateProgress(progress, question.position);
        typesetMath();
        qs('[data-qq-question-text]').scrollIntoView({ block: 'nearest' });
    };

    const selectOption = (button, question) => {
        const index = Number(button.dataset.optionIndex);
        if (question.type === 'true_false') {
            qsa('.qq-option', qs('[data-qq-answer-area]')).forEach(item => item.classList.remove('is-selected'));
            button.classList.add('is-selected');
            selectedAnswer = button.dataset.answerValue || button.textContent.trim();
            selectedOptions = [];
        } else if (question.multiple) {
            button.classList.toggle('is-selected');
            selectedOptions = qsa('.qq-option.is-selected', qs('[data-qq-answer-area]')).map(item => Number(item.dataset.optionIndex));
        } else {
            qsa('.qq-option', qs('[data-qq-answer-area]')).forEach(item => item.classList.remove('is-selected'));
            button.classList.add('is-selected');
            selectedOptions = [index];
        }
        submitButton.disabled = question.type === 'true_false' ? !selectedAnswer : selectedOptions.length === 0;
    };

    const submitCurrentAnswer = async () => {
        if (!currentQuestion || !sessionId || answerPending) return;
        answerPending = true;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Checking… <i class="ri-loader-4-line"></i>';
        const errorBox = qs('[data-qq-answer-error]');
        errorBox.hidden = true;
        qsa('.qq-option', qs('[data-qq-answer-area]')).forEach(button => button.disabled = true);
        try {
            const response = await request(urls.answer.replace('__SESSION__', sessionId), {
                method: 'POST',
                body: JSON.stringify({ question_id: currentQuestion.id, selected_options: selectedOptions, answer: selectedAnswer })
            });
            showFeedback(response);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
            submitButton.disabled = false;
            qsa('.qq-option', qs('[data-qq-answer-area]')).forEach(button => button.disabled = false);
        } finally {
            submitButton.innerHTML = 'Check answer <i class="ri-check-line"></i>';
            answerPending = false;
        }
    };

    submitButton.addEventListener('click', submitCurrentAnswer);

    const showFeedback = response => {
        qsa('.qq-option', qs('[data-qq-answer-area]')).forEach(button => {
            button.disabled = true;
            const index = Number(button.dataset.optionIndex);
            if ((response.correct_options || []).includes(index)) button.classList.add('is-correct');
            if (button.classList.contains('is-selected') && !(response.correct_options || []).includes(index)) button.classList.add('is-wrong');
        });
        const feedback = qs('[data-qq-feedback]');
        feedback.className = `qq-feedback ${response.correct ? 'is-correct' : 'is-wrong'}`;
        feedback.innerHTML = response.correct
            ? '<i class="ri-checkbox-circle-fill"></i><div><strong>{{ __('ui.correct_bang') }}</strong><span>{{ __('ui.correct_feedback') }}</span></div>'
            : `<i class="ri-close-circle-fill"></i><div><strong>{{ __('ui.not_quite') }}</strong><span>{{ __('ui.correct_answer_prefix') }} ${escapeHtml(response.correct_answer || @json(__('ui.see_highlighted_choice')))}</span></div>`;
        feedback.hidden = false;

        pendingNext = response.next_question;
        pendingSummary = response.summary;
        pendingProgress = response.progress;
        submitButton.hidden = true;
        nextButton.hidden = false;
        nextButton.innerHTML = response.completed ? 'View score <i class="ri-bar-chart-line"></i>' : 'Next question <i class="ri-arrow-right-line"></i>';
        updateProgress(response.progress, currentQuestion.position);
    };


    nextButton.addEventListener('click', () => {
        if (pendingSummary) renderResult(pendingSummary);
        else if (pendingNext) renderQuestion(pendingNext, pendingProgress);
    });

    const renderResult = summary => {
        showOnly(resultView);
        progressWrap.hidden = true;
        localStorage.removeItem('quickQuizActiveSession');
        localStorage.setItem('quickQuizPromptDismissedAt', String(Date.now()));
        app.dataset.resumeSession = '';
        const score = Number(summary.score_percent || 0);
        const ring = qs('[data-qq-result-score]');
        ring.style.setProperty('--qq-score', `${score}%`);
        ring.dataset.score = `${score}%`;
        ring.textContent = '';
        qs('[data-qq-result-title]').textContent = score >= 80 ? 'Excellent work!' : score >= 50 ? 'Good momentum!' : 'A useful first step!';
        qs('[data-qq-result-copy]').textContent = score >= 80
            ? 'You are strong in this selection. Try a longer or more focused practice test next.'
            : 'Review your weak areas and try another short quiz to improve accuracy.';
        qs('[data-qq-result-correct]').textContent = summary.correct;
        qs('[data-qq-result-wrong]').textContent = summary.wrong;
        qs('[data-qq-result-total]').textContent = summary.total;
        const actions = qs('[data-qq-result-actions]');
        actions.innerHTML = summary.is_authenticated
            ? `<a class="qq-primary" href="${summary.practice_builder_url}">{{ __('ui.open_practice_builder') }} <i class="ri-equalizer-2-line"></i></a>`
            : `<a class="qq-primary" href="${summary.login_url}">{{ __('ui.save_unlock_explanations') }} <i class="ri-user-add-line"></i></a>`;

        const review = Array.isArray(summary.review) ? summary.review : [];
        const reviewSection = qs('[data-qq-review]');
        const reviewList = qs('[data-qq-review-list]');
        reviewSection.hidden = !summary.is_authenticated || review.length === 0;
        reviewList.innerHTML = review.map(item => `<details class="qq-review-item">
            <summary><span class="qq-review-status ${item.is_correct ? 'is-correct' : 'is-wrong'}"><i class="${item.is_correct ? 'ri-check-line' : 'ri-close-line'}"></i></span><span>{{ __('ui.question') }} ${item.position} · ${item.is_correct ? @json(__('ui.correct')) : @json(__('ui.review_needed'))}</span></summary>
            <div class="qq-review-body">
                <div class="qq-review-question">${item.question || ''}</div>
                <div class="qq-review-answer"><strong>{{ __('ui.your_answer_colon') }}</strong> ${escapeHtml(item.your_answer || @json(__('ui.no_answer')))}</div>
                <div class="qq-review-answer"><strong>{{ __('ui.correct_answer_colon') }}</strong> ${escapeHtml(item.correct_answer || @json(__('ui.see_explanation')))}</div>
                <div class="qq-review-explanation"><strong>{{ __('ui.explanation') }}</strong><div>${item.explanation || ''}</div></div>
            </div>
        </details>`).join('');
        qs('[data-qq-history-back]').hidden = !summary.is_authenticated;
        if (review.length) typesetMath();
    };

    qs('[data-qq-restart]').addEventListener('click', () => {
        sessionId = null;
        currentQuestion = null;
        showSetup();
    });

    const updateProgress = (progress, position) => {
        const total = Number(progress?.total || currentQuestion?.total || 0);
        const answered = Number(progress?.answered || 0);
        qs('[data-qq-progress-label]').textContent = `Question ${position || Math.min(answered + 1, total)} of ${total}`;
        qs('[data-qq-score-label]').textContent = `${Number(progress?.correct || 0)} correct`;
        qs('[data-qq-progress-bar]').style.width = `${total ? Math.round((answered / total) * 100) : 0}%`;
    };

    const resumeQuiz = async savedSession => {
        sessionId = savedSession;
        const response = await request(urls.show.replace('__SESSION__', sessionId));
        localStorage.setItem('quickQuizActiveSession', sessionId);
        if (response.completed) renderResult(response.summary);
        else renderQuestion(response.question, response.progress);
    };

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);
    const typesetMath = () => {
        if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise().catch(() => {});
    };

    if (app.dataset.defaultGroup && groupSelect.querySelector(`option[value="${app.dataset.defaultGroup}"]`)) {
        groupSelect.value = app.dataset.defaultGroup;
        groupSelect.dispatchEvent(new Event('change'));
    }

    if (app.dataset.resumeSession) openModal('post_login_return');
    else if (app.dataset.autoOpen === '1') openModal(pageMode ? 'student_quiz_page' : 'navigation');
});
</script>
