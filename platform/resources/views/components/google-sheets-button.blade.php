@props(['resource', 'filters' => []])
@if(\App\Support\GoogleSheetsAdminAccess::allowed() && \Illuminate\Support\Facades\Route::has('admin.google-sheets.show'))
    <a href="{{ route('admin.google-sheets.show', array_merge(['resource' => $resource], (array) $filters)) }}" {{ $attributes->merge(['class' => 'btn el-btn-secondary']) }}>
        <i class="ri-file-excel-2-line align-bottom me-1"></i> Open in Google Sheets
    </a>
@endif
