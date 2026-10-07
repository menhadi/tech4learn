@extends('layouts.master')
@section('rich-editor', true)
@section('title', isset($passage) ? 'Edit Passage' : 'Add Passage')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($passage) ? 'Edit Passage' : 'Add Passage')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ isset($passage) ? 'Edit Passage' : 'Add Passage' }}</h4>
            </div>
            <div class="card-body">
                <form method="POST"
                    action="{{ isset($passage) ? route('passages.update', $passage->id) : route('passages.store') }}">
                    @csrf
                    @if(isset($passage))
                    @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label for="name-field" class="form-label">Passage Name</label>
                        <input type="text" id="name-field" name="name" class="form-control"
                            placeholder="Enter Passage Name"
                            value="{{ old('name', isset($passage) ? $passage->name : '') }}" required />
                        @if ($errors->has('name'))
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                        @endif
                    </div>

                    @foreach($languages as $language)
                    <x-textarea-editor id="passage-field-{{ $language->id }}" name="passages[{{ $language->id }}]"
                        label="{{ $language->name }} Passage" placeholder="Enter Passage in {{ $language->name }}"
                        :value="old('passages.' . $language->id, isset($passage) ? $passage->langs->where('language_id', $language->id)->first()->passage ?? '' : '')" />
                    @endforeach

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('passages.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success ms-2">{{ isset($passage) ? 'Update Passage' : 'Add
                            Passage' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('success'))
        Swal.fire({
            icon: 'success'
            , title: 'Success'
            , text: '{{ session('success') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error'
            , title: 'Error'
            , text: '{{ session('error') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error'
            , title: 'Validation Error'
            , text: '{{ implode(", ", $errors->all()) }}'
            , timer: 5000
            , showConfirmButton: true
        });
        @endif
    });
</script>
@endsection