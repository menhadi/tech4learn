@php
    $examLanguageMode = $examLanguageMode ?? 'student';
    $examLanguageService = app(App\Services\ExamLanguageService::class);
    $examLanguageKey = $examLanguageMode === 'guest' ? ($exam->slug ?: $exam->id) : $exam->id;
    $examLanguageRoute = $examLanguageMode === 'guest'
        ? 'guest.exams.languages.translate'
        : 'student.exams.languages.translate';
    $examLanguageEndpoint = route($examLanguageRoute, [
        'id' => $examLanguageKey,
        'languageId' => '__LANGUAGE__',
    ]);
@endphp
@if(($languages ?? collect())->count() > 1)
    <div class="exam-language-control">
        <label for="examLanguageSelect" class="visually-hidden">{{ __('ui.question_language') }}</label>
        <select id="examLanguageSelect" class="form-select form-select-sm" aria-label="{{ __('ui.question_language') }}">
            @foreach($languages as $examLanguage)
                <option value="{{ $examLanguage->id }}"
                    data-ready="{{ $examLanguageService->isEnglish($examLanguage) || $examLanguage->pivot?->translation_status === 'ready' ? '1' : '0' }}"
                    @selected((int) $selectedLanguageId === (int) $examLanguage->id)>
                    {{ $examLanguage->name }}
                </option>
            @endforeach
        </select>
        <small id="examLanguageStatus" class="text-muted" aria-live="polite"></small>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('examLanguageSelect');
            const status = document.getElementById('examLanguageStatus');
            const endpointTemplate = @json($examLanguageEndpoint);
            if (!select) return;

            select.addEventListener('change', async function () {
                const selected = select.selectedOptions[0];
                if (!selected) return;
                select.disabled = true;
                window.examLanguageSwitching = true;

                try {
                    while (selected.dataset.ready !== '1') {
                        if (status) status.textContent = 'Preparing ' + selected.textContent.trim() + '…';
                        const response = await fetch(endpointTemplate.replace('__LANGUAGE__', encodeURIComponent(selected.value)), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            }
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(payload.message || 'Language preparation failed.');
                        if (status) status.textContent = `Prepared ${payload.translated || 0} of ${payload.total || 0} questions…`;
                        if (payload.status === 'ready' || ((payload.remaining || 0) === 0 && payload.exam_content_ready)) {
                            selected.dataset.ready = '1';
                        } else if (payload.status === 'processing') {
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        }
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('lang', selected.value);
                    window.location.assign(url.toString());
                } catch (error) {
                    window.examLanguageSwitching = false;
                    select.disabled = false;
                    if (status) status.textContent = error.message;
                }
            });
        });
    </script>
@endif
