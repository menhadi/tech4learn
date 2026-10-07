@extends('layouts.master')
@section('title','Bulk Edit Exams')
@section('content')
@php
$extraFields = [
    'instruction'=>['label'=>'Instructions','type'=>'textarea'], 'syllabus'=>['label'=>'Syllabus','type'=>'textarea'],
    'start_date'=>['label'=>'Start date','type'=>'datetime-local'], 'end_date'=>['label'=>'End date','type'=>'datetime-local'],
    'negative_marking'=>['label'=>'Negative marking','type'=>'boolean'], 'random_question'=>['label'=>'Random questions','type'=>'boolean'],
    'result_after_finish'=>['label'=>'Result after finish','type'=>'boolean'], 'option_shuffle'=>['label'=>'Shuffle options','type'=>'boolean'],
    'allow_answer_change'=>['label'=>'Allow answer change','type'=>'boolean'], 'browser_tolerance'=>['label'=>'Browser tolerance','type'=>'boolean'],
    'proctor'=>['label'=>'Proctoring','type'=>'boolean'], 'calculator_allowed'=>['label'=>'Calculator','type'=>'boolean'],
    'tolerance_count'=>['label'=>'Tolerance count','type'=>'number'], 'grouping_mode'=>['label'=>'Grouping mode','type'=>'text'],
    'is_subject_timer'=>['label'=>'Subject timer','type'=>'boolean'], 'timer_mode'=>['label'=>'Timer mode','type'=>'text'],
    'show_answer_sheet'=>['label'=>'Show answer sheet','type'=>'boolean'], 'mode'=>['label'=>'Exam mode','type'=>'text'],
    'instant_result'=>['label'=>'Instant result','type'=>'boolean'], 'multi_language'=>['label'=>'Multiple languages','type'=>'boolean'],
    'math_editor'=>['label'=>'Math editor','type'=>'boolean'], 'offline_enabled'=>['label'=>'Offline mode available','type'=>'boolean'],
];
@endphp
<div class="container-fluid" data-disable-ai-inline>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div><h3 class="mb-1">Bulk Edit Existing Exams</h3><p class="text-muted mb-0">Filter hierarchically, edit individual rows, or apply one change to selected exams.</p></div>
        <a href="{{ route('exams.index') }}" class="btn btn-outline-primary"><i class="ri-arrow-left-line me-1"></i>Back to Exams</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card mb-4"><div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="exam-hierarchy-filter">
            <div class="col-md-6 col-xl-2"><label class="form-label">Group</label><select class="form-select hierarchy-filter" data-filter="group" name="group_id"><option value="">All groups</option>@foreach($groups as $item)<option value="{{ $item->id }}" @selected($filters['groupId']==$item->id)>{{ $item->group_name }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-2"><label class="form-label">Category</label><select class="form-select hierarchy-filter" data-filter="category" name="category_id"><option value="">All categories</option>@foreach($categories as $item)<option value="{{ $item->id }}" @selected($filters['categoryId']==$item->id)>{{ $item->title }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-2" data-subcategory-ui><label class="form-label">Subcategory</label><select class="form-select hierarchy-filter" data-filter="subcategory" name="subcategory_id"><option value="">All subcategories</option>@foreach($subcategories as $item)<option value="{{ $item->id }}" data-parent="{{ $item->parent_id }}" @selected($filters['subcategoryId']==$item->id)>{{ $item->title }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">Package</label><select class="form-select hierarchy-filter" data-filter="package" name="package_id"><option value="">All packages</option>@foreach($packages as $item)<option value="{{ $item->id }}" @selected($filters['packageId']==$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="col-md-8 col-xl-2"><label class="form-label">Exam name</label><input class="form-control" name="search" value="{{ $filters['search'] }}" placeholder="Search exams"></div>
            <div class="col-md-4 col-xl-1"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div></div>

    <form method="POST" action="{{ route('exams.bulk-editor.update') }}" id="bulk-exam-editor">@csrf @method('PUT')
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-1">Bulk changes</h5><p class="text-muted mb-0">Only non-empty values apply. Prefix and suffix are added to the current row name.</p></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">Name prefix</label><input class="form-control" name="bulk[prefix]" placeholder="e.g. GATE - "></div>
                <div class="col-md-3"><label class="form-label">Name suffix</label><input class="form-control" name="bulk[suffix]" placeholder="e.g. - PYP"></div>
                <div class="col-md-2"><label class="form-label">Duration</label><input class="form-control" type="number" min="0" name="bulk[duration]" placeholder="Keep current"></div>
                <div class="col-md-2"><label class="form-label">Attempts</label><input class="form-control" type="number" min="0" name="bulk[attempt_count]" placeholder="Keep current"></div>
                <div class="col-md-2"><label class="form-label">Passing %</label><input class="form-control" type="number" min="0" max="100" step=".01" name="bulk[passing_percentage]" placeholder="Keep current"></div>
                <div class="col-md-2"><label class="form-label">Display order</label><input class="form-control" type="number" min="0" name="bulk[display_order]" placeholder="Keep current"></div>
                <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="bulk[status]"><option value="">Keep current</option><option>Active</option><option>Inactive</option></select></div>
                <div class="col-md-3"><label class="form-label">Replace group</label><select class="form-select" name="bulk[group_id]"><option value="">Keep current</option>@foreach($groups as $item)<option value="{{ $item->id }}">{{ $item->group_name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Replace category</label><select class="form-select" name="bulk[category_id]"><option value="">Keep current</option>@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->title }}</option>@endforeach</select></div>
                <div class="col-md-3" data-subcategory-ui><label class="form-label">Replace subcategory</label><select class="form-select" name="bulk[subcategory_id]"><option value="">Keep current</option>@foreach($subcategories as $item)<option value="{{ $item->id }}">{{ $item->title }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Replace package</label><select class="form-select" name="bulk[package_id]"><option value="">Keep current</option>@foreach($packages as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                @foreach($extraFields as $field => $definition)
    <div class="col-xl-3 col-md-4 col-sm-6">
        <label class="form-label">{{ $definition['label'] }}</label>
        @if($definition['type']==='boolean')
            <select class="form-select" name="bulk[{{ $field }}]"><option value="">Keep current</option><option value="1">Yes</option><option value="0">No</option></select>
        @elseif($definition['type']==='textarea')
            <textarea class="form-control" rows="2" name="bulk[{{ $field }}]" placeholder="Keep current"></textarea>
        @else
            <input class="form-control" type="{{ $definition['type'] }}" name="bulk[{{ $field }}]" placeholder="Keep current">
        @endif
    </div>
@endforeach
<div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" id="save-selected-exams" disabled data-swal-confirm="Apply row edits and bulk changes to the selected exams?"><i class="ri-save-line me-1"></i>Save selected exams</button></div>
            </div></div>
        </div>

        <div class="card"><div class="card-header d-flex justify-content-between"><div><h5 class="mb-1">Exams</h5><span class="text-muted">{{ $exams->total() }} matching. Use <strong>Edit full exam</strong> for every field in the standard exam module.</span></div><span id="selected-exam-count" class="badge bg-primary align-self-center">0 selected</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0" style="min-width:1450px">
                <thead class="table-light"><tr><th><input type="checkbox" class="form-check-input" id="select-all-exams"></th><th>Exam name</th><th>Group / package</th><th>Duration</th><th>Attempts</th><th>Pass %</th><th>Order</th><th>Status</th><th>All fields</th></tr></thead>
                <tbody>@forelse($exams as $exam)<tr>
                    <td><input class="form-check-input exam-row-check" type="checkbox" name="selected_exam_ids[]" value="{{ $exam->id }}"></td>
                    <td style="min-width:330px"><input class="form-control" name="rows[{{ $exam->id }}][name]" value="{{ $exam->name }}"></td>
                    <td style="min-width:300px"><div>{{ $exam->groups->pluck('group_name')->join(', ') ?: '-' }}</div><small class="text-muted">{{ $exam->packages->pluck('name')->join(', ') ?: 'No package' }}</small></td>
                    <td><input class="form-control" type="number" min="0" name="rows[{{ $exam->id }}][duration]" value="{{ $exam->duration }}"></td>
                    <td><input class="form-control" type="number" min="0" name="rows[{{ $exam->id }}][attempt_count]" value="{{ $exam->attempt_count }}"></td>
                    <td><input class="form-control" type="number" min="0" max="100" step=".01" name="rows[{{ $exam->id }}][passing_percentage]" value="{{ $exam->passing_percentage }}"></td>
                    <td><input class="form-control" type="number" min="0" name="rows[{{ $exam->id }}][display_order]" value="{{ $exam->display_order }}"></td>
                    <td><select class="form-select" name="rows[{{ $exam->id }}][status]"><option @selected($exam->status==='Active')>Active</option><option @selected($exam->status!=='Active')>Inactive</option></select></td>
                    <td><a class="btn btn-outline-primary text-nowrap" target="_blank" href="{{ route('exams.edit', $exam) }}"><i class="ri-edit-line me-1"></i>Edit full exam</a></td>
                </tr>
                <tr class="bg-light-subtle"><td></td><td colspan="8"><div class="row g-3 py-2">
                    @foreach($extraFields as $field => $definition)
                        @php $current=$exam->getAttribute($field); @endphp
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <label class="form-label small mb-1">{{ $definition['label'] }}</label>
                            @if($definition['type']==='boolean')
                                <select class="form-select form-select-sm" name="rows[{{ $exam->id }}][{{ $field }}]"><option value="1" @selected((string)$current==='1')>Yes</option><option value="0" @selected((string)$current==='0')>No</option></select>
                            @elseif($definition['type']==='textarea')
                                <textarea class="form-control form-control-sm" rows="2" name="rows[{{ $exam->id }}][{{ $field }}]">{{ $current }}</textarea>
                            @else
                                <input class="form-control form-control-sm" type="{{ $definition['type'] }}" name="rows[{{ $exam->id }}][{{ $field }}]" value="{{ $definition['type']==='datetime-local' && $current ? \Illuminate\Support\Carbon::parse($current)->format('Y-m-d\TH:i') : $current }}">
                            @endif
                        </div>
                    @endforeach
                </div></td></tr>
                @empty<tr><td colspan="9" class="text-center py-5 text-muted">No exams match these filters.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </form>
    <div class="mt-3">{{ $exams->links() }}</div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('exam-hierarchy-filter');
    filterForm?.querySelectorAll('.hierarchy-filter').forEach(select => {
        select.addEventListener('change', () => {
            const resets = {
                group: ['category', 'subcategory', 'package'],
                category: ['subcategory', 'package'],
                subcategory: ['package'],
            };
            (resets[select.dataset.filter] || []).forEach(name => {
                const dependent = filterForm.querySelector(`[data-filter="${name}"]`);
                if (dependent) dependent.value = '';
            });
            filterForm.requestSubmit();
        });
    });
    const all = document.getElementById('select-all-exams');
    const boxes = [...document.querySelectorAll('.exam-row-check')];
    const button = document.getElementById('save-selected-exams');
    const count = document.getElementById('selected-exam-count');
    const sync = () => {
        const selected = boxes.filter(box => box.checked).length;
        button.disabled = selected === 0;
        count.textContent = selected + ' selected';
        all.checked = boxes.length > 0 && selected === boxes.length;
        all.indeterminate = selected > 0 && selected < boxes.length;
    };
    all?.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); sync(); });
    boxes.forEach(box => box.addEventListener('change', sync));
    sync();
});
</script>
@endpush
@endsection
