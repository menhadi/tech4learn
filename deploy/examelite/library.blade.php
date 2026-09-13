@extends('layouts.master')
@section('title', 'Shared exam library')
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Exam workspace')
@slot('title', 'Shared exam library')
@endcomponent
<div class="card"><div class="card-body">
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<p>Open your organisation’s version to make changes. Shared originals stay unchanged.</p>
<form method="get" class="el-filter-bar">
<label>Content<select name="type" class="form-control">
@foreach(['exam'=>'Exams','question'=>'Questions','subject'=>'Subjects','topic'=>'Topics','subtopic'=>'Subtopics','section'=>'Sections'] as $value=>$label)
<option value="{{ $value }}" @selected($kind===$value)>{{ $label }}</option>
@endforeach
</select></label>
<label>Search<input class="form-control" name="search" value="{{ $search }}" maxlength="120"></label><button class="btn el-btn-primary">Search</button>
</form>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Content</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ \Illuminate\Support\Str::limit(strip_tags($row->$column),220) }}</td><td>
<form method="post" action="/tech4learn/library/{{ $kind }}/{{ $row->id }}/copy">@csrf
<button class="btn el-btn-primary">Open my organisation’s version</button></form></td></tr>
@empty <tr><td colspan="2">No shared content matches your search.</td></tr>
@endforelse
</tbody></table></div>{{ $rows->links() }}
</div></div>
@endsection
