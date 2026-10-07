@extends('students.layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="row">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('messages.profile_title') }}</h4>
            </div>
            <div class="card-body">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="{{ route('student.profile') }}">{{ __('messages.edit_profile_nav_profile') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('student.editProfile') }}">{{ __('messages.profile_edit_button') }}</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('student.changePassword') }}">Change
                            Password</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('messages.edit_profile_nav_profile') }}</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>{{ __('messages.auth_label_name') }}</th>
                        <td>{{ $student->name }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.profile_label_email') }}</th>
                        <td>{{ $student->email }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.profile_label_phone') }}</th>
                        <td>{{ $student->phone }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.profile_label_alt_phone') }}</th>
                        <td>{{ $student->guardian_phone }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.edit_profile_label_enrollment') }}</th>
                        <td>{{ $student->enroll }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.profile_label_admission_date') }}</th>
                        <td>{{ $student->created_at->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('messages.profile_label_address') }}</th>
                        <td>{{ $student->address }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection