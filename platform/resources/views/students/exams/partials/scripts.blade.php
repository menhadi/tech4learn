<script>
    // =========================================================================
    // ✅ TRANSLATIONS & CONFIG
    // =========================================================================
    const LANG = {
        leave_confirm: {!! json_encode(__('messages.js_leave_confirm')) !!},
        reload_confirm: {!! json_encode(__('messages.js_reload_confirm')) !!},
        action_not_allowed: {!! json_encode(__('messages.js_action_not_allowed')) !!},
        terminated_violation: {!! json_encode(__('messages.js_terminated_violation')) !!},
        finish_failed: {!! json_encode(__('messages.js_finish_failed')) !!},
        finishing: {!! json_encode(__('messages.js_finishing')) !!},
        finish_exam: {!! json_encode(__('messages.js_finish_exam')) !!}, 
        save_button: {!! json_encode(__('messages.js_save_button')) !!},
        next_save_button: {!! json_encode(__('messages.js_next_save_button')) !!},
        no_data_language: {!! json_encode(__('messages.js_no_data_language')) !!},
        hint_prefix: {!! json_encode(__('messages.exam_hint_prefix')) !!},
        explanation_prefix: {!! json_encode(__('messages.exam_explanation_prefix')) !!},
        fullscreen_enter: {!! json_encode(__('messages.exam_header_fullscreen_enter')) !!},
        fullscreen_exit: {!! json_encode(__('messages.exam_header_fullscreen_exit')) !!},
        option_true: {!! json_encode(__('messages.exam_option_true')) !!},
        option_false: {!! json_encode(__('messages.exam_option_false')) !!},
        bookmark_title: {!! json_encode(__('messages.exam_bookmark_button_title')) !!},
        unbookmark_title: {!! json_encode(__('messages.exam_unbookmark_button_title')) !!}
    };

    document.addEventListener('DOMContentLoaded', function() {
        // --- DOM Elements ---
        const fullscreenButton = document.getElementById('fullscreenButton');
        const filterSelect = document.getElementById('filterSelect');
        const hoursElement = document.getElementById('hours');
        const minutesElement = document.getElementById('minutes');
        const secondsElement = document.getElementById('seconds');
        const prevButton = document.getElementById('prevButton');
        const nextButton = document.getElementById('nextButton');
        const questions = document.querySelectorAll('.question');
        const questionStatuses = document.querySelectorAll('.question-status img');
        const subjectButtons = document.querySelectorAll('.subject-btn');
        const reviewButtons = document.querySelectorAll('.review-button');
        const clearButtons = document.querySelectorAll('.clear-button');
        const bookmarkButtons = document.querySelectorAll('.bookmark-button');
        const allowAnswerChange = @json((bool) ($exam->allow_answer_change ?? true));

        // --- Safety Check ---
        if (questions.length === 0) {
            console.warn("No questions found on the page. Exam script execution halted.");
            if(nextButton) nextButton.disabled = true;
            return;
        }

        // --- Variables ---
        let currentQuestionIndex = 0;
        let subjectDurations = @json($subjectDurations ?? []); 
        let currentSubjectId = questions[currentQuestionIndex].getAttribute('data-subject-id');
        
        let subjectTimeRemaining = subjectDurations[currentSubjectId] ? parseInt(subjectDurations[currentSubjectId]) : 0;
        let examEndTime = null; 

        let questionStartTime = new Date();
        let toleranceCount = {{ $examResult->tolerance_count ?? 0 }};

        let examFinished = false;
        let isSubmitting = false;

        // --- Prevent Tab Close ---
        const beforeUnloadListener = function(event) {
            if (examFinished || isSubmitting || window.examLanguageSwitching) return;
            event.preventDefault(); 
            event.returnValue = LANG.leave_confirm;
            return LANG.leave_confirm;
        };
        window.addEventListener('beforeunload', beforeUnloadListener);


        // ========================================================
        // ✅ FONT SIZER SCRIPT
        // ========================================================
        const fontIncreaseBtn = document.getElementById('font-increase-btn');
        const fontDecreaseBtn = document.getElementById('font-decrease-btn');
        const questionContainer = document.getElementById('question-container');
        const questionModalBody = document.querySelector('#questionPaperModal .modal-body');
        const MIN_FONT_SIZE = 14; 
        const MAX_FONT_SIZE = 26;
        const STEP = 2;
        let currentSize = 16; 

        function setFontSize(size) {
            size = Math.max(MIN_FONT_SIZE, Math.min(size, MAX_FONT_SIZE));
            currentSize = size;
            if (questionContainer) { questionContainer.style.fontSize = size + 'px'; }
            if (questionModalBody) { questionModalBody.style.fontSize = size + 'px'; }
            if(fontDecreaseBtn) fontDecreaseBtn.disabled = (currentSize <= MIN_FONT_SIZE);
            if(fontIncreaseBtn) fontIncreaseBtn.disabled = (currentSize >= MAX_FONT_SIZE);
        }
        if (fontIncreaseBtn) { fontIncreaseBtn.addEventListener('click', () => { setFontSize(currentSize + STEP); }); }
        if (fontDecreaseBtn) { fontDecreaseBtn.addEventListener('click', () => { setFontSize(currentSize - STEP); }); }
        setFontSize(currentSize);


        // ========================================================
        // ✅ SECURITY FUNCTIONS
        // ========================================================
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
            showSecurityAlert(LANG.action_not_allowed);
        });

        document.addEventListener('keydown', function(event) {
            if (examFinished || isSubmitting) return;

            if (event.key === 'F5' || (event.ctrlKey && event.key === 'r') || (event.ctrlKey && event.key === 'R')) {
                 event.preventDefault();
                 return;
            }
            const otherForbiddenKeys = [
                { key: 'F12' }, 
                { key: 'u', ctrl: true }, { key: 'U', ctrl: true },
                { key: 's', ctrl: true }, { key: 'S', ctrl: true },
                { key: 'p', ctrl: true }, { key: 'P', ctrl: true },
                { key: 'i', ctrl: true, shift: true }, { key: 'I', ctrl: true, shift: true },
                { key: 'j', ctrl: true, shift: true }, { key: 'J', ctrl: true, shift: true },
                { key: 'c', ctrl: true, shift: true }, { key: 'C', ctrl: true, shift: true }
            ];
            for (let forbidden of otherForbiddenKeys) {
                if (event.key === forbidden.key &&
                    (!forbidden.ctrl || event.ctrlKey) &&
                    (!forbidden.shift || event.shiftKey)) {
                    event.preventDefault();
                    showSecurityAlert(LANG.action_not_allowed);
                    return;
                }
            }
        });
        
        let devtools = { open: false, orientation: null };
        const threshold = 160;
        const checkDevTools = () => {
            if (examFinished || isSubmitting) return;
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;
            if (widthThreshold || heightThreshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    finishExamDueToViolation('Developer tools detected');
                }
            } else {
                devtools.open = false;
            }
        };
        setInterval(checkDevTools, 1000);

        function showSecurityAlert(message) {
            const alertBox = document.createElement('div');
            alertBox.style.position = 'fixed';
            alertBox.style.top = '10px';
            alertBox.style.left = '50%';
            alertBox.style.transform = 'translateX(-50%)';
            alertBox.style.padding = '10px 20px';
            alertBox.style.background = 'red';
            alertBox.style.color = 'white';
            alertBox.style.zIndex = '9999';
            alertBox.style.borderRadius = '5px';
            alertBox.textContent = message;
            document.body.appendChild(alertBox);
            setTimeout(() => {
                alertBox.remove();
            }, 2000);
        }

        function finishExamDueToViolation(reason) {
            if (examFinished || isSubmitting) return;
            isSubmitting = true;
            examFinished = true;
            
            window.removeEventListener('beforeunload', beforeUnloadListener);

            fetch("{{ route('student.finishExam') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        exam_result_id: {{ $examResult->id }},
                        violation_reason: reason,
                        forced_finish: true
                    })
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || LANG.finish_failed);
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        window.location.href = `{!! route('student.examFeedback', ['exam_result_id' => $examResult->id, 'result_after_finish' => $exam->result_after_finish]) !!}`;
                    } else {
                        alert(LANG.terminated_violation + ' ' + reason);
                        window.location.href = "{{ route('student.dashboard') }}";
                    }
                })
                .catch(error => {
                    alert(LANG.terminated_violation + ' ' + reason);
                    window.location.href = "{{ route('student.dashboard') }}";
                });
        }

        // ========================================================
        // ✅ TIMER LOGIC (FIXED FOR MINIMIZED TABS)
        // ========================================================
        
        function initTimer(durationInSeconds) {
            const now = Date.now();
            examEndTime = now + (durationInSeconds * 1000);
        }

        async function updateSubjectTimer() {
            if (examFinished) return;
            if (typeof subjectTimeRemaining === 'undefined' || subjectTimeRemaining === null) return;

            const now = Date.now();
            const distance = examEndTime - now;
            
            subjectTimeRemaining = distance > 0 ? Math.floor(distance / 1000) : 0;

            const hours = Math.floor(subjectTimeRemaining / 3600);
            const minutes = Math.floor((subjectTimeRemaining % 3600) / 60);
            const seconds = Math.floor(subjectTimeRemaining % 60);
            
            if (hoursElement) hoursElement.textContent = hours.toString().padStart(2, '0');
            if (minutesElement) minutesElement.textContent = minutes.toString().padStart(2, '0');
            if (secondsElement) secondsElement.textContent = seconds.toString().padStart(2, '0');
            
            if (subjectTimeRemaining > 0) {
                setTimeout(updateSubjectTimer, 1000);
            } else {
                if(nextButton) nextButton.disabled = true; 
                
                try {
                    await saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex), true);
                } catch(e) {}

                const nextSubjectId = getNextSubjectId(currentSubjectId);
                
                if (nextSubjectId && subjectDurations[nextSubjectId] !== undefined) {
                    currentSubjectId = nextSubjectId;
                    
                    let newDuration = parseInt(subjectDurations[currentSubjectId]);
                    subjectTimeRemaining = newDuration;
                    
                    initTimer(newDuration);
                    
                    const newIndex = getFirstQuestionIndexBySubject(currentSubjectId);
                    showQuestion(newIndex);
                    
                    const h = Math.floor(subjectTimeRemaining / 3600);
                    const m = Math.floor((subjectTimeRemaining % 3600) / 60);
                    const s = Math.floor(subjectTimeRemaining % 60);
                    if (hoursElement) hoursElement.textContent = h.toString().padStart(2, '0');
                    if (minutesElement) minutesElement.textContent = m.toString().padStart(2, '0');
                    if (secondsElement) secondsElement.textContent = s.toString().padStart(2, '0');

                    setTimeout(updateSubjectTimer, 1000); 
                } else {
                    finishExam();
                }
            }
        }

        function getNextSubjectId(subjectId) {
            const currentStrId = String(subjectId);
            const subjectIds = Array.from(new Set(Array.from(questions).map(q => String(q.getAttribute('data-subject-id')))));
            const currentIndex = subjectIds.indexOf(currentStrId);
            return subjectIds[currentIndex + 1] || null;
        }

        function getFirstQuestionIndexBySubject(subjectId) {
            return Array.from(questions).findIndex(question => question.getAttribute('data-subject-id') == subjectId);
        }

        async function finishExam() {
            if (examFinished || isSubmitting) return;
            isSubmitting = true;
            examFinished = true;
            window.removeEventListener('beforeunload', beforeUnloadListener);
            
            try {
                await saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex));
            } catch (e) {}

            fetch("{{ route('student.finishExam') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ exam_result_id: {{ $examResult->id }} })
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || LANG.finish_failed);
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        window.location.href = `{!! route('student.examFeedback', ['exam_result_id' => $examResult->id, 'result_after_finish' => $exam->result_after_finish]) !!}`;
                    } else {
                        alert(data.message || LANG.finish_failed);
                        isSubmitting = false;
                        examFinished = false;
                    }
                })
                .catch(error => {
                    alert(error.message || LANG.finish_failed);
                    isSubmitting = false;
                    examFinished = false;
                });
        }


        // ========================================================
        // ✅ TIMER INITIALIZATION
        // ========================================================
        const isSubjectTimer = @json($exam->is_subject_timer);
        const hasSubjectDurations = Object.keys(subjectDurations).length > 0;
        let overallDuration = {{ $remainingTime ?? 0 }};

        if (isSubjectTimer && hasSubjectDurations) {
            initTimer(subjectTimeRemaining);
            updateSubjectTimer();
        } else if (overallDuration > 0) {
            initTimer(overallDuration);
            
            function updateOverallTimer() {
                if (examFinished) return;
                
                const now = Date.now();
                const distance = examEndTime - now;
                overallDuration = distance > 0 ? Math.floor(distance / 1000) : 0;

                const hours = Math.floor(overallDuration / 3600);
                const minutes = Math.floor((overallDuration % 3600) / 60);
                const seconds = Math.floor(overallDuration % 60);
                
                if (hoursElement) hoursElement.textContent = hours.toString().padStart(2, '0');
                if (minutesElement) minutesElement.textContent = minutes.toString().padStart(2, '0');
                if (secondsElement) secondsElement.textContent = seconds.toString().padStart(2, '0');
                
                if (overallDuration > 0) {
                    setTimeout(updateOverallTimer, 1000);
                } else {
                    finishExam();
                }
            }
            updateOverallTimer();
        }


        // ========================================================
        // ✅ HELPER FUNCTIONS
        // ========================================================
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function getNextQuestionIndexWithinSubject(currentIndex) {
            const currentSubjectId = questions[currentIndex].getAttribute('data-subject-id');
            for (let i = currentIndex + 1; i < questions.length; i++) {
                if (questions[i].getAttribute('data-subject-id') == currentSubjectId) { return i; }
            }
            return -1;
        }

        function getPrevQuestionIndexWithinSubject(currentIndex) {
            const currentSubjectId = questions[currentIndex].getAttribute('data-subject-id');
            for (let i = currentIndex - 1; i >= 0; i--) {
                if (questions[i].getAttribute('data-subject-id') == currentSubjectId) { return i; }
            }
            return -1;
        }

        function applyAnswerLock(index) {
            const question = questions[index];
            if (!question) return;
            const isLocked = !allowAnswerChange && question.dataset.answerLocked === '1';
            question.querySelectorAll('.answer-input').forEach(input => { input.disabled = isLocked; });
            question.querySelectorAll('.clear-button:not(.report-btn), .upload-single-btn').forEach(button => { button.disabled = isLocked; });
            const notice = question.querySelector('.answer-lock-notice');
            if (notice) notice.hidden = !isLocked;
        }
        // ✅ SHOW QUESTION & BACK BUTTON FIX
        function showQuestion(index) {
            if (index < 0 || index >= questions.length) return; 

            questions.forEach((question, i) => {
                question.style.display = i == index ? 'block' : 'none';
            });

            currentQuestionIndex = index;
            const currentSubjectId = questions[index].getAttribute('data-subject-id');
            updateSubjectButtonClasses(currentSubjectId);

            // Subject Locking
            if (Object.keys(subjectDurations).length > 0) {
                subjectButtons.forEach(button => {
                    button.disabled = (button.getAttribute('data-subject-id') != currentSubjectId);
                });

                document.querySelectorAll('.question-status').forEach(statusDiv => {
                    const questionIndex = statusDiv.getAttribute('data-index');
                    const questionSubjectId = questions[questionIndex].getAttribute('data-subject-id');
                    if (questionSubjectId != currentSubjectId) {
                        statusDiv.style.pointerEvents = 'none'; 
                        statusDiv.style.opacity = '0.5'; 
                    } else {
                        statusDiv.style.pointerEvents = 'auto'; 
                        statusDiv.style.opacity = '1'; 
                    }
                });
            }

            // Navigation Button Logic
            if (Object.keys(subjectDurations).length === 0) {
                // Normal Mode
                if (prevButton) prevButton.disabled = index == 0;
                if (nextButton) {
                    nextButton.innerHTML = index == questions.length - 1 ? LANG.save_button : LANG.next_save_button;
                }
            } else {
                // Subject Timer Mode
                const prevIndex = getPrevQuestionIndexWithinSubject(index);
                if (prevButton) prevButton.disabled = (prevIndex === -1); 
                
                const nextIndex = getNextQuestionIndexWithinSubject(index);
                if (nextButton) {
                    nextButton.innerHTML = nextIndex === -1 ? LANG.save_button : LANG.next_save_button;
                    nextButton.disabled = (nextIndex === -1); 
                }
            }
            
            const paletteImg = document.querySelector(`.question-status[data-index="${index}"] img`);
            if (paletteImg && paletteImg.src.includes('not_visited.svg')) {
                updateQuestionPalette(index, 'not_answered');
                updateLegend();
            }
            questionStartTime = new Date();

            // ✅✅✅ TARGETED MATH FIX START ✅✅✅
            const activeQuestion = questions[index];
            applyAnswerLock(index);
            
            // 1. DOM CLEANUP (Targeted ONLY for visible question)
            activeQuestion.querySelectorAll('mjx-container').forEach(function(c) {
               const mathEl = c.querySelector('math');
               if (mathEl) {
                   c.replaceWith(mathEl); 
               }
            });

            // 2. TRIGGER MathLive (agar hai)
            if (typeof MathLive !== 'undefined' && MathLive.renderMathInDocument) {
                MathLive.renderMathInDocument();
            } 
            
            // 3. TRIGGER MathJax (agar load ho chuka hai)
            if (window.MathJax && MathJax.typesetPromise) {
                MathJax.typesetClear([activeQuestion]); 
                MathJax.typesetPromise([activeQuestion]); 
            }
            // ✅✅✅ TARGETED MATH FIX END ✅✅✅
        }


        function isAnswered(index) {
            const question = questions[index];
            const inputs = question.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');
            if (inputs.length > 0) return true;
            const textInputs = question.querySelectorAll('textarea, input.fill-blank-input, input.nat-answer-input');
            if (Array.from(textInputs).some(input => input.value.trim() !== '')) return true;
            return false;
        }
        
        function getReviewStatus(index) {
             const questionStatus = document.querySelector(`.question-status[data-index="${index}"] img`);
             if (!questionStatus || !questionStatus.src) return false;
             const currentStatus = questionStatus.src.split('/').pop().split('.')[0];
             return (currentStatus === 'review' || currentStatus === 'review_answer');
        }

        function getBookmarkStatus(index) {
            const button = document.querySelector(`.bookmark-button[data-index="${index}"]`);
            if (!button) return false;
            return button.classList.contains('btn-info');
        }

        const saveAnswer = function(index, isReviewed, isBookmarked, lockAnswer = false) {
            const question = questions[index];
            if (!question) return Promise.resolve();

            const questionType = question.getAttribute('data-question-type');
            let optionSelected = null;
            if (questionType === 'true_false') {
                const checkedRadio = question.querySelector('input[type="radio"]:checked');
                optionSelected = checkedRadio ? checkedRadio.value : null;
            } else if (questionType === 'fill_blank') {
                optionSelected = Array.from(question.querySelectorAll('input.fill-blank-input')).map(input => input.value);
            } else if (questionType === 'nat') {
                const input = question.querySelector('input.nat-answer-input');
                optionSelected = input ? input.value : null;
            } else if (questionType === 'multiple_choice_checkbox') {
                optionSelected = Array.from(question.querySelectorAll('input[type="checkbox"]:checked')).map(input => input.value);
            } else if (questionType === 'multiple_choice_radio') {
                const checkedRadio = question.querySelector('input[type="radio"]:checked');
                optionSelected = checkedRadio ? checkedRadio.value : null;
            } else {
                const textarea = question.querySelector('textarea');
                optionSelected = textarea ? textarea.value : null;
            }

            const questionId = question.getAttribute('data-question-id');
            const examResultId = {{ $examResult->id }};
            const timeTaken = Math.floor((new Date() - questionStartTime) / 1000);
            const answered = isAnswered(index);

            return fetch("{{ route('student.saveAnswer') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    exam_result_id: examResultId,
                    question_id: questionId,
                    question_type: questionType,
                    option_selected: optionSelected,
                    opened: true,
                    answered: answered,
                    review: isReviewed,
                    bookmark: isBookmarked,
                    time_taken: timeTaken,
                    lock_answer: Boolean(lockAnswer)
                })
            }).then(async response => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    if (payload.answer_locked) {
                        window.alert(payload.message || 'This answer is locked and cannot be changed.');
                        window.location.reload();
                    }
                    throw new Error(payload.message || 'Unable to save the answer.');
                }
                if (payload.answer_locked) {
                    question.dataset.answerLocked = '1';
                    applyAnswerLock(index);
                }
                return payload;
            }).then(() => {
                let status = 'not_visited';
                if (isReviewed && answered) status = 'review_answer';
                else if (isReviewed) status = 'review';
                else if (answered) status = 'answered';
                else status = 'not_answered';
                updateQuestionPalette(index, status);
                updateLegend();
            }).catch(error => {
                console.error('Answer save failed:', error);
            });
        };
        const debouncedSaveAnswer = debounce(function(index, isReviewed, isBookmarked) {
            saveAnswer(index, isReviewed, isBookmarked);
        }, 500);

        // Proctoring Events
        @if ($exam->browser_tolerance && $exam->tolerance_count && $exam->tolerance_count > 0)
            document.addEventListener('visibilitychange', function() {
                if (examFinished || isSubmitting) return;
                if (document.hidden) {
                    toleranceCount++;
                    updateToleranceCount(toleranceCount);
                    const browserToleranceModal = document.getElementById('browserToleranceModal');
                    if (browserToleranceModal) {
                        let modalInstance = bootstrap.Modal.getInstance(browserToleranceModal);
                        if (!modalInstance) modalInstance = new bootstrap.Modal(browserToleranceModal);
                        modalInstance.show();
                    }
                    if (toleranceCount >= {{ $exam->tolerance_count }}) {
                        finishExamDueToViolation("Exceeded browser tolerance limit");
                    }
                }
            });
            function updateToleranceCount(count) {
                fetch("{{ route('student.updateToleranceCount') }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ exam_result_id: {{ $examResult->id }}, tolerance_count: count })
                    });
            }
        @endif

        @if ($exam->proctor)
            const webcamElement = document.getElementById('webcam');
            if(webcamElement) {
                const canvasElement = document.createElement('canvas');
                const context = canvasElement.getContext('2d');
                let captureTimer = null;

                async function startProctorCamera() {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false});
                        webcamElement.srcObject = stream;
                        document.getElementById('proctorCameraRecovery')?.remove();
                        if (!captureTimer) captureTimer = setInterval(captureImage, 30000);
                    } catch (error) {
                        console.error('Error accessing webcam:', error);
                        showProctorCameraRecovery(error);
                    }
                }

                function showProctorCameraRecovery(error) {
                    let recovery = document.getElementById('proctorCameraRecovery');
                    if (!recovery) {
                        recovery = document.createElement('div');
                        recovery.id = 'proctorCameraRecovery';
                        recovery.style.cssText = 'position:fixed;inset:12px 12px auto;z-index:1080;max-width:620px;margin:auto;padding:16px;border:2px solid #dc2626;border-radius:12px;background:#fff;color:#0f172a;box-shadow:0 18px 45px rgba(15,23,42,.25)';
                        recovery.innerHTML = '<strong style="display:block;color:#b91c1c;margin-bottom:6px">{{ __('ui.camera_required_exam') }}</strong><span class="camera-recovery-message"></span><ol style="margin:8px 0;padding-left:20px"><li>{{ __('ui.camera_step_site_settings') }}</li><li>{{ __('ui.camera_allow_close_apps') }}</li><li>{{ __('ui.camera_step_retry') }}</li></ol><button type="button" class="btn btn-danger btn-sm">{{ __('ui.try_camera_again') }}</button>';
                        recovery.querySelector('button').addEventListener('click', startProctorCamera);
                        document.body.appendChild(recovery);
                    }
                    const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
                    recovery.querySelector('.camera-recovery-message').textContent = denied
                        ? 'Camera permission is blocked in this browser.'
                        : 'The camera could not start or is being used by another app.';
                }

                startProctorCamera();
                
                function captureImage() {
                    if (examFinished) return;
                    canvasElement.width = webcamElement.videoWidth;
                    canvasElement.height = webcamElement.videoHeight;
                    context.drawImage(webcamElement, 0, 0, canvasElement.width, canvasElement.height);
                    canvasElement.toBlob(blob => {
                        const formData = new FormData();
                        formData.append('image', blob, 'webcam.jpg');
                        formData.append('exam_id', {{ $exam->id }});
                        formData.append('student_id', {{ Auth::guard('student')->id() }});
                        fetch("{{ route('student.saveProctorImage') }}", {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: formData
                            });
                    }, 'image/jpeg');
                }
            }
        @endif


        // Modals & Buttons
        function updateFinalizeModal() {
            const legendCounts = { answered: 0, not_answered: 0, not_visited: 0, review: 0, review_answer: 0 };
            questionStatuses.forEach(img => {
                if(img && img.src) {
                    const status = img.src.split('/').pop().split('.')[0];
                    if (legendCounts.hasOwnProperty(status)) {
                        legendCounts[status]++;
                    }
                }
            });
            document.getElementById('finalize-legend-answered').textContent = legendCounts.answered;
            document.getElementById('finalize-legend-not_answered').textContent = legendCounts.not_answered;
            document.getElementById('finalize-legend-not_visited').textContent = legendCounts.not_visited;
            document.getElementById('finalize-legend-review').textContent = legendCounts.review;
            document.getElementById('finalize-legend-review_answer').textContent = legendCounts.review_answer;
        }
        
        const finalizeExamModal = document.getElementById('finalizeExamModal');
        if (finalizeExamModal) {
            finalizeExamModal.addEventListener('show.bs.modal', updateFinalizeModal);
        }
        const returnToFirstQuestionButton = document.getElementById('returnToFirstQuestion');
        if (returnToFirstQuestionButton) {
            returnToFirstQuestionButton.addEventListener('click', function() {
                showQuestion(0);
                const modalInstance = bootstrap.Modal.getInstance(finalizeExamModal);
                if(modalInstance) modalInstance.hide();
            });
        }
        const finishExamButton = document.getElementById('finishExamButton');
        if (finishExamButton) {
            finishExamButton.addEventListener('click', function() {
                finishExamButton.disabled = true;
                finishExamButton.innerHTML = `<i class="mdi mdi-loading mdi-spin"></i> ${LANG.finishing}`;
                document.querySelectorAll('#finalizeExamModal .modal-footer button').forEach(button => { button.disabled = true; });
                finishExam();
            });
        }
        if (fullscreenButton) {
            fullscreenButton.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                    this.textContent = LANG.fullscreen_exit;
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                        this.textContent = LANG.fullscreen_enter;
                    }
                }
            });
        }

        if (filterSelect) {
            filterSelect.addEventListener('change', debounce(function() {
                const filterValue = this.value;
                const activeSubjectButton = document.querySelector('.subject-btn.btn-primary');
                const currentSubjectId = activeSubjectButton ? activeSubjectButton.getAttribute('data-subject-id') : null;
                
                document.querySelectorAll('.question-status').forEach(statusDiv => {
                    const statusImg = statusDiv.querySelector('img');
                    if (!statusImg || !statusImg.src) return;
                    const questionIndex = statusDiv.getAttribute('data-index');
                    const questionSubjectId = questions[questionIndex].getAttribute('data-subject-id');
                    const questionStatus = statusImg.src.split('/').pop().split('.')[0];
                    const subjectMatch = !currentSubjectId || questionSubjectId == currentSubjectId;
                    const filterMatch = filterValue === 'all' || questionStatus === filterValue;
                    statusDiv.style.display = (subjectMatch && filterMatch) ? 'block' : 'none';
                });
            }, 250));
        }

        function updateSubjectButtonClasses(subjectId) {
            subjectButtons.forEach(button => {
                if (button.getAttribute('data-subject-id') == subjectId) {
                    button.classList.remove('btn-outline-primary');
                    button.classList.add('btn-primary');
                } else {
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-outline-primary');
                }
            });
            if(filterSelect) filterSelect.dispatchEvent(new Event('change'));
        }

        function updateQuestionPalette(index, status) {
            const questionStatus = document.querySelector(`.question-status[data-index="${index}"] img`);
            if (!questionStatus) return;
            const statusMap = {
                not_visited: 'not_visited.svg',
                not_answered: 'not_answered.svg',
                answered: 'answered.svg',
                review: 'review.svg',
                review_answer: 'review_answer.svg'
            };
            questionStatus.src = `{{ asset('assets/images/exam-status/') }}/${statusMap[status]}`;
        }

        function updateLegend() {
            const legendCounts = { answered: 0, not_answered: 0, not_visited: 0, review: 0, review_answer: 0 };
            questionStatuses.forEach(img => {
                if(img && img.src) {
                    const status = img.src.split('/').pop().split('.')[0];
                    if (legendCounts.hasOwnProperty(status)) {
                         legendCounts[status]++;
                    }
                }
            });
            document.querySelectorAll('.legend-answered').forEach(el => el.textContent = legendCounts.answered);
            document.querySelectorAll('.legend-not_answered').forEach(el => el.textContent = legendCounts.not_answered);
            document.querySelectorAll('.legend-not_visited').forEach(el => el.textContent = legendCounts.not_visited);
            document.querySelectorAll('.legend-review').forEach(el => el.textContent = legendCounts.review);
            document.querySelectorAll('.legend-review_answer').forEach(el => el.textContent = legendCounts.review_answer);
        }

        // Navigation Event Listeners
        if (prevButton) {
            prevButton.addEventListener('click', function() {
                let prevIndex = -1;
                if (Object.keys(subjectDurations).length === 0) {
                    prevIndex = currentQuestionIndex - 1;
                } else {
                    prevIndex = getPrevQuestionIndexWithinSubject(currentQuestionIndex);
                }
                saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex), prevIndex !== -1);
                if (prevIndex !== -1) {
                    showQuestion(prevIndex);
                }
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function() {
                let nextIndex = -1;
                if (Object.keys(subjectDurations).length === 0) {
                    nextIndex = (currentQuestionIndex < questions.length - 1) ? currentQuestionIndex + 1 : -1;
                } else {
                    nextIndex = getNextQuestionIndexWithinSubject(currentQuestionIndex);
                }
                saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex), nextIndex !== -1);
                if (nextIndex !== -1) {
                    showQuestion(nextIndex);
                }
            });
        }
        
        reviewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                const answered = isAnswered(index);
                const questionStatusImg = document.querySelector(`.question-status[data-index="${index}"] img`);
                let currentStatus = 'not_visited';
                if (questionStatusImg && questionStatusImg.src) currentStatus = questionStatusImg.src.split('/').pop().split('.')[0];
                let newStatus;
                let isReviewedNew;
                if (currentStatus === 'review' || currentStatus === 'review_answer') {
                    isReviewedNew = false;
                } else {
                    isReviewedNew = true;
                }
                const isBookmarked = getBookmarkStatus(index);
                saveAnswer(index, isReviewedNew, isBookmarked);
            });
        });
        
        bookmarkButtons.forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                this.classList.toggle('btn-info');
                this.classList.toggle('btn-outline-info');
                const isBookmarkedNew = this.classList.contains('btn-info');
                this.title = isBookmarkedNew ? LANG.unbookmark_title : LANG.bookmark_title;
                const isReviewed = getReviewStatus(index);
                saveAnswer(index, isReviewed, isBookmarkedNew);
            });
        });

        clearButtons.forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                const question = questions[index];
                question.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => { input.checked = false; });
                question.querySelectorAll('textarea, input.fill-blank-input, input.nat-answer-input').forEach(input => { input.value = ''; });
                const isReviewed = getReviewStatus(index);
                const isBookmarked = getBookmarkStatus(index);
                saveAnswer(index, isReviewed, isBookmarked);
            });
        });

        subjectButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (Object.keys(subjectDurations).length > 0) return; 
                const subjectId = this.getAttribute('data-subject-id');
                const firstQuestionIndex = getFirstQuestionIndexBySubject(subjectId);
                if (firstQuestionIndex !== -1 && firstQuestionIndex !== currentQuestionIndex) {
                    saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex), true);
                    showQuestion(firstQuestionIndex);
                }
            });
        });

        const questionStatusContainer = document.querySelector('#questionPalette');
        if (questionStatusContainer) {
            questionStatusContainer.addEventListener('click', function(event) {
                const status = event.target.closest('.question-status');
                if (status) {
                    const index = parseInt(status.getAttribute('data-index'));
                    if (Object.keys(subjectDurations).length > 0) {
                        const questionSubjectId = questions[index].getAttribute('data-subject-id');
                        if (questionSubjectId != currentSubjectId) return; 
                    }
                    if(index !== currentQuestionIndex) {
                        saveAnswer(currentQuestionIndex, getReviewStatus(currentQuestionIndex), getBookmarkStatus(currentQuestionIndex), true);
                        showQuestion(index);
                    }
                }
            });
        }
        
        // --- Initialize ---
        showQuestion(currentQuestionIndex);
        updateLegend();

        // ========================================================
        // ✅ LANGUAGE SWITCHER FIX (Targeted Render)
        // ========================================================
        document.querySelectorAll(".view-in-select").forEach(selectBox => {
            selectBox.addEventListener("change", function() {
                const langId = this.value;
                const questionId = this.options[this.selectedIndex].getAttribute("data-question");
                if (!questionId || !langId) return;
                
                fetch(`{{ url('questions') }}/${questionId}/language/${langId}`, {
                        method: "GET",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" }
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.status) {
                            const data = response.data;
                            const questionDiv = selectBox.closest('.question'); 
                            
                            // 1. Update Question Text
                            const questionBox = questionDiv.querySelector(".question-box");
                            if(questionBox) questionBox.innerHTML = data.question ?? '';

                            // 2. Update Hint
                            const hintBox = questionDiv.querySelector(".hint-box");
                            if (hintBox) {
                                if(data.hint) {
                                    hintBox.innerHTML = `<em><strong>${LANG.hint_prefix}</strong> ${data.hint}</em>`;
                                    hintBox.style.display = 'block';
                                } else {
                                    hintBox.style.display = 'none';
                                }
                            }

                            // 3. Update Passage
                            const passageBox = questionDiv.querySelector(".passage-box");
                            if (passageBox) {
                                if(data.passage) {
                                    let content = '';
                                    if(data.passage_name) content += `<p class="fw-semibold text-center small mb-1">${data.passage_name}</p>`;
                                    content += data.passage;
                                    passageBox.innerHTML = content;
                                    passageBox.style.display = 'block';
                                } else {
                                    passageBox.style.display = 'none';
                                }
                            }

                            // 4. Update Options
                            const optionsContainer = questionDiv.querySelector(".options-container");
                            if (optionsContainer) {
                                if (data.question_type === 'T') { 
                                    const labelTrue = optionsContainer.querySelector("label[for*='_true']");
                                    const labelFalse = optionsContainer.querySelector("label[for*='_false']");
                                    if (labelTrue) labelTrue.textContent = LANG.option_true;
                                    if (labelFalse) labelFalse.textContent = LANG.option_false;
                                } 
                                else { 
                                    const options = [data.option1, data.option2, data.option3, data.option4, data.option5, data.option6];
                                    const labels = optionsContainer.querySelectorAll(".form-check-label");
                                    
                                    labels.forEach((label, i) => {
                                        const optionDiv = label.closest('.form-check');
                                        const input = optionDiv.querySelector('input');
                                        
                                        if (options[i] && options[i] !== null && options[i] !== '') {
                                            label.innerHTML = options[i]; 
                                            if(input) input.value = options[i]; 
                                            optionDiv.style.display = 'block'; 
                                        } else {
                                            optionDiv.style.display = 'none';
                                        }
                                    });
                                }
                            }

                            // ✅✅✅ TARGETED MATH FIX FOR LANGUAGE CHANGE ✅✅✅
                            
                            // 1. DOM CLEANUP (Targeted only to current questionDiv)
                            questionDiv.querySelectorAll('mjx-container').forEach(function(c) {
                               const mathEl = c.querySelector('math');
                               if (mathEl) {
                                   c.replaceWith(mathEl);
                               }
                            });

                            // 2. TRIGGER MathLive
                            if (typeof MathLive !== 'undefined' && MathLive.renderMathInDocument) {
                                MathLive.renderMathInDocument();
                            } 
                            
                            // 3. TRIGGER MathJax
                            if (window.MathJax && MathJax.typesetPromise) {
                                MathJax.typesetClear([questionDiv]);
                                MathJax.typesetPromise([questionDiv]);
                            }
                        }
                    })
                    .catch(error => console.error("Error fetching language:", error));
            });
        });
        function applySelectedLanguageToAllQuestions() {
            const selects = Array.from(document.querySelectorAll('.view-in-select'))
                .filter(selectBox => selectBox.value && selectBox.value !== '1');

            let index = 0;

            function runNext() {
                if (index >= selects.length) return;

                selects[index].dispatchEvent(new Event('change', { bubbles: true }));
                index++;

                // Keep requests controlled instead of firing every question at once.
                setTimeout(runNext, 1200);
            }

            runNext();
        }

        setTimeout(applySelectedLanguageToAllQuestions, 800);
        // --- Input Listeners ---
        document.querySelectorAll('textarea.answer-input, input.fill-blank-input, input.nat-answer-input').forEach(textarea => {
            textarea.addEventListener('input', function() { 
                const questionDiv = this.closest('.question');
                if (questionDiv) {
                    const index = parseInt(questionDiv.getAttribute('data-index'));
                    if (typeof getReviewStatus === 'function' && typeof getBookmarkStatus === 'function' && typeof debouncedSaveAnswer === 'function') {
                         const isReviewed = getReviewStatus(index);
                         const isBookmarked = getBookmarkStatus(index);
                         debouncedSaveAnswer(index, isReviewed, isBookmarked);
                    }
                }
            });
        });
        
        document.querySelectorAll('.answer-input[type="radio"], .answer-input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', function() {
                 const questionDiv = this.closest('.question');
                if (questionDiv) {
                    const index = parseInt(questionDiv.getAttribute('data-index'));
                    if (typeof getReviewStatus === 'function' && typeof getBookmarkStatus === 'function' && typeof debouncedSaveAnswer === 'function') {
                         const isReviewed = getReviewStatus(index);
                         const isBookmarked = getBookmarkStatus(index);
                         debouncedSaveAnswer(index, isReviewed, isBookmarked);
                    }
                }
            });
        });

        document.querySelectorAll('.options-container .form-check').forEach(optionRow => {
            optionRow.addEventListener('click', function(event) {
                if (event.target.matches('input, label, a, button')) {
                    return;
                }

                const input = this.querySelector('.answer-input[type="radio"], .answer-input[type="checkbox"]');
                if (input && !input.disabled) {
                    input.click();
                }
            });
        });

        // ========================================================
        // ✅ SCIENTIFIC CALCULATOR LOGIC
        // ========================================================
        const calcBtn = document.getElementById('btn-calculator');
        const calcModal = document.getElementById('scientific-calculator');
        const display = document.getElementById('calc-display');

        if (calcBtn && calcModal) {
            calcBtn.addEventListener('click', function() {
                if (calcModal.style.display === 'none') {
                    calcModal.style.display = 'block';
                } else {
                    calcModal.style.display = 'none';
                }
            });

            window.toggleCalculator = function() {
                calcModal.style.display = 'none';
            };

            window.calcCmd = function(val) {
                if (val === 'C') {
                    display.value = '';
                } else if (val === 'sin' || val === 'cos' || val === 'tan' || val === 'log' || val === 'ln' || val === 'sqrt') {
                    display.value += val + '(';
                } else {
                    display.value += val;
                }
            };

            function safeCalcExpression(input) {
                const functions = {
                    sin: Math.sin,
                    cos: Math.cos,
                    tan: Math.tan,
                    log: Math.log10,
                    ln: Math.log,
                    sqrt: Math.sqrt,
                };
                const source = String(input || '').replace(/\s+/g, '');
                const tokens = source.match(/sin|cos|tan|log|ln|sqrt|\d+(?:\.\d+)?|[()+\-*/%]/g);

                if (!tokens || tokens.join('') !== source) {
                    throw new Error('Invalid expression');
                }

                let index = 0;
                const peek = () => tokens[index];
                const next = () => tokens[index++];

                function parseExpression() {
                    let value = parseTerm();
                    while (peek() === '+' || peek() === '-') {
                        const operator = next();
                        const right = parseTerm();
                        value = operator === '+' ? value + right : value - right;
                    }
                    return value;
                }

                function parseTerm() {
                    let value = parseFactor();
                    while (peek() === '*' || peek() === '/' || peek() === '%') {
                        const operator = next();
                        const right = parseFactor();
                        if ((operator === '/' || operator === '%') && right === 0) {
                            throw new Error('Division by zero');
                        }
                        if (operator === '*') value *= right;
                        if (operator === '/') value /= right;
                        if (operator === '%') value %= right;
                    }
                    return value;
                }

                function parseFactor() {
                    const token = next();

                    if (token === '+') return parseFactor();
                    if (token === '-') return -parseFactor();

                    if (functions[token]) {
                        if (next() !== '(') throw new Error('Invalid function');
                        const value = parseExpression();
                        if (next() !== ')') throw new Error('Invalid function');
                        return functions[token](value);
                    }

                    if (token === '(') {
                        const value = parseExpression();
                        if (next() !== ')') throw new Error('Invalid expression');
                        return value;
                    }

                    if (/^\d+(?:\.\d+)?$/.test(token)) {
                        return Number(token);
                    }

                    throw new Error('Invalid expression');
                }

                const result = parseExpression();
                if (index !== tokens.length || !Number.isFinite(result)) {
                    throw new Error('Invalid expression');
                }

                return Math.round((result + Number.EPSILON) * 10000000000) / 10000000000;
            }

            window.calcResult = function() {
                try {
                    display.value = safeCalcExpression(display.value);
                } catch (e) {
                    display.value = 'Error';
                }
            };

            dragElement(calcModal);

            function dragElement(elmnt) {
                var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
                const header = elmnt.querySelector('.calc-header');
                if (header) {
                    header.onmousedown = dragMouseDown;
                }

                function dragMouseDown(e) {
                    e = e || window.event;
                    e.preventDefault();
                    pos3 = e.clientX;
                    pos4 = e.clientY;
                    document.onmouseup = closeDragElement;
                    document.onmousemove = elementDrag;
                }

                function elementDrag(e) {
                    e = e || window.event;
                    e.preventDefault();
                    pos1 = pos3 - e.clientX;
                    pos2 = pos4 - e.clientY;
                    pos3 = e.clientX;
                    pos4 = e.clientY;
                    elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
                    elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
                }

                function closeDragElement() {
                    document.onmouseup = null;
                    document.onmousemove = null;
                }
            }
        }
    });

    document.addEventListener("DOMContentLoaded", function () {

        const reportButtons = document.querySelectorAll(".report-btn");

        reportButtons.forEach(btn => {
            btn.addEventListener("click", function () {

                // 👉 Get currently visible question
                const currentQuestion = document.querySelector(".question[style*='display: block']");

                if (!currentQuestion) return;

                const questionId = currentQuestion.dataset.questionId;
                const subjectId = currentQuestion.dataset.academicSubjectId || '';
                const questionType = currentQuestion.dataset.questionType;

                // 👉 Set into modal
                document.getElementById("report_question_id").value = questionId;
                document.getElementById("report_subject_id").value = subjectId;
                document.getElementById("report_question_type").value = questionType;

            });
        });

    });

    document.querySelector("#reportModal .report-submit-btn").addEventListener("click", function () {

        const btn = this;
        const typeEl = document.querySelector("#reportModal select[name='report_type']");
        const messageEl = document.querySelector("#reportModal textarea[name='message']");
        const errorElId = "report-error";

        // 🔹 Remove old error
        let oldError = document.getElementById(errorElId);
        if (oldError) oldError.remove();

        const reportType = typeEl ? typeEl.value.trim() : "";
        const message = messageEl ? messageEl.value.trim() : "";

        // Validation
        if (!reportType) {
            const errorDiv = document.createElement("div");
            errorDiv.id = errorElId;
            errorDiv.className = "text-danger mt-2";
            errorDiv.innerText = "Please select an error type";
            typeEl.after(errorDiv);
            return;
        }

        // 🔹 Disable button (prevent double click)
        btn.disabled = true;
        btn.innerText = "Submitting...";

        const data = {
            question_id: document.getElementById("report_question_id").value,
            subject_id: document.getElementById("report_subject_id").value,
            question_type: reportType,
            message: message,
            _token: "{{ csrf_token() }}"
        };

        fetch("{{ route('student.questionReport.store') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(data)
        })
        .then(async res => {
            const response = await res.json();

            if (!res.ok) {
                throw response;
            }

            return response;
        })
        .then(res => {

            // Success message
            alert("Report submitted successfully");

            // Reset form
            if (typeEl) typeEl.value = "";
            if (messageEl) messageEl.value = "";

            // Close modal
            const modalEl = document.getElementById('reportModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

        })
        .catch(err => {

            // Laravel validation errors
            if (err.errors) {
                const errorDiv = document.createElement("div");
                errorDiv.id = errorElId;
                errorDiv.className = "text-danger mt-2";
                errorDiv.innerText = Object.values(err.errors)[0][0];
                messageEl.after(errorDiv);
            } else {
                alert("Something went wrong");
            }

        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Submit";
        });

    });
</script>
