@extends('layouts.app')

@section('content')
    <div class="page-wrapper">
        <div class="row page-titles">
        <div class="col-md-6 align-self-center">
            <h3 class="text-themecolor">Foods</h3>
            </div>
        <div class="col-md-6 align-self-center text-right">
                <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Foods</li>
                </ol>
            </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <style>
                    .bulk-delete-link.disabled {
                        pointer-events: none;
                        opacity: 0.4;
                    }
                </style>
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

                @if (session('import_errors'))
                    <div class="alert alert-warning">
                        <strong>{{ count(session('import_errors')) }} row(s) could not be imported:</strong>
                        <ul class="mb-0">
                            @foreach (session('import_errors') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

{{--                <div class="d-md-flex justify-content-between align-items-center mb-4">--}}
{{--                    <form method="GET" action="{{ route('foods') }}" class="form-inline flex-grow-1 mr-md-3 mb-3 mb-md-0">--}}
{{--                        <div class="form-row w-100">--}}
{{--                            <div class="col-md-6 mb-2 mb-md-0">--}}
{{--                                <select name="category" class="form-control w-100">--}}
{{--                                    <option value="">All categories</option>--}}
{{--                                    @foreach ($categories as $category)--}}
{{--                                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>--}}
{{--                                            {{ $category->title }}--}}
{{--                                        </option>--}}
{{--                                    @endforeach--}}
{{--                                </select>--}}
{{--                            </div>--}}
{{--                            <div class="col-md-3 text-left text-md-right">--}}
{{--                                <button type="submit" class="btn btn-primary btn-block">--}}
{{--                                    <i class="fa fa-filter mr-1"></i> Filter--}}
{{--                                </button>--}}
{{--                        </div>--}}
        </div>
                        @if(request()->has('category'))
                            <div class="mt-2">
                                <a href="{{ route('foods') }}" class="small">Clear filter</a>
                            </div>
                        @endif
                    </form>
                    <div class="text-right">
                        <a href="{{ route('foods.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus mr-1"></i> Create Food
                        </a>
                        <a href="{{ route('foods.download-template') }}" class="btn btn-outline-info ml-2" style="display: none;">
                            <i class="fa fa-download mr-1"></i> Template
                        </a>
            </div>
        </div>

                <div class="table-responsive m-t-10">
                    <table class="display nowrap table table-hover table-striped table-bordered" id="foods-table" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th class="delete-all" style="width:55px;">
                                    <input type="checkbox" id="select-all">
                                    <label class="col-3 control-label mb-0" for="select-all">
                                        <a id="bulk-delete-link" class="do_not_delete bulk-delete-link disabled" href="javascript:void(0)">
                                            <i class="fa fa-trash"></i> All
                                        </a>
                                    </label>
                                </th>
                                <th>Image</th>
                                <th>Food</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Publish</th>
                                <th>Available</th>
                                <th>Updated</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($foods as $food)
                                <tr>
                                    <td class="delete-all">
                                        <input type="checkbox"
                                               class="item-checkbox"
                                               id="food-{{ $food->id }}"
                                               name="ids[]"
                                               value="{{ $food->id }}">
                                        <label class="col-3 control-label mb-0" for="food-{{ $food->id }}"></label>
                                    </td>
                                    <td width="80">
                                        <img src="{{ $food->photo ?: $placeholderImage }}"
                                             alt="{{ $food->name }}"
                                             class="rounded"
                                             style="width: 70px; height: 60px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <div class="font-weight-bold">{{ $food->name }}</div>
                                        <small class="text-muted d-block">{{ \Illuminate\Support\Str::limit($food->description, 60) }}</small>
                                    </td>
                                    <td>{{ $food->category->title ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-light editable-price"
                                              data-url="{{ route('foods.inlineUpdate', $food->id) }}"
                                              data-field="price"
                                              data-value="{{ $food->price ?? 0 }}">
                                            {{ number_format((float) ($food->price ?? 0), 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light editable-price"
                                              data-url="{{ route('foods.inlineUpdate', $food->id) }}"
                                              data-field="disPrice"
                                              data-value="{{ $food->disPrice ?? 0 }}">
                                            {{ number_format((float) ($food->disPrice ?? 0), 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        <label
                                            class="food-toggle"
                                            data-id="{{ $food->id }}"
                                            style="
            position: relative;
            display: inline-block;
            width: 45px;
            height: 22px;
            cursor: pointer;
        "
                                        >
        <span class="toggle-bg"
              style="
                position:absolute; top:0; left:0; right:0; bottom:0;
                background: {{ $food->publish ? 'green' : 'red' }};
                border-radius: 30px;
                transition: .3s;
            "
        >
            <span class="toggle-ball"
                  style="
                    position:absolute;
                    height:18px; width:18px;
                    left:2px; bottom:2px;
                    background:white;
                    border-radius:50%;
                    transition:.3s;
                    transform: translateX({{ $food->publish ? '23px' : '0px' }});
                "
            ></span>
        </span>
                                        </label>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('foods.availability', $food->id) }}" class="d-inline availability-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="isAvailable" value="{{ $food->isAvailable ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-sm {{ $food->isAvailable ? 'btn-primary' : 'btn-outline-secondary' }}">
                                                {{ $food->isAvailable ? 'Yes' : 'No' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>{{ $food->formattedUpdatedAt }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('foods.edit', $food->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('foods.destroy', $food->id) }}" class="d-inline" onsubmit="return confirm('Delete this food?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
{{--                                <tr>--}}
{{--                                    <td colspan="10" class="text-center text-muted py-4">--}}
{{--                                        No foods found. Use the button above to create one.--}}
{{--                                    </td>--}}
{{--                                </tr>--}}
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form id="bulk-delete-form" method="POST" action="{{ route('foods.bulkDestroy') }}" style="display: none;">
                    @csrf
                    @method('DELETE')
                    <div id="bulk-delete-inputs"></div>
                </form>

        </div>
    </div>
    </div>
    </div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('select-all');
    const bulkDeleteLink = document.getElementById('bulk-delete-link');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');

    function updateBulkControls() {
        const checkboxes = Array.from(document.querySelectorAll('.item-checkbox'));
        const checkedBoxes = checkboxes.filter(cb => cb.checked);

        if (bulkDeleteLink) {
            if (checkedBoxes.length === 0) {
                bulkDeleteLink.classList.add('disabled');
            } else {
                bulkDeleteLink.classList.remove('disabled');
            }
        }

        if (selectAllCheckbox) {
            if (!checkboxes.length) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                            return;
                        }

            const allChecked = checkedBoxes.length === checkboxes.length;
            const someChecked = checkedBoxes.length > 0 && !allChecked;

            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked;
        }
    }

    let foodsTableInstance = null;
    if (window.jQuery && $.fn.DataTable) {
        foodsTableInstance = $('#foods-table').DataTable({
            pageLength: 30,
            lengthMenu: [[10, 30, 50, 100, -1], [10, 30, 50, 100, 'All']],
            order: [],
            stateSave: true,
            columnDefs: [
                { targets: 0, orderable: false, searchable: false },
                { targets: -1, orderable: false, searchable: false }
            ]
        });

        foodsTableInstance.on('draw', function () {
            updateBulkControls();
        });
    }

    if (bulkDeleteLink && bulkDeleteForm) {
        bulkDeleteLink.addEventListener('click', function (event) {
            event.preventDefault();
            if (bulkDeleteLink.classList.contains('disabled')) {
                return;
            }
            if (confirm('Delete selected foods?')) {
                bulkDeleteForm.submit();
            }
        });
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checked = this.checked;
            document.querySelectorAll('.item-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            updateBulkControls();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('item-checkbox')) {
            updateBulkControls();
        }
    });

    updateBulkControls();

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function attachInlineEditor(element) {
        element.addEventListener('click', function () {
            if (element.dataset.editing === 'true') {
                return;
            }

            element.dataset.editing = 'true';
            const originalValue = parseFloat(element.dataset.value || 0).toFixed(2);
            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0';
            input.value = originalValue;
            input.className = 'form-control form-control-sm';
            input.style.width = '90px';

            element.textContent = '';
            element.classList.add('p-0');
            element.appendChild(input);
            input.focus();

            const resetState = (value) => {
                element.textContent = value;
                element.dataset.value = value;
                element.dataset.editing = 'false';
                element.classList.remove('p-0', 'text-info');
            };

            const submitValue = () => {
                const newValue = parseFloat(input.value || 0);
                if (isNaN(newValue) || newValue < 0) {
                    resetState(originalValue);
                    return;
                }

                element.classList.add('text-info');

                fetch(element.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        field: element.dataset.field,
                        value: newValue
                    })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          const formatted = parseFloat(data.data.value).toFixed(2);
                          resetState(formatted);
                          element.classList.add('text-success');
                          setTimeout(() => element.classList.remove('text-success'), 1200);
                                } else {
                          resetState(originalValue);
                          alert(data.message || 'Update failed.');
                      }
                  })
                  .catch(() => {
                      resetState(originalValue);
                      alert('Unable to update price right now.');
                  });
            };

            input.addEventListener('blur', submitValue);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitValue();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    resetState(originalValue);
                }
            });
        });
    }
    $(document).on("click", ".food-toggle", function () {
        let id = $(this).data('id');
        let toggle = $(this);
        let bg = toggle.find(".toggle-bg");
        let ball = toggle.find(".toggle-ball");

        $.ajax({
            url: '{{ route("foods.publish", ":id") }}'.replace(':id', id),
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {

                    // Update UI instantly
                    if (response.publish) {
                        bg.css("background", "green");
                        ball.css("transform", "translateX(23px)");
                    } else {
                        bg.css("background", "red");
                        ball.css("transform", "translateX(0px)");
                    }

                    console.log("Updated successfully");
                }
            },
            error: function(xhr) {
                console.error("Toggle update failed");
            }
        });
    });

    document.querySelectorAll('.editable-price').forEach(attachInlineEditor);
        });
    </script>
@endsection

