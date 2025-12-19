@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.restaurants_payout_plural') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.restaurants_payout_plural') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
                            <li class="nav-item active">
                                <a class="nav-link active" href="{{ url()->current() }}">
                                    <i class="fa fa-list mr-2"></i>{{ trans('lang.vendors_payout_table') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('payments.create') }}">
                                    <i class="fa fa-plus mr-2"></i>{{ trans('lang.vendors_payout_create') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive m-t-10">
                            <table id="payoutTable" class="display nowrap table table-hover table-striped table-bordered" width="100%">
                                <thead>
                                    <tr>
                                        <th>{{ trans('lang.paid_amount') }}</th>
                                        <th>{{ trans('lang.date') }}</th>
                                        <th>{{ trans('lang.restaurants_payout_note') }}</th>
                                        <th>{{ trans('lang.status') }}</th>
                                        <th>{{ trans('lang.withdraw_method') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusFilter = @json($statusQuery ?? '');

    $('#payoutTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        pageLength: 10,
        ajax: {
            url: '{{ route('payments.data') }}',
            data: function (d) {
                d.status = statusFilter;
            }
        },
        columns: [
            { data: 'amount' },
            { data: 'date' },
            { data: 'note' },
            { data: 'status', orderable: false, searchable: false },
            { data: 'method', orderable: false },
        ],
        order: [[1, 'desc']],
        language: {
            zeroRecords: "{{ trans('lang.no_record_found') }}",
            emptyTable: "{{ trans('lang.no_record_found') }}",
        }
    });
});
</script>
@endsection
