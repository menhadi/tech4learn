@extends('layouts.master')
@section('title','Source URL Question Import')
@section('content')
@component('components.breadcrumb') @slot('li_1','Academic') @slot('title','Source URL Question Import & Audit') @endcomponent
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
<div class="col-xl-7"><div class="card h-100"><div class="card-body">
<h4>Extract new questions</h4>
<p class="text-muted">Upload a CSV containing <code>question_source_url</code> and hierarchy columns, or paste URLs. Existing source URLs are skipped before a webpage is fetched.</p>
<form method="post" action="{{ route('source-question-import.store') }}" enctype="multipart/form-data">@csrf
<div class="row g-3"><div class="col-md-6"><label class="form-label">CSV file</label><input type="file" name="source_file" accept=".csv,.txt" class="form-control"><div class="form-text">CSV supplies missing metadata (groups, category, exams, subject, section, topic, subtopic, type, difficulty and language). When the webpage also supplies a value, the webpage wins.</div></div>
<div class="col-md-6"><label class="form-label">Question URLs</label><textarea name="source_urls" rows="5" class="form-control" placeholder="https://example.com/question/one"></textarea><div class="form-text">One URL per line. Supply either CSV or URLs.</div></div>
<div class="col-md-6"><label class="form-label">Source adapter</label><select name="adapter" class="form-select">@foreach($adapters as $adapter)<option value="{{ $adapter['key'] }}">{{ $adapter['name'] }}@if($adapter['pattern']) — {{ $adapter['pattern'] }}@endif</option>@endforeach</select><div class="form-text">A CSV row can override this using its <code>adapter</code> column.</div></div>
<div class="col-md-6"><label class="form-label">Default group</label><input name="default_group" class="form-control" placeholder="NEET or Engineering"></div></div>
<button class="btn btn-primary mt-3"><i class="ri-download-cloud-line me-1"></i>Queue new extraction</button>
</form></div></div></div>

<div class="col-xl-5"><div class="card h-100 border-warning"><div class="card-body">
<h4>Audit existing questions</h4>
<p class="text-muted">Re-fetch an existing question with its original adapter and compare every supported field. Audit never creates a question.</p>
<form method="post" action="{{ route('source-question-import.audit.store') }}" enctype="multipart/form-data">@csrf
<div class="mb-3"><label class="form-label">Audit CSV</label><input type="file" name="source_file" accept=".csv,.txt" class="form-control"></div>
<div class="mb-3"><label class="form-label">Existing question URLs</label><textarea name="source_urls" rows="5" class="form-control" placeholder="Paste URLs already imported into ExamElite"></textarea></div>
<div class="mb-3"><label class="form-label">Adapter</label><select name="adapter" class="form-select">@foreach($adapters as $adapter)<option value="{{ $adapter['key'] }}">{{ $adapter['name'] }}</option>@endforeach</select><div class="form-text">Auto-detect reuses the adapter stored by the original import.</div></div>
<button class="btn btn-warning"><i class="ri-search-eye-line me-1"></i>Queue audit only</button>
</form></div></div></div>
</div>

<div class="card border-primary"><div class="card-body">
<h4>Crawl a base URL for remaining questions</h4>
<p class="text-muted">Scan listing and pagination pages, discover individual question URLs, skip URLs already imported into ExamElite, and queue only new questions. Discovery and extraction continue in the background.</p>
<form method="post" action="{{ route('source-question-import.crawl.store') }}">@csrf
<div class="row g-3">
<div class="col-lg-6"><label class="form-label">Base or listing URL *</label><input type="url" name="base_url" value="{{ old('base_url') }}" class="form-control" placeholder="https://questions.examside.com/past-years/..." required><div class="form-text">The crawler remains on this host and within the base URL path.</div></div>
<div class="col-lg-3"><label class="form-label">Source adapter *</label><select name="adapter" class="form-select" required>@foreach($adapters as $adapter)@if($adapter['key'] !== 'auto')<option value="{{ $adapter['key'] }}" @selected(old('adapter','examside') === $adapter['key'])>{{ $adapter['name'] }}</option>@endif @endforeach</select></div>
<div class="col-lg-3"><label class="form-label">Default group *</label><select name="default_group_id" class="form-select" required><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string)old('default_group_id') === (string)$group->id)>{{ $group->group_name }}</option>@endforeach</select><div class="form-text">Used when the webpage does not supply a group.</div></div>
<div class="col-lg-6"><label class="form-label">Question URL wildcard pattern</label><input name="question_pattern" value="{{ old('question_pattern') }}" class="form-control" placeholder="*://questions.example.com/*/question/*"><div class="form-text">Optional for ExamSide because its question URL format is built in. Required for other adapters.</div></div>
<div class="col-lg-3"><label class="form-label">Maximum listing pages</label><input type="number" name="max_pages" value="{{ old('max_pages',100) }}" min="1" max="500" class="form-control" required></div>
<div class="col-lg-3"><label class="form-label">Maximum new questions</label><input type="number" name="max_questions" value="{{ old('max_questions',1000) }}" min="1" max="10000" class="form-control" required></div>
</div>
<button class="btn btn-primary mt-3"><i class="ri-radar-line me-1"></i>Start discovery and extraction</button>
</form></div></div>

<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-1">Website adapters</h5><p class="text-muted mb-0">ExamSide is built in. Add selector profiles for websites with different HTML structures.</p></div><button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-adapter">Add website adapter</button></div>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Name</th><th>Key</th><th>URL pattern</th><th>Version</th><th>Status</th><th></th></tr></thead><tbody><tr><td>ExamSide</td><td><code>examside</code></td><td><code>*://*.examside.com/*</code></td><td>Built in</td><td><span class="badge bg-success">Enabled</span></td><td></td></tr>@foreach($adapterProfiles as $profile)<tr><td>{{ $profile->name }}</td><td><code>{{ $profile->key }}</code></td><td><code>{{ $profile->url_pattern }}</code></td><td>{{ $profile->version }}</td><td><span class="badge {{ $profile->enabled ? 'bg-success' : 'bg-secondary' }}">{{ $profile->enabled ? 'Enabled' : 'Disabled' }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('source-question-adapters.edit',$profile) }}">Edit</a>@if($profile->enabled)<form class="d-inline" method="post" action="{{ route('source-question-adapters.destroy',$profile) }}" onsubmit="return confirm('Disable this adapter? Existing audit history will remain.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Disable</button></form>@endif</td></tr>@endforeach</tbody></table></div>
<div class="collapse {{ $errors->has('key') || $errors->has('question_selector') ? 'show' : '' }} mt-3" id="new-adapter"><div class="border rounded p-3"><h5>Create selector adapter</h5><p class="text-muted">Use CSS selectors such as <code>.question-text</code> or XPath expressions beginning with <code>//</code>.</p><form method="post" action="{{ route('source-question-adapters.store') }}">@csrf @php $profile = new \App\Models\SourceQuestionAdapterProfile(['enabled'=>true]); @endphp @php $selectors = []; @endphp @include('source-question-import.partials.adapter-fields')<button class="btn btn-primary mt-3">Create adapter</button></form></div></div>
</div></div>

<div class="card"><div class="card-body"><h5>Recent import and audit runs</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Mode</th><th>Adapter</th><th>Status</th><th>Rows</th><th></th></tr></thead><tbody>@forelse($runs as $run)<tr><td>#{{ $run->id }}</td><td><span class="badge {{ ($run->mode ?: 'import') === 'audit' ? 'bg-warning text-dark' : 'bg-primary' }}">{{ ucfirst($run->mode ?: 'import') }}</span></td><td>{{ $run->adapter ?: 'auto' }}</td><td>{{ $run->status }}</td><td>{{ $run->items_count }}/{{ $run->total }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('source-question-import.show',$run) }}">Open</a> @if(!in_array($run->status,['queued','processing'],true))<form method="post" action="{{ route('source-question-import.destroy',$run) }}" class="d-inline" onsubmit="return confirm('Remove this run?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-muted">No runs yet.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
