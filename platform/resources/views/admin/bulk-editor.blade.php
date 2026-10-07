@extends('layouts.master')
@section('title', 'Bulk Edit '.$config['title'])

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Administration')
@slot('title', 'Bulk Edit '.$config['title'])
@endcomponent

<div class="card" data-disable-ai-inline>
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4 class="card-title mb-1">Bulk Edit {{ $config['title'] }}</h4>
            <p class="text-muted mb-0">Select records, edit individual rows or apply one value to every selected row.</p>
        </div>
        <a href="{{ url()->previous() }}" class="btn el-btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back</a>
    </div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="GET" class="row g-3 align-items-end mb-4" id="bulk-hierarchy-filter">
            @foreach($config['filters'] ?? [] as $filter)
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">{{ ucfirst($filter) }}</label>
                    <select name="{{ $filter }}_id" class="form-select hierarchy-filter" data-filter="{{ $filter }}">
                        <option value="">All {{ $filter }}{{ $filter === 'category' ? 'ies' : 's' }}</option>
                        @foreach($filterOptions[$filter] ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected((string)($filters[$filter] ?? '') === (string)$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-6 col-xl-3">
                <label class="form-label">Search</label>
                <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search {{ strtolower($config['title']) }}...">
            </div>
            <div class="col-md-6 col-xl-3 d-flex gap-2">
                <button class="btn el-btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('admin.bulk-editor.index', $resource) }}" class="btn el-btn-secondary flex-grow-1">Reset</a>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.bulk-editor.update', $resource) }}" id="bulk-editor-form">
            @csrf @method('PUT')
            <div class="border rounded p-3 mb-4 bg-light-subtle">
                <h5 class="mb-3">Apply to selected records</h5>
                <div class="row g-3">
                    @foreach($config['fields'] as $field => $definition)
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <label class="form-label">{{ $definition['label'] }}</label>
                            @if($definition['type'] === 'select')
                                <select name="bulk[{{ $field }}]" class="form-select">
                                    <option value="">No change</option>
                                    @foreach($options[$definition['options']] ?? [] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            @elseif($definition['type'] === 'textarea')
                                <textarea name="bulk[{{ $field }}]" class="form-control" rows="2" placeholder="No change"></textarea>
                            @else
                                <input type="{{ $definition['type'] === 'option_indices' ? 'text' : $definition['type'] }}" name="bulk[{{ $field }}]" class="form-control" placeholder="{{ $definition['type'] === 'option_indices' ? 'No change; use 2 or 1,3' : 'No change' }}" @if(isset($definition['step'])) step="{{ $definition['step'] }}" @endif>
                            @endif
                        </div>
                    @endforeach
                    <div class="col-xl-3 col-md-4 col-sm-6"><label class="form-label">Prefix name/code</label><input name="name_prefix" class="form-control" placeholder="Optional"></div>
                    <div class="col-xl-3 col-md-4 col-sm-6"><label class="form-label">Suffix name/code</label><input name="name_suffix" class="form-control" placeholder="Optional"></div>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" id="select-visible" class="form-check-input mt-0"> Select all visible</label>
                <button type="submit" class="btn el-btn-primary"><i class="ri-save-line me-1"></i> Save selected changes</button>
            </div>

            @if(count($config['fields']) > 10)
                <div class="table-responsive">
                    <table class="table align-middle el-table">
                        <thead><tr><th style="width:44px"></th><th style="min-width:80px">ID</th><th>{{ $config['fields'][$config['name_field']]['label'] ?? 'Record' }}</th></tr></thead>
                        <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="{{ $record->id }}" class="form-check-input record-select"></td>
                                <td>{{ $record->id }}</td>
                                <td class="fw-semibold">{{ \Illuminate\Support\Str::limit(strip_tags((string)$record->getAttribute($config['name_field'])), 130) }}</td>
                            </tr>
                            <tr class="bg-light-subtle">
                                <td></td><td colspan="2">
                                    <div class="row g-3 py-2">
                                    @foreach($config['fields'] as $field => $definition)
                                        @php
                                            $current = $record->getAttribute($field);
                                            if ($field === 'correct_option_indices' && is_array($current)) $current = implode(',', $current);
                                        elseif (is_array($current)) $current = $current[app()->getLocale()] ?? reset($current) ?: '';
                                        @endphp
                                        <div class="col-xl-3 col-md-4 col-sm-6">
                                            <label class="form-label small mb-1">{{ $definition['label'] }}</label>
                                            @if($definition['type'] === 'select')
                                                <select name="rows[{{ $record->id }}][{{ $field }}]" class="form-select form-select-sm">
                                                    <option value="">No change</option>
                                                    @foreach($options[$definition['options']] ?? [] as $value => $label)<option value="{{ $value }}" @selected((string)$current === (string)$value)>{{ $label }}</option>@endforeach
                                                </select>
                                            @elseif($definition['type'] === 'textarea')
                                                <textarea name="rows[{{ $record->id }}][{{ $field }}]" class="form-control form-control-sm" rows="3">{{ $current }}</textarea>
                                            @else
                                                <input type="{{ $definition['type'] === 'option_indices' ? 'text' : $definition['type'] }}" name="rows[{{ $record->id }}][{{ $field }}]" value="{{ $current }}" class="form-control form-control-sm" @if(isset($definition['step'])) step="{{ $definition['step'] }}" @endif>
                                            @endif
                                        </div>
                                    @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-5">No records found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle el-table">
                        <thead><tr><th style="width:44px"></th><th style="min-width:80px">ID</th>@foreach($config['fields'] as $definition)<th style="min-width:190px">{{ $definition['label'] }}</th>@endforeach</tr></thead>
                        <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="{{ $record->id }}" class="form-check-input record-select"></td>
                                <td>{{ $record->id }}</td>
                                @foreach($config['fields'] as $field => $definition)
                                    @php
                                        $current = $record->getAttribute($field);
                                        if ($field === 'correct_option_indices' && is_array($current)) $current = implode(',', $current);
                                        elseif (is_array($current)) $current = $current[app()->getLocale()] ?? reset($current) ?: '';
                                    @endphp
                                    <td>
                                        @if($definition['type'] === 'select')
                                            <select name="rows[{{ $record->id }}][{{ $field }}]" class="form-select form-select-sm">
                                                <option value="">No change</option>
                                                @foreach($options[$definition['options']] ?? [] as $value => $label)<option value="{{ $value }}" @selected((string)$current === (string)$value)>{{ $label }}</option>@endforeach
                                            </select>
                                        @elseif($definition['type'] === 'textarea')
                                            <textarea name="rows[{{ $record->id }}][{{ $field }}]" class="form-control form-control-sm" rows="3">{{ $current }}</textarea>
                                        @else
                                            <input type="{{ $definition['type'] === 'option_indices' ? 'text' : $definition['type'] }}" name="rows[{{ $record->id }}][{{ $field }}]" value="{{ $current }}" class="form-control form-control-sm" @if(isset($definition['step'])) step="{{ $definition['step'] }}" @endif>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($config['fields']) + 2 }}" class="text-center text-muted py-5">No records found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </form>
        <div class="mt-3">{{ $records->links() }}</div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('bulk-hierarchy-filter');
    filterForm?.querySelectorAll('.hierarchy-filter').forEach(select => {
        select.addEventListener('change', () => {
            const resets = {
                group: ['category', 'subcategory', 'package', 'subject', 'topic', 'subtopic'],
                category: ['subcategory', 'package'],
                subcategory: ['package'],
                subject: ['topic', 'subtopic'],
                topic: ['subtopic'],
            };
            (resets[select.dataset.filter] || []).forEach(name => {
                const dependent = filterForm.querySelector(`[data-filter="${name}"]`);
                if (dependent) dependent.value = '';
            });
            filterForm.requestSubmit();
        });
    });
    const master = document.getElementById('select-visible');
    const boxes = Array.from(document.querySelectorAll('.record-select'));
    master?.addEventListener('change', () => boxes.forEach(box => box.checked = master.checked));
    document.getElementById('bulk-editor-form')?.addEventListener('submit', function (event) {
        if (!boxes.some(box => box.checked)) {
            event.preventDefault();
            if (window.Swal) Swal.fire({icon:'warning', title:'Select records', text:'Select at least one record to update.'});
            else alert('Select at least one record to update.');
        }
    });
});
</script>
@endsection
