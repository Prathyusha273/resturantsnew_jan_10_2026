@extends('layouts.app')
@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{trans('lang.wallet_transaction_plural')}} <span class="userTitle"></span>
            </h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{url('/')}}">{{trans('lang.dashboard')}}</a></li>
                <li class="breadcrumb-item active">{{trans('lang.wallet_transaction_plural')}}</li>
            </ol>
        </div>
        <div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
                            <li class="nav-item">
                                <a class="nav-link active" href="{!! url()->current() !!}"><i
                                        class="fa fa-list mr-2"></i>{{trans('lang.wallet_transaction_table')}}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                    <div class="title-head d-flex align-items-center mb-4 border-bottom pb-4 justify-content-between">
                        <h3 class="mb-0">{{trans('lang.wallet_transaction_table')}}</h3>
                        <div class="select-box pl-3">
                            <div id="daterange"><i class="fa fa-calendar"></i>&nbsp;
                                <span></span>&nbsp; <i class="fa fa-caret-down"></i>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive m-t-10">
                        <table id="example24"
                            class="display nowrap table table-hover table-striped table-bordered table table-striped"
                            cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>{{trans('lang.amount')}}</th>
                                    <th>{{trans('lang.date')}}</th>
                                    <th>{{trans('lang.payment_methods')}}</th>
                                     <th>{{trans('lang.vendors_payout_note')}}</th>
                                    <th>{{trans('lang.payment_status')}}</th>
                                </tr>
                            </thead>
                            <tbody id="append_list1">
                            </tbody>
                        </table>
                    </div>
                </div>
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
    $(function () {
        let activeRange = null;

        $('#daterange span').html('{{ trans("lang.select_date_range") }}');
        $('#daterange').daterangepicker({
            autoUpdateInput: false,
        });

        $('#daterange').on('apply.daterangepicker', function (ev, picker) {
            activeRange = {
                start: picker.startDate.format('YYYY-MM-DD HH:mm:ss'),
                end: picker.endDate.format('YYYY-MM-DD HH:mm:ss')
            };
            $('#daterange span').html(picker.startDate.format('MMMM D, YYYY') + ' - ' + picker.endDate.format('MMMM D, YYYY'));
            $('#example24').DataTable().ajax.reload();
        });

        $('#daterange').on('cancel.daterangepicker', function () {
            activeRange = null;
            $('#daterange span').html('{{ trans("lang.select_date_range") }}');
            $('#example24').DataTable().ajax.reload();
        });

        var fieldConfig = {
            columns: [
                { key: 'transactionamount', header: '{{trans("lang.amount")}}' },
                { key: 'payment_method', header: '{{trans("lang.payment_methods")}}' },
                { key: 'payment_status', header: '{{trans("lang.payment_status")}}' },
                { key: 'note', header: '{{trans("lang.vendors_payout_note")}}' },
                { key: 'date', header: '{{trans("lang.date")}}' },
            ],
            fileName: '{{trans("lang.wallet_transaction_table")}}',
        };

        const table = $('#example24').DataTable({
            pageLength: 10,
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '{{ route("wallettransaction.data") }}',
                data: function (d) {
                    if (activeRange) {
                        d.start_date = activeRange.start;
                        d.end_date = activeRange.end;
                    }
                }
            },
            columns: [
                { data: 0, orderable: true },
                { data: 1, orderable: true },
                { data: 2, orderable: false },
                { data: 3, orderable: false },
                { data: 4, orderable: false },
            ],
            order: [[1, 'desc']],
            language: {
                zeroRecords: "{{ trans('lang.no_record_found') }}",
                emptyTable: "{{ trans('lang.no_record_found') }}"
            },
            dom: 'lfrtipB',
            buttons: [
                {
                    extend: 'collection',
                    text: '<i class="mdi mdi-cloud-download"></i> Export as',
                    className: 'btn btn-info',
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: 'Export Excel',
                            exportOptions: {
                                columns: ':visible' // Export all visible columns
                            },
                            title: 'transactions',
                        },
                        {
                            extend: 'pdfHtml5',
                            text: 'Export PDF',
                            exportOptions: {
                                columns: ':visible' // Export all visible columns
                            },
                            title: 'transactions',
                        },
                        {
                            extend: 'csvHtml5',
                            text: 'Export CSV',
                            exportOptions: {
                                columns: ':visible' // Export all visible columns
                            },
                            title: 'transactions ',
                        }
                    ]
                }
            ],
            initComplete: function () {
                $(".dataTables_filter").append($(".dt-buttons").detach());
                $('.dataTables_filter input').attr('placeholder', 'Search here...').attr('autocomplete', 'new-password').val('');
                $('.dataTables_filter label').contents().filter(function () {
                    return this.nodeType === 3;
                }).remove();
            }
        });
    });
</script>
@endsection
