@extends('layouts.master')
@section('title', 'Transaction List')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Transaction List')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">All Transactions</h4>
            </div>
            <div class="card-body">
                @php
                    $sortLink = fn ($column) => request()->fullUrlWithQuery([
                        'sort' => $column,
                        'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        'page' => null,
                    ]);
                    $sortIcon = fn ($column) => $sort === $column
                        ? ($direction === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line')
                        : 'ri-arrow-up-down-line';
                @endphp
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <span class="text-muted">Showing {{ $transactions->count() }} of {{ $transactions->total() }}</span>
                    <form method="GET" action="{{ route('transactions.index') }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <select name="per_page" class="form-select" onchange="this.form.submit()" aria-label="Rows per page">
                            @foreach([50, 100, 500] as $size)
                                <option value="{{ $size }}" {{ $perPage == $size ? 'selected' : '' }}>{{ $size }} per page</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><a href="{{ $sortLink('id') }}" class="text-body">ID <i class="{{ $sortIcon('id') }}"></i></a></th>
                            <th><a href="{{ $sortLink('order') }}" class="text-body">Order ID <i class="{{ $sortIcon('order') }}"></i></a></th>
                            <th><a href="{{ $sortLink('transaction') }}" class="text-body">Transaction ID <i class="{{ $sortIcon('transaction') }}"></i></a></th>
                            <th><a href="{{ $sortLink('method') }}" class="text-body">Payment Method <i class="{{ $sortIcon('method') }}"></i></a></th>
                            <th><a href="{{ $sortLink('amount') }}" class="text-body">Amount ({{ $configuration_detail->currency }}) <i class="{{ $sortIcon('amount') }}"></i></a></th>
                            <th><a href="{{ $sortLink('status') }}" class="text-body">Status <i class="{{ $sortIcon('status') }}"></i></a></th>
                            <th><a href="{{ $sortLink('date') }}" class="text-body">Date <i class="{{ $sortIcon('date') }}"></i></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->id }}</td>
                            <td>{{ $transaction->order_id }}</td>
                            <td>{{ $transaction->transaction_id }}</td>
                            <td>{{ ucfirst($transaction->payment_gateway) }}</td>
                            <td>{{ $configuration_detail->currency }}{{ number_format($transaction->amount, 2) }}</td>
                            <td>{{ ucfirst($transaction->status) }}</td>
                            <td>{{ $transaction->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No transactions found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                </div>

                <div class="d-flex justify-content-end">
                    {{ $transactions->links('vendor.pagination.custom') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
