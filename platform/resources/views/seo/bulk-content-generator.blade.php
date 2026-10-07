@extends('layouts.master')

@section('title')
    Bulk AI Content Generator
@endsection

@section('content')
    @component('components.breadcrumb')
        @slot('li_1')
            AI Content
        @endslot
        @slot('title')
            Bulk AI Content Generator
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Bulk Generate Page Content</h4>
                </div>

                <div class="card-body">
                    <div class="alert alert-info">
                        Bulk content generation page is ready. Generator action will be connected next.
                    </div>

                    <form method="POST" action="{{ route('admin.ai-content.bulk.generate') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Content Type</label>
                            <select name="content_type" class="form-control" required>
                                <option value="packages">Packages</option>
                                <option value="exams">Exams</option>
                                <option value="website_pages">Website Pages</option>
                                <option value="about_us">About Us</option>
                                <option value="all">All Supported Types</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select name="mode" class="form-control" required>
                                <option value="empty">Generate only empty content fields</option>
                                <option value="all">Regenerate and overwrite existing content</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Batch Limit</label>
                            <select name="limit" class="form-control" required>
                                <option value="10" selected>10 items</option>
                                <option value="25">25 items</option>
                                <option value="50">50 items</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            Generate Content Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
