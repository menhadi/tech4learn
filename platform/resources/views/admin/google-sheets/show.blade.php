@extends('layouts.master')
@section('title', 'Google Sheets - '.$definition['title'])

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Administration')
@slot('title', 'Google Sheets - '.$definition['title'])
@endcomponent

<div class="row justify-content-center" data-disable-ai-inline>
    <div class="col-xxl-10">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="card-title mb-1">Connected {{ $definition['title'] }} sheet</h4>
                    <p class="text-muted mb-0">Choose editable fields, send the current filtered dataset to Google Sheets, then sync edited rows back.</p>
                </div>
                @if($connection)
                    <a href="{{ $connection->spreadsheet_url }}" target="_blank" rel="noopener" class="btn btn-success">
                        <i class="ri-external-link-line me-1"></i> Open connected sheet
                    </a>
                @endif
            </div>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><strong>Google Sheets action failed.</strong><div>{{ $errors->first() }}</div></div>@endif
                @unless($configured)
                    <div class="alert alert-warning mb-4">
                        Google Sheets is not configured on this server. Enable the Sheets and Drive APIs, create a Google service account, and set
                        <code>GOOGLE_SHEETS_ENABLED=true</code> and <code>GOOGLE_SERVICE_ACCOUNT_CREDENTIALS</code> to the absolute JSON credential path.
                    </div>
                @endunless

                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Rows in current scope</div><div class="fs-4 fw-semibold">{{ number_format($estimatedRows) }}</div></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Last sent to Google</div><div class="fw-semibold">{{ $connection?->last_exported_at?->diffForHumans() ?? 'Never' }}</div></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Last synced to database</div><div class="fw-semibold">{{ $connection?->last_synced_at?->diffForHumans() ?? 'Never' }}</div></div></div>
                </div>

                <form method="POST" action="{{ route('admin.google-sheets.export', $resource) }}" id="google-sheet-fields">
                    @csrf
                    @foreach($filters as $key => $value)<input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">@endforeach
                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <label class="form-label">Google account that can edit the sheet</label>
                            <input type="email" name="share_email" value="{{ old('share_email', $connection?->share_email ?? config('google_sheets.share_email') ?? auth()->user()->email) }}" class="form-control" required @readonly((bool)$connection)>
                            <div class="form-text">The first connection shares the sheet with this account. Additional access can be granted inside Google Drive.</div>
                        </div>
                        <div class="col-lg-6 d-flex align-items-end">
                            <div class="alert alert-info mb-0 w-100 py-2">Refreshing replaces the sheet contents. Sync any pending edits first.</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Fields to edit</h5>
                            <p class="text-muted mb-0">System ID, version, and sync-status columns are included automatically.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm el-btn-secondary" id="select-all-fields">Select all editable</button>
                            <button type="button" class="btn btn-sm el-btn-secondary" id="clear-fields">Clear</button>
                        </div>
                    </div>
                    <div class="row g-2 mb-4">
                        @foreach($definition['fields'] as $field => $fieldDefinition)
                            <div class="col-xl-3 col-md-4 col-sm-6">
                                <label class="border rounded p-3 w-100 h-100 d-flex gap-2 align-items-start">
                                    <input type="checkbox" class="form-check-input mt-1 google-sheet-field" name="fields[]" value="{{ $field }}"
                                        @checked(in_array($field, old('fields', $connection?->selected_fields ?? array_keys($definition['fields'])), true))>
                                    <span><span class="fw-semibold d-block">{{ $fieldDefinition['label'] }}</span><code class="small">{{ $field }}</code></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn el-btn-primary" @disabled(!$configured) onclick="return confirm('This will replace the connected sheet with current database values. Continue?')">
                        <i class="ri-refresh-line me-1"></i> {{ $connection ? 'Refresh connected sheet' : 'Create connected sheet' }}
                    </button>
                </form>

                @if($connection)
                    <hr class="my-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">Apply Google Sheet edits</h5>
                            <p class="text-muted mb-0">Only changed rows are validated and updated. Conflicts and errors are written into the <code>sync_status</code> column.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.google-sheets.sync', $resource) }}">
                            @csrf
                            <button class="btn btn-success" @disabled(!$configured) onclick="return confirm('Validate and apply edited sheet rows to the Exam Elite database?')">
                                <i class="ri-database-2-line me-1"></i> Sync changes to Exam Elite
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('select-all-fields')?.addEventListener('click', () => document.querySelectorAll('.google-sheet-field').forEach(field => field.checked = true));
document.getElementById('clear-fields')?.addEventListener('click', () => document.querySelectorAll('.google-sheet-field').forEach(field => field.checked = false));
@if(session('open_google_sheet')) window.open(@json(session('open_google_sheet')), '_blank', 'noopener'); @endif
</script>
@endsection
