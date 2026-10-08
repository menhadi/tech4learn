@extends('layouts.master')
@section('title', 'Attendance setup')
@section('content')
<div class="card"><div class="card-body">
    <h1 class="h4">Set up organisation attendance</h1>
    <p>Attendance belongs to an organisation. Create a fresh organisation and an active Owner or Admin account in the SaaS Control Center.</p>
    <p>Complete its attendance setup, then sign in with that organisation account on its configured domain to create centres, classes, sections and learners.</p>
    <p>Previous attendance records have not been imported. The platform administrator account manages setup; daily capture and review use organisation accounts.</p>
    <a class="btn btn-primary" href="{{ route('saas.index') }}">Open SaaS Control Center</a>
</div></div>
@endsection
