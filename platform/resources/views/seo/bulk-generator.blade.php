@extends('layouts.master')

@section('title')
    Bulk SEO Generator
@endsection

@section('content')
    @component('components.breadcrumb')
        @slot('li_1')
            SEO
        @endslot
        @slot('title')
            Bulk SEO Generator
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Bulk Generate SEO Metadata</h4>
                </div>

                <div class="card-body">
                    @if (session('seo_bulk_result'))
                        @php $result = session('seo_bulk_result'); @endphp
                        <div class="alert alert-success">
                            <strong>Done.</strong>
                            Updated {{ $result['updated'] }} item(s), skipped {{ $result['skipped'] }} item(s).
                        </div>

                        @foreach (($result['details'] ?? []) as $type => $detail)
                            @if (($detail['updated'] ?? 0) > 0 || ($detail['skipped'] ?? 0) > 0)
                                <div class="mb-3">
                                    <h6 class="text-capitalize">{{ str_replace('_', ' ', $type) }}</h6>
                                    <p class="mb-1">
                                        Updated: {{ $detail['updated'] ?? 0 }},
                                        Skipped: {{ $detail['skipped'] ?? 0 }}
                                    </p>
                                    @if (!empty($detail['items']))
                                        <ul class="mb-0">
                                            @foreach (array_slice($detail['items'], 0, 10) as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    @endif

                    <form method="POST" action="{{ route('admin.seo.bulk.generate') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Content Type</label>
                            <select name="content_type" class="form-control" required>
                                <option value="all">All Content Types</option>
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Includes groups, categories{{ ($subcategoriesEnabled ?? true) ? ', subcategories' : '' }}, packages, exams, website pages and about page.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select name="mode" class="form-control" required>
                                <option value="empty">Generate only empty SEO fields</option>
                                <option value="all">Regenerate and overwrite existing SEO fields</option>
                            </select>
                            <small class="text-muted">Use empty mode for normal work. Regenerate all will overwrite manual SEO.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Batch Limit</label>
                            <select name="limit" class="form-control" required>
                                <option value="10">10 items</option>
                                <option value="25" selected>25 items</option>
                                <option value="50">50 items</option>
                                <option value="100">100 items</option>
                                <option value="200">200 items</option>
                            </select>
                            <small class="text-muted">For many exams, run multiple batches to avoid timeout and control AI cost.</small>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            Generate SEO Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
