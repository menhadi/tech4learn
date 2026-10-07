@extends('layouts.master')
@section('title','PYP Pages & Analysis')
@section('content')
@component('components.breadcrumb')
    @slot('li_1','Website & Content')
    @slot('title','PYP Pages & Analysis')
@endcomponent

<form method="POST" action="{{ route('pyp-pages.update') }}">
    @csrf @method('PUT')
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-1">Generated PYP pages</h4>
            <p class="text-muted mb-0">Create SEO-friendly year, subject, topic and subtopic pages from your existing cleaned database. No exam or question is duplicated.</p>
        </div>
        <div class="card-body">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <div class="row g-4">
                @foreach([
                    'enabled'=>'Enable generated PYP pages',
                    'subject_pages'=>'Generate subject pages',
                    'topic_pages'=>'Generate topic pages',
                    'subtopic_pages'=>'Generate subtopic pages',
                    'analysis_enabled'=>'Enable trends and analysis',
                ] as $field=>$label)
                    <div class="col-lg-4 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="form-check form-switch">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked(old($field,$settings[$field]))>
                                <label class="form-check-label fw-semibold" for="{{ $field }}">{{ $label }}</label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-3"><label class="form-label">Minimum questions per indexed page</label><input class="form-control" type="number" min="1" max="500" name="min_questions" value="{{ old('min_questions',$settings['min_questions']) }}"><div class="form-text">Smaller pages remain available but use noindex.</div></div>
                <div class="col-md-3"><label class="form-label">Minimum years for trend claims</label><input class="form-control" type="number" min="1" max="20" name="min_years_for_historical" value="{{ old('min_years_for_historical',$settings['min_years_for_historical']) }}"></div>
                <div class="col-md-3"><label class="form-label">Refresh generated data (minutes)</label><input class="form-control" type="number" min="1" max="10080" name="cache_minutes" value="{{ old('cache_minutes',$settings['cache_minutes']) }}"></div>
                <div class="col-md-3"><label class="form-label">Sample questions per page</label><input class="form-control" type="number" min="1" max="20" name="sample_questions" value="{{ old('sample_questions',$settings['sample_questions']) }}"></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-1">Package controls</h4><p class="text-muted mb-0">Only packages containing active previous-year papers appear here.</p></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Package</th><th style="width:110px">Papers</th><th style="width:100px">Enabled</th><th style="width:160px">Analysis</th><th style="width:100px">Index</th><th>SEO override</th><th style="width:110px">Preview</th></tr></thead>
                    <tbody>
                    @forelse($packages as $package)
                        @php $item=$packageSettings->get($package->id); $name=is_array($package->name)?($package->name['en']??reset($package->name)):$package->name; @endphp
                        <tr>
                            <td><strong>{{ $name }}</strong><div class="text-muted small">{{ $package->groups->pluck('group_name')->map(fn($v)=>is_array($v)?($v['en']??reset($v)):$v)->implode(', ') }}</div></td>
                            <td>{{ $package->pyp_exams_count }}</td>
                            <td><input type="hidden" name="packages[{{ $package->id }}][enabled]" value="0"><input class="form-check-input" type="checkbox" name="packages[{{ $package->id }}][enabled]" value="1" @checked(old("packages.{$package->id}.enabled",$item?->enabled ?? true))></td>
                            <td><select class="form-select" name="packages[{{ $package->id }}][analysis_mode]"><option value="historical" @selected(($item?->analysis_mode??'historical')==='historical')>Historical</option><option value="content_only" @selected($item?->analysis_mode==='content_only')>Content only</option><option value="disabled" @selected($item?->analysis_mode==='disabled')>Disabled</option></select></td>
                            <td><input type="hidden" name="packages[{{ $package->id }}][indexable]" value="0"><input class="form-check-input" type="checkbox" name="packages[{{ $package->id }}][indexable]" value="1" @checked(old("packages.{$package->id}.indexable",$item?->indexable ?? true))></td>
                            <td><input class="form-control mb-2" name="packages[{{ $package->id }}][meta_title]" value="{{ old("packages.{$package->id}.meta_title",$item?->meta_title) }}" placeholder="Optional meta title"><textarea class="form-control" rows="2" name="packages[{{ $package->id }}][meta_description]" placeholder="Optional meta description">{{ old("packages.{$package->id}.meta_description",$item?->meta_description) }}</textarea></td>
                            <td>@if($package->slug)<a class="btn btn-sm el-btn-secondary" target="_blank" href="{{ route('pyp.index',$package->slug) }}">Open <i class="ri-external-link-line"></i></a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No active previous-year-paper packages were found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end pb-4"><button class="btn el-btn-primary"><i class="ri-save-line me-1"></i> Save PYP Settings</button></div>
</form>
@endsection
