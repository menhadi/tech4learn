@extends('layouts.master')
@section('title', 'Demo Bot Students')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Students & Results')
@slot('title', 'Demo Bot Students')
@endcomponent

@include('students.admin.type-tabs')

@php
    $label = function ($value) {
        if (is_array($value)) {
            return reset($value) ?: '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return reset($decoded) ?: $value;
            }
        }

        return (string) $value;
    };
@endphp

<div class="el-page-header mb-3">
    <div class="el-page-header-main">
        <span class="el-page-kicker">Leaderboard Preview</span>
        <h1 class="el-page-title">Demo Bot Students</h1>
        <p class="el-page-subtitle">Create temporary demo learners with exam attempts for front-end leaderboards, then remove them batch-wise later.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="el-stats-grid mb-3">
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Demo Students</div>
            <div class="el-stat-value">{{ number_format($stats['students']) }}</div>
            <div class="el-stat-note">Marked separately from real users</div>
        </div>
        <span class="el-stat-icon"><i class="ri-user-smile-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Demo Attempts</div>
            <div class="el-stat-value">{{ number_format($stats['results']) }}</div>
            <div class="el-stat-note">Visible in leaderboards</div>
        </div>
        <span class="el-stat-icon"><i class="ri-file-list-3-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Average Score</div>
            <div class="el-stat-value">{{ $stats['average_percent'] }}%</div>
            <div class="el-stat-note">Across demo attempts</div>
        </div>
        <span class="el-stat-icon"><i class="ri-line-chart-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Batches</div>
            <div class="el-stat-value">{{ number_format($stats['batches']) }}</div>
            <div class="el-stat-note">Delete a full batch anytime</div>
        </div>
        <span class="el-stat-icon"><i class="ri-stack-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Study Cards</div>
            <div class="el-stat-value">{{ number_format($stats['study_cards']) }}</div>
            <div class="el-stat-note">{{ number_format($stats['study_learners']) }} demo learners</div>
        </div>
        <span class="el-stat-icon"><i class="ri-stack-line"></i></span>
    </div>
    <div class="el-stat">
        <div>
            <div class="el-stat-label">Study Points</div>
            <div class="el-stat-value">{{ number_format($stats['study_points']) }}</div>
            <div class="el-stat-note">Used in study-card leaderboard</div>
        </div>
        <span class="el-stat-icon"><i class="ri-star-line"></i></span>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="el-panel h-100">
            <div class="el-panel-header">
                <h2 class="el-panel-title">Create Demo Users</h2>
            </div>
            <form method="POST" action="{{ route('demo-students.generate') }}" class="p-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label">How many users?</label>
                    <input type="number" name="count" class="form-control" min="1" max="200" value="{{ old('count', 20) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Groups</label>
                    <select name="group_mode" class="form-control mb-2" id="demoGroupMode">
                        <option value="selected" {{ old('group_mode', 'selected') === 'selected' ? 'selected' : '' }}>Selected groups</option>
                        <option value="all" {{ old('group_mode') === 'all' ? 'selected' : '' }}>All groups</option>
                    </select>
                    <select name="group_ids[]" class="form-control select2" multiple id="demoGroupIds">
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected(in_array($group->id, old('group_ids', [])))>{{ $label($group->group_name) }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Demo students are attached to one group, so they follow the same group access logic as real students.</small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="demoCategoryId">Category <span class="text-muted">(optional)</span></label>
                        <select name="category_level_1" class="form-control" id="demoCategoryId">
                            <option value="">All categories</option>
                            @foreach($parentCategories as $category)
                                <option value="{{ $category->id }}"
                                    data-groups="{{ $category->groups->pluck('id')->implode(',') }}"
                                    @selected((string) old('category_level_1') === (string) $category->id)>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6" data-subcategory-ui>
                        <label class="form-label" for="demoSubcategoryId">Subcategory <span class="text-muted">(optional)</span></label>
                        <select name="category_level_2" class="form-control" id="demoSubcategoryId">
                            <option value="">All subcategories</option>
                            @foreach($parentCategories as $category)
                                @foreach($category->children as $subcategory)
                                    <option value="{{ $subcategory->id }}" data-parent="{{ $category->id }}"
                                        @selected((string) old('category_level_2') === (string) $subcategory->id)>{{ $subcategory->title }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Limits generated packages and exam attempts to this hierarchy, producing accurate category and subcategory leaderboards.</small>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Packages/user</label>
                        <input type="number" name="packages_per_student" class="form-control" min="1" max="20" value="{{ old('packages_per_student', 1) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Exams/user</label>
                        <input type="number" name="exams_per_student" class="form-control" min="1" max="10" value="{{ old('exams_per_student', 2) }}" required>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Min score %</label>
                        <input type="number" name="score_min" class="form-control" min="0" max="100" step="0.01" value="{{ old('score_min', 35) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Max score %</label>
                        <input type="number" name="score_max" class="form-control" min="0" max="100" step="0.01" value="{{ old('score_max', 92) }}" required>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Study cards/user</label>
                        <input type="number" name="study_cards_per_student" class="form-control" min="0" max="100" value="{{ old('study_cards_per_student', 12) }}">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="generate_study_cards" value="1" id="generateStudyCards" @checked(old('generate_study_cards', '1'))>
                            <label class="form-check-label" for="generateStudyCards">Add study-card points</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn el-btn-primary el-btn-icon w-100 mt-3">
                    <i class="ri-robot-2-line"></i> Generate Demo Batch
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="el-panel">
            <div class="el-panel-header">
                <h2 class="el-panel-title">Demo User Table</h2>
                @if($batches->isNotEmpty())
                    <form method="POST" action="{{ route('demo-students.deleteBatch') }}" data-swal-confirm="Delete this full demo batch? This cannot be undone." class="d-flex gap-2">
                        @csrf
                        <select name="batch_id" class="form-control">
                            @foreach($batches as $batch)
                                <option value="{{ $batch->demo_batch_id }}">{{ $batch->demo_batch_id }} ({{ $batch->total }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn el-btn-danger el-btn-icon"><i class="ri-delete-bin-line"></i> Delete Batch</button>
                    </form>
                @endif
            </div>

            <form method="GET" action="{{ route('demo-students.index') }}" class="el-filter-bar">
                <div class="el-filter-field">
                    <label>Batch</label>
                    <select name="batch" class="form-control">
                        <option value="">All batches</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->demo_batch_id }}" @selected(request('batch') === $batch->demo_batch_id)>{{ $batch->demo_batch_id }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="el-filter-field">
                    <label>Search</label>
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email or batch">
                </div>
                <div class="el-filter-field">
                    <label>Show</label>
                    <select name="per_page" class="form-control">
                        @foreach([50, 100, 500] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 50) === $size)>{{ $size }} per page</option>
                        @endforeach
                    </select>
                </div>
                <div class="el-filter-actions el-filter-action-field">
                    <button class="btn el-btn-primary el-btn-icon" type="submit"><i class="ri-search-line"></i> Search</button>
                    <a class="btn el-btn-secondary el-btn-icon" href="{{ route('demo-students.index') }}"><i class="ri-refresh-line"></i> Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table el-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Demo Student</th>
                            <th>Group</th>
                            <th>Results</th>
                            <th>Avg Score</th>
                            <th>Study Cards</th>
                            <th>Study Points</th>
                            <th>Batch</th>
                            <th>Generated</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($demoStudents as $student)
                            <tr>
                                <td>
                                    <strong>{{ $student->name }}</strong>
                                    <div class="text-muted small">{{ $student->email }}</div>
                                </td>
                                <td>
                                    @foreach($student->groups as $group)
                                        <span class="badge bg-light text-dark">{{ $label($group->group_name) }}</span>
                                    @endforeach
                                </td>
                                <td>{{ number_format($student->exam_results_count) }}</td>
                                <td>{{ round((float) $student->exam_results_avg_percent, 2) }}%</td>
                                <td>{{ number_format($student->flashcard_progress_count) }}</td>
                                <td>{{ number_format((int) $student->study_points_total) }}</td>
                                <td><span class="badge el-badge-soft">{{ $student->demo_batch_id }}</span></td>
                                <td>{{ optional($student->demo_generated_at)->format('d M, Y h:i A') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('demo-students.destroy', $student) }}" data-swal-confirm="Delete this demo student and related demo data?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn el-btn-danger btn-sm"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No demo students created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                {{ $demoStudents->links() }}
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('demoGroupMode');
    const groups = document.getElementById('demoGroupIds');
    const category = document.getElementById('demoCategoryId');
    const subcategory = document.getElementById('demoSubcategoryId');

    function refreshHierarchy() {
        if (!mode || !groups || !category || !subcategory) return;

        groups.disabled = mode.value === 'all';
        const selectedGroups = mode.value === 'all'
            ? []
            : Array.from(groups.selectedOptions).map(option => option.value);

        Array.from(category.options).forEach(option => {
            if (!option.value) return;
            const allowedGroups = (option.dataset.groups || '').split(',').filter(Boolean);
            const visible = selectedGroups.length === 0
                || allowedGroups.length === 0
                || selectedGroups.every(groupId => allowedGroups.includes(groupId));
            option.hidden = !visible;
            option.disabled = !visible;
        });

        if (category.selectedOptions[0]?.disabled) category.value = '';
        const parentId = category.value;

        Array.from(subcategory.options).forEach(option => {
            if (!option.value) return;
            const visible = parentId !== '' && option.dataset.parent === parentId;
            option.hidden = !visible;
            option.disabled = !visible;
        });

        if (subcategory.selectedOptions[0]?.disabled || !parentId) subcategory.value = '';
    }

    mode?.addEventListener('change', refreshHierarchy);
    groups?.addEventListener('change', refreshHierarchy);
    category?.addEventListener('change', refreshHierarchy);
    refreshHierarchy();
});
</script>
@endsection
