@extends('layouts.master')

@section('title', 'Study Cards')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Packages')
    @slot('title', 'Study Cards')
@endcomponent

<style>
    .flashcard-toolbar,
    .flashcard-panel { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 20px rgba(15,23,42,.05); }
    .flashcard-toolbar { padding:18px; }
    .flashcard-panel .table thead th { background:var(--el-table-header-bg, color-mix(in srgb, var(--el-primary) 10%, #fff)); color:var(--el-heading); font-weight:700; }
    .flashcard-scope span { display:inline-flex; margin:2px; padding:4px 9px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-size:12px; font-weight:700; }
    .flashcard-toolbar .btn,
    .flashcard-panel .btn { min-height:40px; border-radius:6px; font-weight:700; box-shadow:none !important; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .flashcard-toolbar__table-tools { display:flex; gap:10px; justify-content:flex-end; align-items:flex-end; }
    .flashcard-toolbar__search { width:min(360px, 100%); }
    .flashcard-toolbar__show { width:160px; }
    .flashcard-card-link { min-width:92px; min-height:36px; padding:8px 12px; }
    .flashcard-panel .dropdown-toggle::after { margin-left:6px; }
    @media (max-width: 767.98px) {
        .flashcard-toolbar__table-tools { justify-content:stretch; flex-wrap:wrap; }
        .flashcard-toolbar__search,
        .flashcard-toolbar__show { width:100%; }
    }
</style>

<div class="flashcard-toolbar mb-3">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Package</label>
            <select name="package_id" class="form-select">
                <option value="">All Packages</option>
                @foreach($packages as $packageOption)
                    <option value="{{ $packageOption->id }}" {{ (string) request('package_id') === (string) $packageOption->id ? 'selected' : '' }}>{{ $packageOption->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn el-btn-primary flex-fill" type="submit"><i class="ri-search-line me-1"></i> Search</button>
            <a href="{{ route('flashcards.index') }}" class="btn el-btn-secondary flex-fill">Reset</a>
        </div>
        <div class="col-12 flashcard-toolbar__table-tools">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control flashcard-toolbar__search" placeholder="Search study card set">
            <select name="per_page" class="form-select flashcard-toolbar__show" onchange="this.form.submit()">
                @foreach([50, 100, 500] as $size)
                    <option value="{{ $size }}" {{ (int) ($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }} sets</option>
                @endforeach
            </select>
        </div>
    </form>
</div>

<div class="flashcard-panel">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <div>
            <h4 class="mb-1">Study Card Sets</h4>
            <div class="text-muted">Scope means where the set belongs: whole package, or narrowed by group, subject, topic, and sub topic.</div>
        </div>
        <button class="btn el-btn-primary" data-bs-toggle="modal" data-bs-target="#createFlashcardSetModal">
            <i class="ri-add-line me-1"></i> Add Set
        </button>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Package</th>
                    <th>Scope</th>
                    <th>Total Cards</th>
                    <th>View Cards</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sets as $set)
                    <tr>
                        <td>
                            <strong>{{ $set->title }}</strong>
                            <div class="text-muted small">{{ ucfirst($set->source_type ?? 'manual') }}</div>
                        </td>
                        <td>{{ $set->package?->name ?? '-' }}</td>
                        <td class="flashcard-scope">
                            @if($set->group)<span>{{ $set->group->group_name }}</span>@endif
                            @if($set->subject)<span>{{ $set->subject->subject_name }}</span>@endif
                            @if($set->topic)<span>{{ $set->topic->name }}</span>@endif
                            @if($set->stopic)<span>{{ $set->stopic->name }}</span>@endif
                            @if(! $set->group && ! $set->subject && ! $set->topic && ! $set->stopic)<span>Package</span>@endif
                        </td>
                        <td>{{ $set->cards_count }}</td>
                        <td>
                            <a href="{{ route('flashcards.show', $set) }}" class="btn el-btn-primary btn-sm flashcard-card-link">
                                View Cards
                            </a>
                        </td>
                        <td><span class="badge {{ $set->status ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $set->status ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn el-btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ri-more-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#editFlashcardSetModal{{ $set->id }}">Edit</button></li>
                                    <li>
                                        <form action="{{ route('flashcards.destroy', $set) }}" method="POST" data-swal-confirm="Remove this study card set?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit">Remove</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No study card sets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $sets->links() }}</div>
</div>

@include('flashcards.partials.set-modal', ['modalId' => 'createFlashcardSetModal', 'mode' => 'create', 'packages' => $packages, 'groups' => $groups, 'subjects' => $subjects, 'topics' => $topics, 'stopics' => $stopics])

@foreach($sets as $set)
    @include('flashcards.partials.set-modal', ['modalId' => 'editFlashcardSetModal' . $set->id, 'mode' => 'edit', 'set' => $set, 'packages' => $packages, 'groups' => $groups, 'subjects' => $subjects, 'topics' => $topics, 'stopics' => $stopics])
@endforeach
@endsection
