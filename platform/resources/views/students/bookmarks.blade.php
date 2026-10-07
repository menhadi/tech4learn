@extends('students.layouts.app')

@section('title') @lang('messages.bookmarks_title') @endsection

@push('styles')
<style>
    .bookmark-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid var(--el-border);
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }
    .bookmark-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .bookmark-icon-bg {
        background: var(--el-primary);
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
    }
    .bookmark-count {
        position: absolute;
        top: 80px;
        right: 20px;
        background: var(--el-secondary);
        color: var(--theme-button-text, #fff);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 3px solid #fff;
    }
</style>
@endpush

@section('content')

@component('components.breadcrumb')
    @slot('li_1') @lang('messages.dash_breadcrumb_dashboards') @endslot
    @slot('title') @lang('messages.bookmarks_breadcrumb_my_bookmarks') @endslot
@endcomponent

<div class="container-fluid">
    @if(count($bookmarksByExam) > 0)
        <div class="row g-4">
            @foreach($bookmarksByExam as $data)
            <div class="col-md-6 col-xl-3">
                <div class="card bookmark-card h-100 shadow-sm">
                    <div class="bookmark-icon-bg">
                        <i class="ri-bookmark-3-line"></i>
                    </div>
                    <div class="bookmark-count shadow-sm">{{ $data['total_bookmarks'] }}</div>
                    
                    <div class="card-body text-center pt-4">
                        <h5 class="card-title fw-bold text-dark text-truncate" title="{{ $data['exam_name'] }}">{{ $data['exam_name'] }}</h5>
                        <p class="text-muted small mb-3">{{ __('ui.saved_questions') }}</p>
                        
                        <a href="{{ route('student.viewBookmarks', ['exam' => $data['exam_id']]) }}"
                           class="btn w-100 rounded-pill" style="background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff);">
                           Review Questions <i class="ri-arrow-right-line align-middle ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <div class="row justify-content-center">
            <div class="col-md-6 text-center py-5">
                <i class="ri-bookmark-line d-block mb-3" style="font-size: 72px; color: var(--el-primary); opacity: .35;"></i>
                <h4 class="mt-4 text-muted">@lang('messages.bookmarks_empty_title')</h4>
                <p class="text-muted">@lang('messages.bookmarks_empty_desc')</p>
                <a href="{{ route('student.myexams') }}" class="btn mt-3" style="background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff);">Go to My Exams</a>
            </div>
        </div>
    @endif
</div>
@endsection
