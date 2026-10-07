@php
    $mode = $mode ?? 'student';
    $startRoute = $mode === 'guest' ? 'guest.startExam' : 'student.startExam';
    $examKey = $mode === 'guest' ? ($exam->slug ?: $exam->id) : $exam->id;
    $languageService = app(App\Services\ExamLanguageService::class);
    $availableLanguages = $languageService->available($exam);
    $selectedLanguage = $languageService->resolve($exam, request('lang'));
    $selectedLanguageId = $selectedLanguage?->id;
    $display = $languageService->display($exam, $selectedLanguage);
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
    $examName = $displayText($display['name']);
    $examInstruction = $displayText($display['instruction']);
    $translationReady = $languageService->isEnglish($selectedLanguage)
        || $availableLanguages->firstWhere('id', $selectedLanguageId)?->pivot?->translation_status === 'ready';
    $translationRoute = $mode === 'guest' ? 'guest.exams.languages.translate' : 'student.exams.languages.translate';
    $translationEndpointTemplate = route($translationRoute, ['id' => $examKey, 'languageId' => '__LANGUAGE__']);
    $themeConfig = getConfiguration();
    $themePrimary = $themeConfig->theme_primary_color ?? '#008080';
    $themeSecondary = $themeConfig->theme_secondary_color ?? '#00a0a0';
@endphp

<style>
    :root {
        --theme-primary: {{ $themePrimary }};
        --theme-secondary: {{ $themeSecondary }};
        --teal-primary: var(--theme-primary, #008080);
        --teal-dark: var(--theme-primary, #006666);
        --teal-light: color-mix(in srgb, var(--theme-primary, #008080) 10%, #ffffff);
        --teal-gradient: var(--theme-primary, #008080);
    }
    .instruction-container { display: flex; flex-direction: column; height: 100dvh; max-width: 960px; margin: 0 auto; overflow: hidden; padding: 16px 20px; }
    .instruction-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 4px 4px 20px; scrollbar-gutter: stable; }
    .exam-header { background: var(--teal-gradient); border-radius: 14px; padding: clamp(20px, 5vw, 30px); color: white; margin-bottom: 22px; text-align: center; box-shadow: 0 10px 30px color-mix(in srgb, var(--theme-primary, #008080) 24%, transparent); }
    .exam-header h2 { margin: 0 0 10px; font-size: clamp(1.35rem, 5vw, 2rem); font-weight: 800; color: white; line-height: 1.2; overflow-wrap: anywhere; }
    .exam-header p { margin: 0; opacity: 0.95; color: rgba(255,255,255,0.9); }
    .exam-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.22); }
    .stat-card { text-align: center; background: rgba(255,255,255,0.15); padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.16); }
    .stat-number { font-size: clamp(1.25rem, 4vw, 1.75rem); font-weight: 800; display: block; color: white; }
    .stat-label { font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.9); }
    .language-card, .instructions-card { background: var(--el-card-bg, #ffffff); border-radius: 14px; padding: clamp(18px, 4vw, 25px); margin-bottom: 22px; border: 1px solid var(--el-border, #e2e8f0); box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .language-card h5, .instructions-card h5 { color: var(--teal-primary); margin-bottom: 15px; font-weight: 700; font-size: 18px; }
    .guest-details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .guest-details-grid .form-control { border: 1px solid var(--el-border, #e2e8f0); border-radius: 10px; min-height: 48px; }
    .guest-details-grid .form-control:focus { border-color: var(--theme-primary, #008080); box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-primary, #008080) 14%, transparent); }
    .language-selector { width: 100%; padding: 14px 18px; border: 1px solid var(--el-border, #e2e8f0); border-radius: 10px; font-size: 15px; background: white; cursor: pointer; }
    .language-selector:focus { outline: none; border-color: var(--theme-primary, #008080); box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-primary, #008080) 14%, transparent); }
    .info-text { display: block; margin-top: 10px; font-size: 13px; color: var(--teal-primary); }
    .instructions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
    .instruction-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--teal-light); border-radius: 10px; border: 1px solid color-mix(in srgb, var(--theme-primary, #008080) 12%, #ffffff); }
    .instruction-icon { width: 34px; height: 34px; background: var(--teal-primary); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex: 0 0 auto; }
    .warning-card { background: var(--el-secondary-soft, color-mix(in srgb, var(--theme-secondary, #00a0a0) 12%, #ffffff)); border-radius: 16px; padding: 20px; margin-bottom: 25px; border-left: 4px solid var(--theme-secondary, #00a0a0); color: var(--el-heading, #1f2937); }
    .agree-card { background: transparent; border: 0; padding: 0; margin: 0; }
    .custom-checkbox { display: flex; align-items: center; gap: 15px; cursor: pointer; }
    .custom-checkbox input { width: 22px; height: 22px; cursor: pointer; accent-color: var(--teal-primary); }
    .start-btn { width: 100%; padding: 15px; background: var(--teal-gradient); color: white; border: none; border-radius: 12px; font-size: 17px; font-weight: 800; cursor: pointer; opacity: 1; }
    .start-btn:hover, .start-btn:focus { color: white; box-shadow: 0 12px 24px color-mix(in srgb, var(--theme-primary, #008080) 20%, transparent); }
    .start-btn:disabled { opacity: 0.55; cursor: not-allowed; }
    .instruction-action-bar { flex: 0 0 auto; display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 340px); align-items: center; gap: 18px; padding: 14px 4px max(14px, env(safe-area-inset-bottom)); background: var(--el-page-bg, #fff); border-top: 1px solid var(--el-border, #e2e8f0); box-shadow: 0 -10px 28px rgba(15, 23, 42, 0.08); }
    .start-action { min-width: 0; }
    .start-help { display: block; min-height: 18px; margin: 0 0 6px; color: #64748b; font-size: 12px; text-align: center; }
    .camera-card { text-align: left; }
    .camera-status { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--el-border, #e2e8f0); }
    .camera-status-icon { width: 38px; height: 38px; flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--teal-light); color: var(--teal-primary); font-size: 20px; }
    .camera-preview { display: none; width: min(100%, 360px); aspect-ratio: 4 / 3; margin: 16px auto 0; overflow: hidden; border-radius: 12px; background: #0f172a; }
    .camera-preview video { width: 100%; height: 100%; object-fit: cover; }
    .camera-enable-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; margin-top: 14px; padding: 10px 20px; border: 0; border-radius: 10px; background: var(--teal-primary); color: #fff; font-weight: 700; }
    .camera-help { display: none; margin-top: 14px; padding: 14px; border: 1px solid #f59e0b; border-radius: 10px; background: #fffbeb; color: #78350f; }
    .camera-help ol { margin: 8px 0 0; padding-left: 20px; }
    .camera-card[data-camera-state="ready"] .camera-preview { display: block; }
    .camera-card[data-camera-state="ready"] .camera-enable-btn { display: none; }
    .camera-card[data-camera-state="denied"] .camera-help { display: block; }
    @media (max-width: 768px) {
        .instruction-container { padding: 8px 10px 0; }
        .exam-stats { grid-template-columns: 1fr; }
        .guest-details-grid { grid-template-columns: 1fr; }
        .stat-card { padding: 10px 12px; }
        .instructions-grid { grid-template-columns: 1fr; }
        .instruction-action-bar { grid-template-columns: 1fr; gap: 10px; padding-inline: 4px; }
        .custom-checkbox { align-items: flex-start; gap: 10px; font-size: 14px; }
        .start-help { text-align: left; }
    }
</style>

<div class="instruction-container">
    <div class="instruction-scroll" tabindex="0" aria-label="Exam instructions">
    <div class="exam-header">
        <h2>{{ $examName }}</h2>
        <p>{{ __('ui.read_instructions') }}</p>

        <div class="exam-stats">
            <div class="stat-card">
                <span class="stat-number">{{ $exam->questions_count ?? $exam->questions->count() ?? 0 }}</span>
                <span class="stat-label">{{ __('ui.questions') }}</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">{{ $exam->duration ?? 0 }}</span>
                <span class="stat-label">{{ __('messages.exam_sidebar_minutes') }}</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">{{ $exam->questions->sum('marks') ?? 0 }}</span>
                <span class="stat-label">{{ __('ui.total_marks') }}</span>
            </div>
        </div>
    </div>

    @if($availableLanguages->count() > 1)
    <div class="language-card">
        <h5>{{ __('ui.select_preferred_language') }}</h5>
        <select id="languageSelect" class="language-selector">
            @foreach($availableLanguages as $lang)
                <option value="{{ $lang->id }}" data-english="{{ $languageService->isEnglish($lang) ? '1' : '0' }}" {{ $selectedLanguage && $selectedLanguage->id === $lang->id ? 'selected' : '' }}>
                    {{ $lang->name }}
                </option>
            @endforeach
        </select>
        <small id="translationStatus" class="info-text">{{ $translationReady ? 'Questions will use this saved language.' : 'Translation will start in batches of five questions.' }}</small>
    </div>
    @endif

    @if($mode === 'guest')
    <div class="language-card">
        <h5>{{ __('ui.save_result_details') }} <span class="text-muted fw-normal">(Optional)</span></h5>
        <p class="text-muted mb-3">{{ __('ui.save_result_copy') }}</p>
        <div class="guest-details-grid">
            <div>
                <label for="guestName" class="form-label">{{ __('messages.auth_label_name') }}</label>
                <input type="text" id="guestName" class="form-control" maxlength="120" value="{{ session('guest_name') }}" placeholder="Your name">
            </div>
            <div>
                <label for="guestEmail" class="form-label">{{ __('messages.profile_label_email') }}</label>
                <input type="email" id="guestEmail" class="form-control" maxlength="150" value="{{ session('guest_email') }}" placeholder="you@example.com">
            </div>
        </div>
    </div>
    @endif

    <div class="instructions-card">
        <h5>{{ __('ui.important_instructions') }}</h5>
        @if(!empty($examInstruction))
            <div class="mb-4">{!! $examInstruction !!}</div>
        @endif
        <div class="instructions-grid">
            <div class="instruction-item"><span class="instruction-icon">1</span><span>{{ __('ui.instr_close_tabs') }}</span></div>
            <div class="instruction-item"><span class="instruction-icon">2</span><span>{{ __('ui.instr_read_question') }}</span></div>
            @if((bool) ($exam->allow_answer_change ?? true))
                <div class="instruction-item"><span class="instruction-icon">3</span><span>{{ __('ui.instr_change_saved') }}</span></div>
            @else
                <div class="instruction-item"><span class="instruction-icon">3</span><span>{{ __('ui.instr_answers_lock') }}</span></div>
            @endif
            <div class="instruction-item"><span class="instruction-icon">4</span><span>{{ __('ui.instr_stable_internet') }}</span></div>
            <div class="instruction-item"><span class="instruction-icon">5</span><span>{{ __('ui.instr_no_refresh') }}</span></div>
            <div class="instruction-item"><span class="instruction-icon">6</span><span>{{ __('ui.instr_submit_time') }}</span></div>
        </div>
    </div>

    @if($exam->proctor)
        <div class="instructions-card camera-card" id="cameraCard" data-camera-state="idle">
            <h5>{{ __('ui.camera_required') }}</h5>
            <div class="camera-status" role="status" aria-live="polite">
                <span class="camera-status-icon" aria-hidden="true"><i class="fas fa-video"></i></span>
                <div><strong id="cameraStatusText">{{ __('ui.camera_allow_continue') }}</strong><p id="cameraMessage" class="text-muted fs-14 mb-0 mt-1">{{ __('ui.camera_tap_allow') }}</p></div>
            </div>
            <div class="camera-preview"><video id="webcam" autoplay playsinline muted></video></div>
            <button type="button" id="enableCameraBtn" class="camera-enable-btn"><i class="fas fa-camera me-2" aria-hidden="true"></i> {{ __('ui.allow_camera') }}</button>
            <div class="camera-help"><strong>{{ __('ui.camera_blocked') }}</strong><ol><li>{{ __('ui.camera_step_site_settings') }}</li><li>{{ __('ui.camera_step_permission') }}</li><li>{{ __('ui.camera_step_retry') }}</li></ol></div>
        </div>
    @endif

    <div class="warning-card">
        <strong>{{ __('ui.important_notice') }}</strong>
        @if((bool) ($exam->allow_answer_change ?? true))
            <p class="mb-0 mt-2">{{ __('ui.notice_can_change') }}</p>
        @else
            <p class="mb-0 mt-2">{{ __('ui.notice_locked') }}</p>
        @endif
    </div>

    </div>

    <div class="instruction-action-bar">
        <div class="agree-card"><label class="custom-checkbox"><input type="checkbox" id="instruction_check" checked><span>{{ __('ui.agree_instructions') }}</span></label></div>
        <div class="start-action"><small id="startButtonHelp" class="start-help" aria-live="polite">{{ __('ui.confirm_read') }}</small><button id="start_exam_btn" class="start-btn" disabled>{{ __('ui.start_exam_now') }}</button></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const proctor = {{ $exam->proctor ? 'true' : 'false' }};
    const startExamBtn = document.getElementById('start_exam_btn');
    const instructionCheck = document.getElementById('instruction_check');
    const languageSelect = document.getElementById('languageSelect');
    const translationStatus = document.getElementById('translationStatus');
    const translationEndpointTemplate = @json($translationEndpointTemplate);
    const startButtonHelp = document.getElementById('startButtonHelp');
    const cameraCard = document.getElementById('cameraCard');
    const enableCameraBtn = document.getElementById('enableCameraBtn');
    const cameraStatusText = document.getElementById('cameraStatusText');
    const cameraMessage = document.getElementById('cameraMessage');
    const video = document.getElementById('webcam');
    let translationReady = @json($translationReady);
    let translationRunning = false;
    let webcamEnabled = !proctor;
    let webcamStream = null;

    function updateStartButtonState() {
        startExamBtn.disabled = !instructionCheck.checked || !webcamEnabled || !translationReady || translationRunning;
        if (!instructionCheck.checked) startButtonHelp.textContent = 'Confirm that you have read the instructions.';
        else if (!webcamEnabled) startButtonHelp.textContent = 'Allow camera access before starting this proctored exam.';
        else if (translationRunning || !translationReady) startButtonHelp.textContent = 'Please wait while your selected language is prepared.';
        else startButtonHelp.textContent = 'Everything is ready. You can start the exam.';
    }

    function setCameraState(state, title, message) {
        if (!cameraCard) return;
        cameraCard.dataset.cameraState = state;
        cameraStatusText.textContent = title;
        cameraMessage.textContent = message;
        enableCameraBtn.disabled = state === 'checking';
        enableCameraBtn.innerHTML = state === 'checking'
            ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span> Checking Camera...'
            : '<i class="fas fa-camera me-2" aria-hidden="true"></i>' + (state === 'denied' || state === 'error' ? 'Try Camera Again' : 'Allow Camera');
    }

    async function requestCamera() {
        webcamEnabled = false;
        updateStartButtonState();
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setCameraState('error', 'Camera is unavailable in this browser', 'Open this exam in Chrome, Edge, or Safari using a secure HTTPS connection.');
            return;
        }
        setCameraState('checking', 'Waiting for camera permission', 'Choose Allow when your browser asks to use the camera.');
        try {
            webcamStream?.getTracks().forEach(track => track.stop());
            webcamStream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 480}}, audio: false});
            video.srcObject = webcamStream;
            await video.play().catch(() => {});
            webcamEnabled = true;
            setCameraState('ready', 'Camera is ready', 'Your camera works. Confirm the instructions to start.');
        } catch (error) {
            const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
            const missing = error?.name === 'NotFoundError' || error?.name === 'DevicesNotFoundError';
            const busy = error?.name === 'NotReadableError' || error?.name === 'TrackStartError';
            if (denied) setCameraState('denied', 'Camera permission is blocked', 'Allow the camera in site settings, then try again.');
            else if (missing) setCameraState('error', 'No camera was found', 'Connect or enable a camera, then try again.');
            else if (busy) setCameraState('error', 'Camera is being used by another app', 'Close other video apps or tabs, then try again.');
            else setCameraState('error', 'Camera could not start', 'Check your camera and browser settings, then try again.');
        } finally {
            updateStartButtonState();
        }
    }

    if (proctor) {
        enableCameraBtn.addEventListener('click', requestCamera);
        navigator.permissions?.query({name: 'camera'}).then(permission => {
            if (permission.state === 'granted') requestCamera();
            if (permission.state === 'denied') setCameraState('denied', 'Camera permission is blocked', 'Allow the camera in site settings, then try again.');
            permission.addEventListener?.('change', () => {
                if (permission.state === 'granted' && !webcamEnabled) requestCamera();
            });
        }).catch(() => {});
    }

    window.addEventListener('pagehide', () => webcamStream?.getTracks().forEach(track => track.stop()));

    instructionCheck.addEventListener('change', updateStartButtonState);

    async function prepareSelectedLanguage() {
        if (!languageSelect || translationRunning) return;
        const option = languageSelect.selectedOptions[0];
        if (!option) return;
        if (option.dataset.english === '1') {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', option.value);
            window.location.href = url.toString();
            return;
        }

        translationRunning = true;
        translationReady = false;
        updateStartButtonState();
        if (translationStatus) translationStatus.textContent = 'Preparing this language in batches of five questions…';
        try {
            while (true) {
                const endpoint = translationEndpointTemplate.replace('__LANGUAGE__', encodeURIComponent(option.value));
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Translation could not be completed.');
                if (translationStatus) translationStatus.textContent = `Translated ${data.translated || 0} of ${data.total || 0} questions…`;
                if (data.status === 'ready' || ((data.remaining || 0) === 0 && data.exam_content_ready)) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('lang', option.value);
                    window.location.href = url.toString();
                    return;
                }
                if (data.status === 'processing') await new Promise(resolve => setTimeout(resolve, 1000));
            }
        } catch (error) {
            translationRunning = false;
            translationReady = false;
            if (translationStatus) translationStatus.textContent = error.message;
            updateStartButtonState();
        }
    }

    languageSelect?.addEventListener('change', prepareSelectedLanguage);
    if (languageSelect && !translationReady) prepareSelectedLanguage();

    startExamBtn.addEventListener('click', function() {
        if (startExamBtn.disabled) return;

        const lang = languageSelect ? languageSelect.value : '';
        const guestName = document.getElementById('guestName') ? document.getElementById('guestName').value.trim() : '';
        const guestEmail = document.getElementById('guestEmail') ? document.getElementById('guestEmail').value.trim() : '';
        const startUrl = @json(route($startRoute, ['id' => $examKey]));
        const params = new URLSearchParams();

        if (lang) params.set('lang', lang);
        if (guestName) params.set('guest_name', guestName);
        if (guestEmail) params.set('guest_email', guestEmail);

        const query = params.toString();
        window.location.href = query ? startUrl + '?' + query : startUrl;
    });

    updateStartButtonState();
});
</script>
