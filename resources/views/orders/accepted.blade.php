@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.accepted_orders') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ url('orders') }}">{{ trans('lang.order_plural') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.accepted_orders') }}</li>
            </ol>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive m-t-10">
                            <table id="orderTable" class="display nowrap table table-hover table-striped table-bordered" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th class="delete-all">
                                            <input type="checkbox" id="is_active">
                                            <label class="col-3 control-label" for="is_active">
                                                <a id="deleteAll" class="do_not_delete" href="javascript:void(0)">
                                                    <i class="fa fa-trash"></i> {{ trans('lang.all') }}
                                                </a>
                                            </label>
                                        </th>
                                        <th>{{ trans('lang.order_id') }}</th>
                                        <th>{{ trans('lang.order_user_id') }}</th>
                                        <th>{{ trans('lang.driver_plural') }}</th>
                                        <th>{{ trans('lang.order_order_status_id') }}</th>
                                        <th>{{ trans('lang.amount') }}</th>
                                        <th>{{ trans('lang.order_type') }}</th>
                                        <th>{{ trans('lang.date') }}</th>
                                        <th>{{ trans('lang.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <form id="order-delete-form" method="POST" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                            <form id="order-bulk-delete-form" method="POST" action="{{ route('orders.bulkDestroy') }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
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
    const bulkDeleteForm = document.getElementById('order-bulk-delete-form');
    const deleteForm = document.getElementById('order-delete-form');
    const selectAll = document.getElementById('is_active');
    const deleteAllBtn = document.getElementById('deleteAll');

    const table = $('#orderTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        pageLength: 10,
        ajax: {
            url: '{{ route('orders.data') }}',
            data: function (d) {
                d.status = statusFilter;
            }
        },
        columns: [
            { data: 'select', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'customer' },
            { data: 'driver' },
            { data: 'status', orderable: false, searchable: false },
            { data: 'amount' },
            { data: 'type', orderable: false },
            { data: 'date' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        order: [[7, 'desc']],
        language: {
            zeroRecords: "{{ trans('lang.no_record_found') }}",
            emptyTable: "{{ trans('lang.no_record_found') }}",
        }
    });

    function updateBulkControls() {
        const checkboxes = Array.from(document.querySelectorAll('#orderTable .is_open'));
        const checked = checkboxes.filter(cb => cb.checked);

        if (selectAll) {
            if (!checkboxes.length) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = checked.length === checkboxes.length && checked.length > 0;
                selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
            }
        }

        if (deleteAllBtn) {
            deleteAllBtn.classList.toggle('disabled', checked.length === 0);
        }
    }

    table.on('draw', function () {
        updateBulkControls();
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('#orderTable .is_open').forEach(cb => {
                cb.checked = selectAll.checked;
            });
            updateBulkControls();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('is_open')) {
            updateBulkControls();
        }
    });

    if (deleteAllBtn && bulkDeleteForm) {
        deleteAllBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (deleteAllBtn.classList.contains('disabled')) {
                return;
            }
            const selected = Array.from(document.querySelectorAll('#orderTable .is_open:checked'));
            if (!selected.length || !confirm('Delete selected orders?')) {
                return;
            }
            bulkDeleteForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
            selected.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                bulkDeleteForm.appendChild(input);
            });
            bulkDeleteForm.submit();
        });
    }

    document.addEventListener('click', function (event) {
        const deleteBtn = event.target.closest('.order-delete-btn');
        if (!deleteBtn || !deleteForm) {
            return;
        }
        event.preventDefault();
        if (!confirm('Delete this order?')) {
            return;
        }
        deleteForm.action = deleteBtn.dataset.route;
        deleteForm.submit();
    });
});
</script>
@endsection

