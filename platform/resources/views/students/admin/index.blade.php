@extends('layouts.master')
@section('title', 'Students Management')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Management')
@slot('title', 'Students Overview')
@endcomponent

@php
    $canAddStudent = user_can_route_action('students.create', 'add');
    $canEditStudent = user_can_route_action('students.edit', 'edit');
    $canDeleteStudent = user_can_route_action('students.destroy', 'delete');
    $canExportStudent = user_can_route_action('students.export', 'export');
@endphp

<div class="el-page-header mb-3">
    <div class="el-page-header-main">
        <span class="el-page-kicker">Student Management</span>
        <h1 class="el-page-title">Students</h1>
        <p class="el-page-subtitle">Add learners, organize them into groups, and follow their progress.</p>
    </div>
    <div class="el-action-bar">
        @if($canAddStudent && config('attendance.student_delivery_enabled', false))
            <a href="{{ route('enrolment.workspace') }}" class="btn el-btn-primary el-btn-icon"><i class="ri-user-add-line"></i> Enrol Students</a>
        @elseif($canAddStudent)
            <button class="btn el-btn-primary el-btn-icon" data-bs-toggle="modal" data-bs-target="#showModal"><i class="ri-user-add-line"></i> Add Student</button>
            <button class="btn el-btn-soft el-btn-icon" data-bs-toggle="modal" data-bs-target="#importModal"><i class="ri-upload-2-line"></i> Import</button>
        @endif
        @if($canExportStudent)
            <a href="{{ route('students.export', request()->all()) }}" class="btn btn-light el-btn-icon"><i class="ri-download-2-line"></i> Export</a>
        @endif
    </div>
</div>

@if(config('attendance.student_delivery_enabled', false))
<div class="alert alert-info">For students delivered from Enrolment, change their name, enrolment number and section in <a href="{{ route('enrolment.workspace') }}">Enrolment</a>, then deliver the updated exam profile. Manage exam login and contact details here.</div>
@endif

<div class="el-stats-grid mb-3">
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Real Students</div>
            <div class="el-stat-value">{{ $stats['total'] }}</div>
            @if(!empty($showDemoStudents))
                <div class="el-stat-note">{{ number_format($stats['demo']) }} demo &middot; {{ number_format($stats['all']) }} total</div>
            @else
                <div class="el-stat-note">Registered learners</div>
            @endif
        </div>
        <span class="el-stat-icon"><i class="ri-group-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Active</div>
            <div class="el-stat-value">{{ $stats['active'] }}</div>
            <div class="el-stat-note">Ready to take exams</div>
        </div>
        <span class="el-stat-icon"><i class="ri-user-follow-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Pending OTP</div>
            <div class="el-stat-value">{{ $stats['pending'] ?? 0 }}</div>
            <div class="el-stat-note">May need admin activation</div>
        </div>
        <span class="el-stat-icon"><i class="ri-alert-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Groups</div>
            <div class="el-stat-value">{{ $groups->count() }}</div>
            <div class="el-stat-note">Classes or batches</div>
        </div>
        <span class="el-stat-icon"><i class="ri-stack-line"></i></span>
    </div>
</div>

<div class="el-panel">
    <div class="px-3 pt-3">
        @include('students.admin.type-tabs')
    </div>
    <div class="el-panel-header">
        <h2 class="el-panel-title">Student List</h2>
        <div class="el-action-bar">
            @if($canEditStudent)
                <button type="button" id="bulk-assign-group-btn" class="btn el-btn-soft el-btn-icon"><i class="ri-group-line"></i> Assign Group</button>
            @endif
            @if($canDeleteStudent)
                <button type="button" id="bulk-delete-students" class="btn el-btn-danger el-btn-icon"><i class="ri-delete-bin-line"></i> Remove Selected</button>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('students.index') }}" class="el-filter-bar"
        data-el-ajax-filter data-el-ajax-target=".el-panel">
        <div class="el-filter-field">
            <label for="groupFilter">Group</label>
            <select id="groupFilter" name="group" class="form-control select2" data-el-autofilter>
                <option value="">All Groups</option>
                @foreach($groups as $examGroup)
                    <option value="{{ $examGroup['id'] }}" {{ request()->get('group') == $examGroup['id'] ? 'selected' : '' }}>
                        {{ $examGroup['group_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="el-filter-field">
            <label for="rankFilter">Status</label>
            <select id="rankFilter" name="rank" class="form-control select2" data-el-autofilter>
                <option {{ request()->get('rank') == 'all' ? 'selected' : '' }} value="all">All Students</option>
                <option {{ request()->get('rank') == 'active' ? 'selected' : '' }} value="active">Active</option>
                <option {{ request()->get('rank') == 'pending' ? 'selected' : '' }} value="pending">Pending OTP</option>
                <option {{ request()->get('rank') == 'weak' ? 'selected' : '' }} value="weak">Needs Help</option>
                <option {{ request()->get('rank') == 'top' ? 'selected' : '' }} value="top">Top Performers</option>
            </select>
        </div>
        <div class="el-filter-field">
            <label for="sortFilter">Sort</label>
            <select id="sortFilter" name="sort" class="form-control" data-el-autofilter>
                <option value="created" {{ request('sort', 'created') === 'created' ? 'selected' : '' }}>Recently Added</option>
                <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>Recent Activity</option>
                <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
                <option value="performance" {{ request('sort') === 'performance' ? 'selected' : '' }}>Performance</option>
                <option value="status" {{ request('sort') === 'status' ? 'selected' : '' }}>Status</option>
            </select>
        </div>
        <div class="el-filter-actions el-filter-action-field">
            <button id="filterBtn" type="submit" class="btn el-btn-primary el-btn-icon"><i class="ri-filter-3-line"></i> Filter</button>
            <button id="reset-btn" type="button" class="btn el-btn-secondary el-btn-icon"><i class="ri-refresh-line"></i> Reset</button>
        </div>
    </form>

    <div class="d-none d-md-block">
        <form method="GET" action="{{ route('students.index') }}" class="el-table-toolbar"
            data-el-ajax-filter data-el-ajax-target=".el-panel">
            <input type="hidden" name="group" value="{{ request('group') }}">
            <input type="hidden" name="rank" value="{{ request('rank') }}">
            <input type="hidden" name="sort" value="{{ request('sort', 'created') }}">
            <div class="el-table-toolbar-actions"></div>
            <div class="el-table-toolbar-controls">
                <div class="el-filter-search">
                    <div class="search-box">
                        <input id="searchFilter" type="text" name="search" class="form-control search" placeholder="Name, email, phone, roll no." value="{{ request('search') }}" data-el-autofilter>
                        <i class="ri-search-line search-icon"></i>
                    </div>
                </div>
                <div class="el-page-size">
                    <select id="perPageFilter" name="per_page" class="form-control" data-el-autofilter>
                        <option value="50" {{ (int) request('per_page', 50) === 50 ? 'selected' : '' }}>50 rows</option>
                        <option value="100" {{ (int) request('per_page') === 100 ? 'selected' : '' }}>100 rows</option>
                        <option value="500" {{ (int) request('per_page') === 500 ? 'selected' : '' }}>500 rows</option>
                    </select>
                </div>
            </div>
        </form>
        <div class="el-result-count">Showing {{ $students->count() }} of {{ $students->total() }}</div>
        <div class="el-table-wrap">
            <table class="table align-middle el-table" id="studentTable">
                        <thead class="table-light text-muted">
                            <tr>
                                @if($canEditStudent || $canDeleteStudent)
                                    <th scope="col" style="width: 50px;">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="select-all-students"></div>
                                    </th>
                                @endif
                                <th class="sort">Student</th>
                                <th class="sort">Avg. Performance</th>
                                <th class="sort">Last Activity</th>
                                <th class="sort">Contact</th>
                                <th class="sort">Status</th>
                                @if($canEditStudent || $canDeleteStudent)
                                    <th class="sort">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="list form-check-all">
                            @foreach($students as $student)
                            <tr>
                                @if($canEditStudent || $canDeleteStudent)
                                    <th scope="row"><div class="form-check"><input class="form-check-input student-checkbox" type="checkbox" name="ids[]" value="{{ $student->id }}"></div></th>
                                @endif
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            @if($student->photo) <img src="{{ $student->photo_url }}" alt="" class="avatar-xs rounded-circle">
                                            @else <div class="avatar-xs"><span class="avatar-title rounded-circle bg-primary-subtle text-primary">{{ strtoupper(substr($student->name, 0, 1)) }}</span></div> @endif
                                        </div>
                                        <div><h5 class="fs-14 mb-0"><a href="#" class="text-reset">{{ $student->name }}</a></h5><p class="text-muted mb-0 fs-12">{{ $student->groups->pluck('group_name')->implode(', ') }}</p></div>
                                    </div>
                                </td>
                                <td style="width: 200px;">
                                    @php $avg = $student->exam_results_avg_percent ?? 0; $barColor = $avg >= 75 ? 'bg-success' : ($avg >= 35 ? 'bg-warning' : 'bg-danger'); @endphp
                                    <div class="d-flex align-items-center"><div class="flex-grow-1 progress progress-sm animated-progess"><div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $avg }}%"></div></div><span class="flex-shrink-0 ms-2 fs-12 fw-medium">{{ number_format($avg, 0) }}%</span></div><span class="text-muted fs-11">Avg Score</span>
                                </td>
                                <td>
                                    @if($student->last_login) <h6 class="mb-0 fs-13">{{ \Carbon\Carbon::parse($student->last_login)->diffForHumans() }}</h6><span class="text-muted fs-11">Login</span>
                                    @elseif($student->last_exam_date) <h6 class="mb-0 fs-13">{{ \Carbon\Carbon::parse($student->last_exam_date)->diffForHumans() }}</h6><span class="text-muted fs-11">Exam Attempt</span>
                                    @else <span class="badge bg-light text-muted">Never</span> @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fs-13">{{ $student->phone ?: 'No phone' }}</span>
                                        <span class="text-muted fs-12">{{ $student->email ?: 'No email' }}</span>
                                        <span class="text-muted fs-12">Reg: {{ $student->reg_code ?: '-' }}</span>
                                        <span class="text-muted fs-12">Roll: {{ $student->enroll ?: '-' }}</span>
                                    </div>
                                </td>
                                <td class="status">
                                    @if($student->status == 'Active') <span class="badge bg-success-subtle text-success text-uppercase">Active</span>
                                    @elseif($student->status == 'Pending') <span class="badge bg-warning-subtle text-warning text-uppercase">Pending</span>
                                    @else <span class="badge bg-danger-subtle text-danger text-uppercase">Suspended</span> @endif
                                </td>
                                @if($canEditStudent || $canDeleteStudent)
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if($canEditStudent)
                                                <button class="btn btn-sm el-btn-soft edit-item-btn" data-bs-toggle="modal" data-bs-target="#showModal" data-id="{{ $student->id }}" data-name="{{ $student->name }}" data-email="{{ $student->email }}" data-phone="{{ $student->phone }}" data-reg_code="{{ $student->reg_code }}" data-enroll="{{ $student->enroll }}" data-address="{{ $student->address }}" data-status="{{ $student->status }}" data-group_ids="{{ $student->groups->pluck('id')->implode(',') }}" data-photo="{{ $student->photo ? asset('storage/' . $student->photo) : '' }}"><i class="ri-pencil-fill align-bottom"></i></button>
                                            @endif
                                            @if($canDeleteStudent)
                                                <button class="btn btn-sm el-btn-danger remove-item-btn" data-bs-toggle="modal" data-bs-target="#deleteRecordModal" data-id="{{ $student->id }}"><i class="ri-delete-bin-fill align-bottom"></i></button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="el-mobile-list d-md-none">
                @forelse($students as $student)
                    @php
                        $avg = $student->exam_results_avg_percent ?? 0;
                        $barColor = $avg >= 75 ? 'bg-success' : ($avg >= 35 ? 'bg-warning' : 'bg-danger');
                    @endphp
                    <div class="el-mobile-card">
                        <div class="el-mobile-card-head">
                            <div>
                                <div class="el-row-title">{{ $student->name }}</div>
                                <div class="el-row-meta">{{ $student->groups->pluck('group_name')->implode(', ') ?: 'No group assigned' }}</div>
                            </div>
                            <div class="text-end">
                                @if($student->status == 'Active') <span class="badge bg-success-subtle text-success text-uppercase">Active</span>
                                @elseif($student->status == 'Pending') <span class="badge bg-warning-subtle text-warning text-uppercase">Pending</span>
                                @else <span class="badge bg-danger-subtle text-danger text-uppercase">Suspended</span> @endif
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="el-row-meta">Avg Score</span>
                                <strong>{{ number_format($avg, 0) }}%</strong>
                            </div>
                            <div class="progress el-progress"><div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $avg }}%"></div></div>
                        </div>
                        <div class="el-row-meta mt-3">
                            {{ $student->phone ?: 'No phone' }} | {{ $student->email ?: 'No email' }}<br>
                            Reg: {{ $student->reg_code ?: '-' }} | Roll: {{ $student->enroll ?: '-' }}
                        </div>
                        @if($canEditStudent || $canDeleteStudent)
                            <div class="el-mobile-card-actions">
                                @if($canEditStudent)
                                    <button class="btn btn-sm el-btn-soft edit-item-btn" data-bs-toggle="modal" data-bs-target="#showModal" data-id="{{ $student->id }}" data-name="{{ $student->name }}" data-email="{{ $student->email }}" data-phone="{{ $student->phone }}" data-reg_code="{{ $student->reg_code }}" data-enroll="{{ $student->enroll }}" data-address="{{ $student->address }}" data-status="{{ $student->status }}" data-group_ids="{{ $student->groups->pluck('id')->implode(',') }}" data-photo="{{ $student->photo ? asset('storage/' . $student->photo) : '' }}"><i class="ri-pencil-fill align-bottom"></i> Edit</button>
                                @endif
                                @if($canDeleteStudent)
                                    <button class="btn btn-sm el-btn-danger remove-item-btn" data-bs-toggle="modal" data-bs-target="#deleteRecordModal" data-id="{{ $student->id }}"><i class="ri-delete-bin-fill align-bottom"></i> Remove</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="el-empty-state">No students found. Try changing the filters.</div>
                @endforelse
            </div>

            @if($students->isEmpty())
                <div class="el-empty-state d-none d-md-block">No students found. Try changing the filters.</div>
            @endif

            <div class="d-flex justify-content-end p-3">
                <div class="pagination-wrap hstack gap-2">{{ $students->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>

@if($canAddStudent || $canEditStudent)
<div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close-modal"></button>
            </div>
            <form id="student-form" class="tablelist-form" autocomplete="off" method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3"><label for="name-field" class="form-label">Name</label><input type="text" id="name-field" name="name" class="form-control" required /></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reg-code-field" class="form-label">Registration Number @if($requireRegistrationNumber)<span class="text-danger">*</span>@else<span class="text-muted">(optional)</span>@endif</label>
                            <input type="text" id="reg-code-field" name="reg_code" class="form-control" {{ $requireRegistrationNumber ? 'required' : '' }} />
                            <small class="text-muted">For school tenants this can be required. If blank, a password must be entered.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="enroll-field" class="form-label">Roll / Enrollment Number</label>
                            <input type="text" id="enroll-field" name="enroll" class="form-control" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email-field" class="form-label">Email</label>
                            <input type="email" id="email-field" name="email" class="form-control" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone-field" class="form-label">Phone</label>
                            <input type="number" id="phone-field" name="phone" class="form-control" />
                        </div>
                    </div>
                    <div class="mb-3"><label for="address-field" class="form-label">Address</label><input type="text" id="address-field" name="address" class="form-control" /></div>
                    <div class="mb-3"><label for="password-field" class="form-label">Password</label><div class="input-group"><input type="password" id="password-field" name="password" class="form-control" placeholder="Leave blank to use registration number" /><button class="btn btn-outline-secondary" type="button" id="toggle-password"><i class="ri-eye-off-line"></i></button></div></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="status-field" class="form-label">Status</label><select id="status-field" name="status" class="form-control" required><option value="Active">Active</option><option value="Pending">Pending</option><option value="Suspend">Suspend</option></select></div>
                        <div class="col-md-6 mb-3"><label for="group_ids-field" class="form-label">Groups</label><select id="group_ids-field" name="group_ids[]" class="form-control" multiple required>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select></div>
                    </div>
                    <div class="mb-3"><label for="photo-field" class="form-label">Photo</label><input type="file" id="photo-field" name="photo" class="form-control" /><img id="photo-preview" src="#" alt="Photo" style="display: none; max-width: 80px; margin-top: 10px; border-radius: 5px;"></div>
                </div>
                <div class="modal-footer"><div class="hstack gap-2 justify-content-end"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn el-btn-primary" id="add-btn">Add Student</button><button type="submit" class="btn el-btn-primary" id="edit-btn" style="display: none;">Update Changes</button></div></div>
            </form>
        </div>
    </div>
</div>

@endif

@if($canDeleteStudent)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button></div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5"><h4>Are you Sure?</h4><p class="text-muted mx-4 mb-0">Are you sure you want to remove this student record?</p></div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">@csrf @method('DELETE')<button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button></form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ✅ EXISTING MODAL: BULK GROUP ASSIGN --}}
@endif

@if($canEditStudent)
<div class="modal fade" id="bulkGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Assign Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bulk-group-form">
                    <div class="mb-3">
                        <label class="form-label">Select Action</label>
                        <select id="bulk-action-type" class="form-select">
                            <option value="add">Add to Group (Keep existing)</option>
                            <option value="replace">Replace Group (Remove others)</option>
                            <option value="remove">Remove from Group</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Group</label>
                        <select id="bulk-group-id" class="form-select">
                            <option value="">-- Select Group --</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <div class="alert alert-warning" role="alert">
                    <strong>Note:</strong> This will affect all selected students.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" id="save-bulk-group" class="btn el-btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ✅ NEW MODAL: IMPORT STUDENTS --}}
@endif

@if($canAddStudent)
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Bulk Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        Download the <a href="{{ route('students.downloadTemplate') }}" class="fw-bold text-decoration-underline">Sample CSV Template</a>. Fill it and upload below.
                        <div class="mt-1">Initial password for imported students will be their registration number.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assign to Group <span class="text-danger">*</span></label>
                        <select name="group_id" class="form-select" required>
                            <option value="">-- Select Group --</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">All uploaded students will be added to this group.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload File (CSV/Excel) <span class="text-danger">*</span></label>
                        <input type="file" name="excel_file" class="form-control" accept=".csv, .xlsx, .xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn el-btn-primary">Import Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endif

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>

    document.addEventListener('DOMContentLoaded', function() {

        $('.select2').select2();
        let studentFilterTimer;

        function submitStudentToolbar(delay = 450) {
            clearTimeout(studentFilterTimer);
            studentFilterTimer = setTimeout(function() {
                const toolbar = document.querySelector('.el-table-toolbar');
                if (toolbar && window.ExamLiteAjaxFilter) {
                    window.ExamLiteAjaxFilter.refresh(toolbar);
                    return;
                }
                toolbar?.requestSubmit();
            }, delay);
        }

        $('#searchFilter').on('input', function() {
            submitStudentToolbar(650);
        });

        $('#perPageFilter').on('change', function() {
            submitStudentToolbar(120);
        });

        // Reset button click
        $('#reset-btn').on('click', function() {
            document.querySelectorAll('.el-filter-bar select, .el-table-toolbar select').forEach(function (field) {
                field.selectedIndex = 0;
            });
            document.querySelectorAll('.el-filter-bar input, .el-table-toolbar input').forEach(function (field) {
                field.value = '';
            });

            const form = document.querySelector('.el-filter-bar');
            if (form && window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.refresh(form);
                return;
            }

            window.location.href = new URL(window.location.href.split('?')[0]).toString();
        });

    });


    $(document).ready(function() {
        if ($('#group_ids-field').length) {
            $('#group_ids-field').select2({ dropdownParent: $('#showModal'), placeholder: "Select Groups", allowClear: true, width: '100%' });
        }

        // Checkbox Logic
        $('#select-all-students').on('change', function () { $('.student-checkbox').prop('checked', this.checked); });
        $(document).on('change', '.student-checkbox', function () { $('#select-all-students').prop('checked', $('.student-checkbox:checked').length === $('.student-checkbox').length); });

        // Bulk Delete
        $('#bulk-delete-students').on('click', function () {
            let ids = []; $('.student-checkbox:checked').each(function () { ids.push($(this).val()); });
            if (ids.length === 0) { Swal.fire('Warning', 'Please select at least one student.', 'warning'); return; }
            Swal.fire({ title: 'Are you sure?', text: "Selected students will be deleted permanently!", icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete' }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({ url: "{{ route('students.remove') }}", type: 'post', data: { ids: ids, _token: "{{ csrf_token() }}" },
                        success: function (response) { Swal.fire('Deleted!', response.message, 'success'); setTimeout(() => location.reload(), 1000); },
                        error: function () { Swal.fire('Error!', 'Failed to delete.', 'error'); }
                    });
                }
            });
        });

        // ✅ BULK ASSIGN GROUP LOGIC
        $('#bulk-assign-group-btn').on('click', function () {
            let ids = [];
            $('.student-checkbox:checked').each(function () { ids.push($(this).val()); });

            if (ids.length === 0) {
                Swal.fire('Warning', 'Please select at least one student.', 'warning');
                return;
            }
            var bulkGroupModalEl = document.getElementById('bulkGroupModal');
            if (!bulkGroupModalEl) return;
            var myModal = new bootstrap.Modal(bulkGroupModalEl);
            myModal.show();
        });

        $('#save-bulk-group').on('click', function() {
            let ids = [];
            $('.student-checkbox:checked').each(function () { ids.push($(this).val()); });
            
            let groupId = $('#bulk-group-id').val();
            let actionType = $('#bulk-action-type').val();

            if(!groupId) { alert('Please select a group'); return; }

            // Close modal manually
            var modalEl = document.getElementById('bulkGroupModal');
            if (!modalEl) return;
            var modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

            Swal.fire({ title: 'Processing...', text: 'Updating student groups', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

            $.ajax({
                url: "{{ route('students.bulkAssignGroup') }}",
                type: 'post',
                data: { ids: ids, group_id: groupId, action_type: actionType, _token: "{{ csrf_token() }}" },
                success: function (response) {
                    Swal.fire('Updated!', response.message, 'success');
                    setTimeout(() => { location.reload(); }, 1000);
                },
                error: function () { Swal.fire('Error!', 'Something went wrong.', 'error'); }
            });
        });

        // Edit Modal Populate
        var showModal = document.getElementById('showModal');
        if (showModal) {
            showModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                if (!button.hasAttribute('data-id')) return;
                var id = button.getAttribute('data-id');
                var name = button.getAttribute('data-name');
                var email = button.getAttribute('data-email');
                var address = button.getAttribute('data-address');
                var phone = button.getAttribute('data-phone');
                var regCode = button.getAttribute('data-reg_code');
                var enroll = button.getAttribute('data-enroll');
                var status = button.getAttribute('data-status');
                var group_ids = button.getAttribute('data_group_ids') || button.getAttribute('data-group_ids');
                var photo = button.getAttribute('data-photo');
                var modal = this;
                if (id) {
                    modal.querySelector('.modal-title').textContent = 'Edit Student';
                    modal.querySelector('#name-field').value = name;
                    modal.querySelector('#email-field').value = email;
                    modal.querySelector('#address-field').value = address;
                    modal.querySelector('#phone-field').value = phone;
                    modal.querySelector('#reg-code-field').value = regCode;
                    modal.querySelector('#enroll-field').value = enroll;
                    modal.querySelector('#status-field').value = status;
                    $('#group_ids-field').val(group_ids ? group_ids.split(',') : []).trigger('change');
                    modal.querySelector('#password-field').value = '';
                    modal.querySelector('#password-field').removeAttribute('required');
                    modal.querySelector('#add-btn').style.display = 'none';
                    modal.querySelector('#edit-btn').style.display = 'block';
                    if (photo) { modal.querySelector('#photo-preview').src = photo; modal.querySelector('#photo-preview').style.display = 'block'; } else { modal.querySelector('#photo-preview').style.display = 'none'; }
                    modal.querySelector('form').setAttribute('action', '{{ route("students.update", ":id") }}'.replace(':id', id));
                    if (!modal.querySelector('input[name="_method"]')) { modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">'); }
                }
            });

            showModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('student-form').reset();
                $('#group_ids-field').val([]).trigger('change');
                document.querySelector('.modal-title').textContent = 'Add Student';
                document.querySelector('#add-btn').style.display = 'block';
                document.querySelector('#edit-btn').style.display = 'none';
                document.querySelector('form').setAttribute('action', '{{ route("students.store") }}');
                var method = document.querySelector('input[name="_method"]');
                if(method) method.remove();
                document.querySelector('#photo-preview').style.display = 'none';
            });
        }

        // Delete Modal
        var deleteModal = document.getElementById('deleteRecordModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var action = "{{ route('students.destroy', ':id') }}".replace(':id', id);
                document.getElementById('delete-form').setAttribute('action', action);
            });
        }
        
        // Toggle Password
        $('#toggle-password').on('click', function() {
            var input = $('#password-field');
            var icon = $(this).find('i');
            if (input.attr('type') === 'password') { input.attr('type', 'text'); icon.removeClass('ri-eye-off-line').addClass('ri-eye-line'); } else { input.attr('type', 'password'); icon.removeClass('ri-eye-line').addClass('ri-eye-off-line'); }
        });
    });
</script>

@endsection
