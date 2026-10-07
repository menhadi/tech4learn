@extends('layouts.master')
@section('title', 'Audit: '.$audit->exam?->name)
@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <a href="{{ route('exam-quality.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Quality audits</a>
            <h3 class="mt-2 mb-0">{{ $audit->exam?->name }}</h3>
            @php $sourceProvider = data_get($audit->options, 'source_ai_provider', data_get($audit->options, 'ai_provider', 'auto')); @endphp
            @php $academicProvider = data_get($audit->options, 'academic_ai_provider', data_get($audit->options, 'ai_provider', 'auto')); @endphp
            @php $providerLabels = ['auto' => 'Admin priority', 'claude' => 'Claude', 'chatgpt' => 'ChatGPT', 'gemini' => 'Gemini', 'deepseek' => 'DeepSeek']; @endphp
            @php $imageProvider = data_get($audit->options, 'image_ai_provider', 'auto'); @endphp
            <div class="small text-muted mt-1">Rules @if($audit->include_source) &middot; Source text: {{ $providerLabels[$sourceProvider] ?? 'Admin priority' }} (locked) @endif @if(data_get($audit->options,'include_image_audit')) &middot; Images: {{ $providerLabels[$imageProvider] ?? 'Admin priority' }} (locked) @endif @if($audit->include_ai) &middot; Academic: {{ $providerLabels[$academicProvider] ?? 'Admin priority' }} (locked) @endif @if($audit->include_visual) &middot; Browser visual @endif</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('exam-quality.preview', $audit) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Full paper preview</a>
            @if($audit->exam)
                <a class="btn btn-sm btn-primary" href="{{ route('exams.paper.edit', $audit->exam) }}"><i class="ri-edit-box-line me-1"></i>Edit full paper</a>
            @endif
            <span class="badge fs-6 bg-{{ $audit->status === 'completed' ? 'success' : ($audit->status === 'failed' ? 'danger' : ($audit->status === 'cancelled' ? 'secondary' : 'warning')) }}">{{ ucfirst(str_replace('_',' ',$audit->status)) }}</span>
            @if(in_array($audit->status,['queued','starting','running']))
                <form method="POST" action="{{ route('exam-quality.stop',$audit) }}" data-swal-confirm="Stop this audit? The current API request may finish, but no new paid batch will start.">@csrf
                    <button class="btn btn-sm btn-danger"><i class="ri-stop-circle-line me-1"></i>Stop audit</button>
                </form>
            @elseif($audit->status === 'stop_requested')
                <button class="btn btn-sm btn-outline-danger" disabled><span class="spinner-border spinner-border-sm me-1"></span>Stopping</button>
            @endif
        </div>
    </div>

    @if($audit->status === 'queued')
        <div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>The audit worker is starting automatically. This page refreshes automatically.</div>
    @elseif($audit->status === 'starting')
        <div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>A worker has claimed this audit and is preparing the paper. This page refreshes automatically.</div>
    @elseif($audit->status === 'running')
        <div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>Audit is processing: {{ $audit->checked_questions }} of {{ $audit->total_questions ?: 'pending' }} questions. This page refreshes automatically.</div>
    @elseif($audit->status === 'stop_requested')
        <div class="alert alert-warning"><span class="spinner-border spinner-border-sm me-2"></span>Stopping after the current API request. No new paid batch will start.</div>
    @elseif($audit->status === 'cancelled')
        <div class="alert alert-secondary">Audit stopped. Findings completed before cancellation were preserved.</div>
    @endif
    @if($audit->failure_message)<div class="alert alert-danger">{{ $audit->failure_message }}</div>@endif
    @if(in_array($audit->status, ['queued','starting','running','stop_requested']))
        <script>setTimeout(() => location.reload(), {{ in_array($audit->status, ['queued','starting']) ? 3000 : 10000 }})</script>
    @endif


    <form method="POST" action="{{ route('exam-quality.repairs.queue', $audit) }}" id="selected-repair-form" class="d-none">@csrf</form>
    <form method="POST" action="{{ route('exam-quality.repairs.batch-publish', $audit) }}" id="selected-publish-form" class="d-none" data-swal-confirm="Admin override: publish the selected draft changes even if audit warnings or errors remain? Previous versions will be saved and can be restored.">@csrf<input type="hidden" name="force_publish" value="1"></form>

    <div class="row g-3 mb-4">
        @foreach([
            ['Checked', $auditStats['checked'], 'primary', null, null],
            ['Passed', $auditStats['passed'], 'success', null, null],
            ['Open Warnings', $auditStats['warning_questions'], 'warning', $auditStats['warning_issues'], 'warning'],
            ['Open Errors', $auditStats['error_questions'], 'danger', $auditStats['error_issues'], 'error'],
        ] as [$label, $value, $color, $issueCount, $issueLabel])
            <div class="col-6 col-lg-3">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="text-muted">{{ $label }}</div>
                        @if($issueCount === null)
                            <div class="fs-2 fw-bold text-{{ $color }}">{{ $value }}</div>
                        @else
                            <div class="fs-2 fw-bold text-{{ $color }}">{{ $value }} <span class="fs-6 fw-semibold">{{ Str::plural('question', $value) }}</span></div>
                            <div class="small text-muted mt-1">{{ $issueCount }} open {{ Str::plural($issueLabel, $issueCount) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($releases->isNotEmpty())
    <div class="card mb-4"><div class="card-header"><h4 class="mb-1">Paper repair releases</h4><div class="text-muted">Batch publication and rollback history for this paper.</div></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Release</th><th>Questions</th><th>Status</th><th>Published</th><th></th></tr></thead><tbody>
    @foreach($releases as $release)<tr><td><strong>{{ $release->name }}</strong></td><td>{{ $release->items_count }} <span class="small text-muted">({{ $release->restored_items_count }} restored)</span></td><td><span class="badge bg-{{ $release->status==='published'?'success':'warning' }}">{{ ucfirst(str_replace('_',' ',$release->status)) }}</span></td><td>{{ optional($release->published_at)->format('d M Y, h:i A') }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('exam-quality.releases.show',$release) }}">View / restore</a></td></tr>@endforeach
    </tbody></table></div></div></div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Audit question results">
        <a href="{{ route('exam-quality.show', ['audit' => $audit, 'tab' => 'findings']) }}" class="btn {{ $activeTab === 'findings' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="ri-error-warning-line me-1"></i> Findings
            <span class="badge {{ $activeTab === 'findings' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-1">{{ $auditStats['warning_questions'] + $auditStats['error_questions'] }}</span>
        </a>
        <a href="{{ route('exam-quality.show', ['audit' => $audit, 'tab' => 'passed']) }}" class="btn {{ $activeTab === 'passed' ? 'btn-success' : 'btn-outline-success' }}">
            <i class="ri-shield-check-line me-1"></i> Passed Questions
            <span class="badge {{ $activeTab === 'passed' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $auditStats['passed'] }}</span>
        </a>
    </div>

    @if($activeTab === 'passed')
        <div class="card">
            <div class="card-header">
                <h4 class="mb-1">Passed Questions</h4>
                <div class="text-muted">Questions checked in this audit with no rule, source, browser, or academic findings. You can still inspect and edit them manually.</div>
            </div>
            <div class="card-body">
                @forelse($passedQuestions as $question)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <h5 class="mb-1">
                                    Question ID {{ $question->id }}
                                    @if($paperQuestionNumbers->has((int) $question->id))
                                        <span class="badge bg-primary ms-2">Paper Q. {{ $paperQuestionNumbers->get((int) $question->id) }}</span>
                                    @endif
                                </h5>
                                <div class="small text-muted">{{ $question->question_code }} &middot; {{ $question->qtype?->question_type }}</div>
                            </div>
                            <a class="btn btn-sm btn-primary" href="{{ route('questions.edit', $question) }}" target="_blank"><i class="ri-edit-line me-1"></i>Edit question</a>
                        </div>
                        <div class="audit-passed-preview bg-light border rounded p-3">{!! $question->question !!}</div>
                    </div>
                @empty
                    <div class="text-center py-5"><i class="ri-shield-check-line fs-1 text-success"></i><h4 class="mt-2">No passed questions</h4><p class="text-muted">Every checked question produced at least one finding, or this audit has not checked any questions yet.</p></div>
                @endforelse
            </div>
            <div class="card-footer">{{ $passedQuestions->links() }}</div>
        </div>
        <style>
            .audit-passed-preview { max-height: 18rem; overflow: auto; }
            .audit-passed-preview img, .audit-passed-preview svg, .audit-passed-preview table { max-width: 100%; height: auto; }
            .audit-passed-preview table { width: auto; }
        </style>
    @else
    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2">
                <div class="col-md-3"><select name="severity" class="form-select"><option value="">All severities</option>@foreach(['critical','error','warning','info'] as $value)<option value="{{ $value }}" @selected(request('severity')===$value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="source" class="form-select"><option value="">All reviewers</option>@foreach(['rules'=>'Rule bot','source_compare'=>'Source text','image_compare'=>'Image extraction','browser'=>'Browser bot','ai_content'=>'Academic reviewer','system'=>'System'] as $value=>$label)<option value="{{ $value }}" @selected(request('source')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="status" class="form-select"><option value="">Open findings</option><option value="all" @selected(request('status')==='all')>All statuses</option>@foreach(['resolved','false_positive','ignored'] as $value)<option value="{{ $value }}" @selected(request('status')===$value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
                <div class="col-md-3"><button class="btn btn-primary">Filter</button> <a href="{{ route('exam-quality.show',$audit) }}" class="btn btn-secondary">Reset</a></div>
            </form>
        </div>

        <div class="alert alert-info rounded-0 border-start-0 border-end-0 mb-0"><strong>Administrator publishing:</strong> Audit warnings and errors are advisory. You may publish reviewed draft changes explicitly; the previous question version is always saved for restoration.</div>
        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="select-all-visible-findings">
                <label class="form-check-label fw-semibold" for="select-all-visible-findings">Select all visible</label>
                <span class="small text-muted ms-2" id="bulk-selection-count">0 selected</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(\App\Support\SaasAccess::featureEnabled('exam_quality_ai'))
                    <span class="small text-muted align-self-center me-2">New audits prepare drafts automatically. These controls recover older, missing or failed drafts.</span>
                    <button type="submit" form="selected-repair-form" class="btn btn-sm btn-primary" id="prepare-selected" disabled><i class="ri-refresh-line me-1"></i>Prepare selected missing/failed</button>
                    <button type="submit" form="selected-publish-form" class="btn btn-sm btn-success" id="publish-selected" disabled><i class="ri-check-double-line me-1"></i>Publish selected</button>
                    <form method="POST" action="{{ route('exam-quality.repairs.queue', $audit) }}">@csrf
                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="ri-refresh-line me-1"></i>Prepare missing/failed drafts</button>
                    </form>
                    @if($readyDraftCount > 0)
                        <form method="POST" action="{{ route('exam-quality.repairs.batch-review',$audit) }}">@csrf
                            <button class="btn btn-sm btn-success" type="submit" name="all_ready" value="1"><i class="ri-file-list-3-line me-1"></i>Review full paper ({{ $readyDraftCount }})</button>
                        </form>
                    @endif
                @else
                    <span class="badge bg-secondary align-self-center">AI repair is not included in this plan</span>
                @endif
            </div>
        </div>

        <div class="card-body">
            @forelse($findingGroups as $group)
                @php $first = $group->first(); @endphp
                @php $repairDraft = $first?->question_id ? $repairDrafts->get((int) $first->question_id) : null; @endphp
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-2">
                        <div>
                            @if($first?->question)
                                <div class="form-check mb-2">
                                    @if($repairDraft && in_array($repairDraft->status, ['ready','needs_review']) && !empty($repairDraft->changed_fields))
                                        <input class="form-check-input publish-question-checkbox" type="checkbox" name="draft_ids[]" value="{{ $repairDraft->id }}" id="publish-q-{{ $first->question_id }}" form="selected-publish-form">
                                        <label class="form-check-label" for="publish-q-{{ $first->question_id }}"><span class="badge bg-{{ $repairDraft->status === 'ready' ? 'success' : 'warning' }}-subtle text-{{ $repairDraft->status === 'ready' ? 'success' : 'warning' }}"><i class="ri-check-line me-1"></i>{{ $repairDraft->status === 'ready' ? 'Ready to publish' : 'Admin override available' }}</span></label>
                                    @elseif(\App\Support\SaasAccess::featureEnabled('exam_quality_ai') && (!$repairDraft || in_array($repairDraft->status,['failed','rejected'])))
                                        <input class="form-check-input repair-question-checkbox" type="checkbox" name="question_ids[]" value="{{ $first->question_id }}" id="repair-q-{{ $first->question_id }}" form="selected-repair-form">
                                        <label class="visually-hidden" for="repair-q-{{ $first->question_id }}">Select question</label>
                                    @endif
                                </div>
                            @endif
                            @if($first?->question)
                                <h5 class="mb-1">
                                    Question ID {{ $first->question_id }}
                                    @if($paperQuestionNumbers->has((int) $first->question_id))
                                        <span class="badge bg-primary ms-2">Paper Q. {{ $paperQuestionNumbers->get((int) $first->question_id) }}</span>
                                    @endif
                                </h5>
                                <div class="small text-muted">{{ $first->question->question_code }} &middot; {{ $first->question->qtype?->question_type }}</div>
                            @else
                                <h5 class="mb-1">System finding</h5>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark">{{ $group->count() }} {{ Str::plural('issue', $group->count()) }}</span>
                            @if($first?->question)
                                @if($repairDraft)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('exam-quality.repairs.preview', $repairDraft) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Preview</a>
                                    <a class="btn btn-sm btn-{{ $repairDraft->status === 'published' ? 'success' : (in_array($repairDraft->status, ['failed','rejected']) ? 'outline-danger' : 'primary') }}" href="{{ route('exam-quality.repairs.show', $repairDraft) }}">
                                        <i class="ri-crop-line me-1"></i>Review / crop &middot; {{ $repairDraft->status === 'needs_review' ? 'Manual review' : ucfirst(str_replace('_', ' ', $repairDraft->status)) }}
                                    </a>
                                    @if(in_array($repairDraft->status, ['ready','needs_review']) && !empty($repairDraft->changed_fields))
                                        <form method="POST" action="{{ route('exam-quality.repairs.batch-publish', $audit) }}" data-swal-confirm="{{ $repairDraft->status === 'needs_review' ? 'Admin override: publish these changes while warnings or errors remain?' : 'Publish this repair now?' }} The previous question version will be saved and can be restored later.">
                                            @csrf
                                            <input type="hidden" name="draft_ids[]" value="{{ $repairDraft->id }}">
                                            @if($repairDraft->status === 'needs_review')<input type="hidden" name="force_publish" value="1">@endif
                                            <button class="btn btn-sm btn-{{ $repairDraft->status === 'ready' ? 'success' : 'warning' }}"><i class="ri-check-double-line me-1"></i>{{ $repairDraft->status === 'ready' ? 'Publish' : 'Publish anyway' }}</button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('exam-quality.repairs.queue', $audit) }}">@csrf
                                        <input type="hidden" name="question_ids[]" value="{{ $first->question_id }}">
                                        <button class="btn btn-sm btn-outline-primary"><i class="ri-magic-line me-1"></i>Prepare repair</button>
                                    </form>
                                @endif
                                <a class="btn btn-sm btn-primary" href="{{ route('questions.edit',$first->question) }}" target="_blank"><i class="ri-edit-line me-1"></i>Edit question</a>
                            @endif
                        </div>
                    </div>

                    @foreach($group as $finding)
                        <div class="{{ !$loop->first ? 'border-top pt-3 mt-3' : '' }}">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <span class="badge bg-{{ in_array($finding->severity,['error','critical'])?'danger':($finding->severity==='warning'?'warning':'info') }}">{{ strtoupper($finding->severity) }}</span>
                                    <span class="badge bg-light text-dark ms-1">{{ str_replace('_',' ',$finding->source) }}</span>
                                    <h6 class="mt-2 mb-1">{{ $finding->title }}</h6>
                                </div>
                                <span class="badge bg-light text-dark align-self-start">{{ str_replace('_',' ',$finding->status) }}</span>
                            </div>
                            <p class="mb-2">{{ $finding->details }}</p>

                            @if(data_get($finding->evidence, 'source_reference') || data_get($finding->evidence, 'source_excerpt') || data_get($finding->evidence, 'suggested_correction'))
                                <div class="bg-light border rounded p-3 mb-2 small">
                                    @if(data_get($finding->evidence, 'source_reference'))<div><strong>Source reference:</strong> {{ data_get($finding->evidence, 'source_reference') }}</div>@endif
                                    @if(data_get($finding->evidence, 'source_excerpt'))<div class="mt-1"><strong>Source excerpt:</strong> {{ data_get($finding->evidence, 'source_excerpt') }}</div>@endif
                                    @if(data_get($finding->evidence, 'suggested_correction'))<div class="mt-1"><strong>Suggested correction:</strong> {{ data_get($finding->evidence, 'suggested_correction') }}</div>@endif
                                </div>
                            @endif
                            @if($finding->confidence !== null)<div class="small text-muted mb-2">AI confidence: {{ rtrim(rtrim(number_format($finding->confidence,2),'0'),'.') }}%</div>@endif

                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                @if($finding->screenshot_path)<a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ asset('storage/'.$finding->screenshot_path) }}"><i class="ri-image-line me-1"></i>Evidence</a>@endif
                                <form method="POST" action="{{ route('exam-quality.findings.update',$finding) }}" class="d-flex gap-2">@csrf @method('PATCH')
                                    <select name="status" class="form-select form-select-sm"><option value="open" @selected($finding->status==='open')>Open</option><option value="resolved" @selected($finding->status==='resolved')>Resolved</option><option value="false_positive" @selected($finding->status==='false_positive')>False positive</option><option value="ignored" @selected($finding->status==='ignored')>Ignored</option></select>
                                    <button class="btn btn-sm btn-outline-secondary">Save</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="text-center py-5"><i class="ri-shield-check-line fs-1 text-success"></i><h4 class="mt-2">No findings</h4><p class="text-muted">No quality problems were detected by the selected reviewers.</p></div>
            @endforelse
        </div>
        <div class="card-footer">{{ $groupPages->links() }}</div>
    </div>
    @endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const boxes = Array.from(document.querySelectorAll('.repair-question-checkbox'));
    const publishBoxes = Array.from(document.querySelectorAll('.publish-question-checkbox'));
    const master = document.getElementById('select-all-visible-findings');
    const prepareButton = document.getElementById('prepare-selected');
    const publishButton = document.getElementById('publish-selected');
    const count = document.getElementById('bulk-selection-count');

    const sync = () => {
        const prepareSelected = boxes.filter(box => box.checked).length;
        const publishSelected = publishBoxes.filter(box => box.checked).length;
        const selected = prepareSelected + publishSelected;
        if (prepareButton) {
            prepareButton.disabled = prepareSelected === 0;
            prepareButton.innerHTML = '<i class="ri-refresh-line me-1"></i>Prepare selected missing/failed' + (prepareSelected ? ' (' + prepareSelected + ')' : '');
        }
        if (publishButton) {
            publishButton.disabled = publishSelected === 0;
            publishButton.innerHTML = '<i class="ri-check-double-line me-1"></i>Publish selected' + (publishSelected ? ' (' + publishSelected + ')' : '');
        }
        if (count) count.textContent = `${selected} selected`;
        if (master) {
            const allBoxes = boxes.concat(publishBoxes);
            master.checked = allBoxes.length > 0 && selected === allBoxes.length;
            master.indeterminate = selected > 0 && selected < allBoxes.length;
            master.disabled = allBoxes.length === 0;
        }
    };

    boxes.forEach(box => box.addEventListener('change', sync));
    publishBoxes.forEach(box => box.addEventListener('change', sync));
    master?.addEventListener('change', () => {
        boxes.forEach(box => box.checked = master.checked);
        publishBoxes.forEach(box => box.checked = master.checked);
        sync();
    });
    sync();
});
</script>
@endsection