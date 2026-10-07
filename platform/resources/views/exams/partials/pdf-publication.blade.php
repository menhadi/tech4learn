<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a class="btn btn-outline-primary" href="{{ route('exams.index', ['filter' => 'unpublished_pdfs']) }}">Unpublished PDF papers</a>
</div>
@if(user_can_route_action('exams.edit', 'edit'))
@php
    $publicationGroups = \App\Models\Group::where('organization_id', \App\Support\Tenant::id())->orderBy('group_name')->get();
    $publicationCategories = \App\Models\Category::where('organization_id', \App\Support\Tenant::id())->whereNull('parent_id')->where('status', 1)->orderBy('title')->get();
@endphp
<form id="pdf-publication" action="{{ route('exams.publish-pdfs') }}" method="POST" class="card card-body mb-3">
    @csrf
    <h5 class="mb-3">Publish PDF papers</h5>
    <div class="d-flex flex-wrap align-items-end gap-3">
        <label class="form-check"><input type="checkbox" class="form-check-input" id="select-pdf-papers"> Select all on this page</label>
        <label>Group<select name="group_id" class="form-select"><option value="">Keep each paper's group</option>@foreach($publicationGroups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select></label>
        <label>Category action<select name="category_mode" class="form-select"><option value="keep">Keep current category</option><option value="assign">Assign existing category</option>@if(user_can_route_action('category.create', 'add'))<option value="create">Create category</option>@endif</select></label>
        <label data-category-assign hidden>Category<select name="category_id" class="form-select" disabled><option value="">Select category</option>@foreach($publicationCategories as $category)<option value="{{ $category->id }}">{{ $category->title }}</option>@endforeach</select></label>
        <label data-category-create hidden>New category<input name="new_category" maxlength="200" class="form-control" disabled></label>
        <button type="submit" class="btn btn-success" id="publish-pdf-papers" disabled>Publish selected PDFs (<span id="pdf-paper-count">0</span>)</button>
    </div>
    <p class="small text-muted mb-0 mt-2">A saved question or combined PDF is required. Papers without active questions offer only PDF download. Extraction stays separate. Package papers retain the package's group and category.</p>
    @if($errors->any())<div class="text-danger mt-2">{{ $errors->first() }}</div>@endif
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pdf-publication');
    const boxes = () => Array.from(document.querySelectorAll('input[form="pdf-publication"][name="exam_ids[]"]'));
    const master = document.getElementById('select-pdf-papers');
    const refresh = () => {
        const all = boxes(), count = all.filter(box => box.checked).length;
        document.getElementById('pdf-paper-count').textContent = count;
        document.getElementById('publish-pdf-papers').disabled = count === 0;
        master.checked = all.length > 0 && count === all.length;
        master.indeterminate = count > 0 && count < all.length;
    };
    master.addEventListener('change', () => { boxes().forEach(box => box.checked = master.checked); refresh(); });
    boxes().forEach(box => box.addEventListener('change', refresh));
    const categoryMode = form.elements.category_mode;
    categoryMode.addEventListener('change', () => {
        ['assign', 'create'].forEach(mode => {
            const label = form.querySelector('[data-category-' + mode + ']');
            label.hidden = categoryMode.value !== mode;
            label.querySelector('input,select').disabled = label.hidden;
            label.querySelector('input,select').required = !label.hidden;
        });
    });
    refresh();
});
</script>
@endpush
@else
<div class="card card-body mb-3">
    <h5>Publish PDF papers</h5>
    <div><button type="button" class="btn btn-success" disabled>Publish selected PDFs</button></div>
    <p class="text-muted small mb-0 mt-2">Exam edit permission is required to select and publish PDF papers. Ask your organization administrator to grant the appropriate exam permission.</p>
</div>
@endif
