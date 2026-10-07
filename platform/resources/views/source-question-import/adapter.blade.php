@extends('layouts.master')
@section('title','Edit Source Adapter')
@section('content')
@component('components.breadcrumb') @slot('li_1','Academic') @slot('title','Edit Source Adapter') @endcomponent
@php($selectors = (array) $adapter->selectors)
<div class="card"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">{{ $adapter->name }}</h4><p class="text-muted mb-0">Version {{ $adapter->version }}. CSS selectors and XPath expressions are supported.</p></div><a href="{{ route('source-question-import.index') }}" class="btn btn-outline-secondary">Back</a></div>
<form method="post" action="{{ route('source-question-adapters.update',$adapter) }}">@csrf @method('PATCH')
@include('source-question-import.partials.adapter-fields',['profile'=>$adapter,'selectors'=>$selectors])
<button class="btn btn-primary">Save new adapter version</button>
</form></div></div>
@endsection
