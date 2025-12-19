@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.coupon_plural') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.coupon_table') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
                    <li class="nav-item active">
                        <a class="nav-link" href="{{ route('coupons') }}"><i class="fa fa-list mr-2"></i>{{ trans('lang.coupon_table') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('coupons.create') }}"><i class="fa fa-plus mr-2"></i>{{ trans('lang.coupon_create') }}</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <div class="table-responsive m-t-10">
                    <table id="coupons-table" class="display nowrap table table-hover table-striped table-bordered" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th class="delete-all" style="width:55px;">
                                    <input type="checkbox" id="select-all">
                                    <label class="col-3 control-label mb-0" for="select-all">
                                        <a id="bulk-delete-link" class="do_not_delete bulk-delete-link disabled" href="javascript:void(0)">
                                            <i class="fa fa-trash"></i> {{ trans('lang.all') }}
                                        </a>
                                    </label>
                                </th>
                                <th>{{ trans('lang.coupon_code') }}</th>
                                <th>{{ trans('lang.coupon_discount') }}</th>
                                <th>{{ trans('lang.coupon_description') }}</th>
                                <th>{{ trans('lang.coupon_expires_at') }}</th>
                                <th>{{ trans('lang.coupon_enabled') }}</th>
                                <th>{{ trans('lang.coupon_privacy') }}</th>
                                <th>{{ trans('lang.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <form id="bulk-delete-form" method="POST" action="{{ route('coupons.bulkDestroy') }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .bulk-delete-link.disabled {
        pointer-events: none;
        opacity: 0.4;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('select-all');
    const bulkDeleteLink = document.getElementById('bulk-delete-link');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');

    function updateBulkControls() {
        const checkboxes = Array.from(document.querySelectorAll('#coupons-table .is_open'));
        const checked = checkboxes.filter(cb => cb.checked);

        if (bulkDeleteLink) {
            bulkDeleteLink.classList.toggle('disabled', checked.length === 0);
        }

        if (selectAllCheckbox) {
            if (!checkboxes.length) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                return;
            }

            selectAllCheckbox.checked = checked.length === checkboxes.length;
            selectAllCheckbox.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    }

    const table = $('#coupons-table').DataTable({
        pageLength: 10,
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: '{{ route('coupons.data') }}',
        columns: [
            { data: 0, orderable: false, searchable: false },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5, orderable: false, searchable: false },
            { data: 6, orderable: false, searchable: false },
            { data: 7, orderable: false, searchable: false },
        ],
        order: [[4, 'desc']],
        language: {
            zeroRecords: '{{ trans('lang.no_record_found') }}',
            emptyTable: '{{ trans('lang.no_record_found') }}'
        }
    });

    table.on('draw', function () {
        updateBulkControls();
    });

    if (bulkDeleteLink && bulkDeleteForm) {
        bulkDeleteLink.addEventListener('click', function (event) {
            event.preventDefault();
            if (bulkDeleteLink.classList.contains('disabled')) {
                return;
            }

            const selectedCheckboxes = Array.from(document.querySelectorAll('#coupons-table .is_open:checked'));
            bulkDeleteForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

            selectedCheckboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                bulkDeleteForm.appendChild(input);
            });

            if (selectedCheckboxes.length && confirm('Delete selected coupons?')) {
                bulkDeleteForm.submit();
            }
        });
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checked = this.checked;
            document.querySelectorAll('#coupons-table .is_open').forEach(cb => {
                cb.checked = checked;
            });
            updateBulkControls();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('is_open')) {
            updateBulkControls();
        }
    });

    updateBulkControls();
});
</script>
@endsection

