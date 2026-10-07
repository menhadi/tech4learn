@extends('layouts.master')
@section('title', 'Sales Report')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Sales Report')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Sales Reports</h4>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Order ID</th>
                            <th>Course Name</th>
                            <th>Quantity</th>
                            <th>Amount ({{ $configuration_detail->currency }})</th>
                            <th>Sale Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesReports as $sale)
                        <tr>
                            <td>{{ $sale->id }}</td>
                            <td>{{ $sale->order_id }}</td>
                            <td>{{ $sale->package->name ?? 'N/A' }}</td>
                            <td>{{ $sale->quantity }}</td>
                            <td>{{ $configuration_detail->currency }}{{ number_format($sale->total, 2) }}</td>
                            <td>{{ $sale->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No sales found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="d-flex justify-content-end">
                    {{ $salesReports->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
