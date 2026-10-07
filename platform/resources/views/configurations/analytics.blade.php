@extends('layouts.master')
@section('title', 'Google Analytics 4')
@section('content')
<div class="card"><div class="card-body">
<h1>Google Analytics 4</h1>
<p>Connect public website visits and engagement for <strong>{{ $host }}</strong>. Administrator and student dashboard layouts do not load this tag.</p>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(!$ready)<div class="alert alert-warning">Analytics settings require the pending application migration before they can be saved.</div>@endif
<form method="post" action="{{ route('configurations.analytics.update') }}">@csrf @method('PUT')
<div class="mb-3"><label for="ga-enabled" class="form-label">Tracking</label><select id="ga-enabled" name="enabled" class="form-select"><option value="0" @selected(!old('enabled',$analytics['enabled']))>Disabled</option><option value="1" @selected(old('enabled',$analytics['enabled']))>Enabled</option></select></div>
<div class="mb-3"><label for="ga-id" class="form-label">Measurement ID</label><input id="ga-id" name="measurement_id" class="form-control" value="{{ old('measurement_id',$analytics['measurement_id']) }}" placeholder="G-XL23PFK983" maxlength="22" pattern="G-[A-Z0-9]{4,20}"></div>
<p>New Exam Elite property: <strong>G-XL23PFK983</strong>. Use this ID on examelite.com. No scripts or credentials need to be pasted.</p>
<button class="btn btn-primary" @disabled(!$ready)>Save analytics</button>
</form>
<p class="mt-3">Saving connects the tag; receipt is verified separately. Visit the public website, then open <a href="https://analytics.google.com/analytics/web/#/a254227929p557322257/realtime/overview" target="_blank" rel="noopener">GA4 Realtime</a>. Scrolls, downloads, outbound links, site search and video measurement are controlled in GA4’s web-stream settings.</p>
<a href="{{ route('configurations.general') }}">Back to general configuration</a>
</div></div>
@endsection
