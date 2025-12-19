@extends('layouts.app')

@section('content')
<div class="withdrawal-method page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.add_withdrawal_method') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('withdraw-method') }}">{{ trans('lang.withdrawal_method') }}</a></li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <p class="text-muted">{{ trans('lang.withdrawal_method_description') }}</p>
                <h4 class="mb-3">{{ trans('lang.available_method') }}</h4>

                <div class="list-data">
                    @forelse(($methods ?? []) as $methodEntry)
                        <div class="method d-flex border-bottom pb-3 mb-3 align-items-center">
                            <div class="image d-flex align-items-center">
                                <img src="{{ asset('images/' . $methodEntry['key'] . '.png') }}" style="width: 90px;">
                                <div class="ml-3">
                                    <h5 class="mb-1">{{ $methodEntry['label'] }}</h5>
                                    @if($methodEntry['configured'])
                                        <span class="badge badge-success">{{ trans('lang.setup_done') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-auto">
                                <button type="button"
                                        class="btn btn-danger setup_btn"
                                        data-method="{{ $methodEntry['key'] }}"
                                        data-label="{{ $methodEntry['label'] }}"
                                        data-fields='@json($methodEntry['fields'])'
                                        data-values='@json($methodEntry['values'])'>
                                    {{ $methodEntry['configured'] ? trans('lang.edit') : trans('lang.setup') }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-center font-weight-bold mb-0">{{ trans('lang.no_record_found') }}</p>
                    @endforelse
                </div>

                <div class="form-group text-center mt-4">
                    <a href="{{ route('withdraw-method') }}" class="btn btn-default">
                        <i class="fa fa-undo"></i> {{ trans('lang.cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<div class="modal fade" id="addMethodModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered location_modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title locationModalTitle">
                    <span id="method_title"></span> - {{ trans('lang.add_withdrawal_method') }}
                </h5>
                <button type="button" class="close close-model" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="error_top alert alert-danger" style="display:none"></div>
                <form id="withdraw-method-form" method="POST" action="{{ route('withdraw-method.store') }}">
                    @csrf
                    <input type="hidden" name="method" id="withdraw_method_key">
                    <div class="form-row">
                        <div id="append_fields" class="w-100"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="add-method-btn">{{ trans('lang.save') }}</button>
                <button type="button" class="btn btn-primary close-model" data-dismiss="modal" aria-label="Close">{{ trans('lang.close') }}</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = $('#addMethodModal');
        const form = document.getElementById('withdraw-method-form');
        const fieldContainer = document.getElementById('append_fields');
        const methodInput = document.getElementById('withdraw_method_key');
        const errorTop = document.querySelector('.error_top');

        document.querySelectorAll('.setup_btn').forEach(button => {
            button.addEventListener('click', function () {
                const method = this.dataset.method;
                const label = this.dataset.label;
                const fields = JSON.parse(this.dataset.fields || '[]');
                const values = JSON.parse(this.dataset.values || '{}');

                methodInput.value = method;
                document.getElementById('method_title').textContent = label;
                fieldContainer.innerHTML = '';
                errorTop.style.display = 'none';
                errorTop.textContent = '';

                fields.forEach(field => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'form-group row width-100';

                    const labelEl = document.createElement('label');
                    labelEl.className = 'col-5 control-label';
                    labelEl.textContent = field.label;

                    const inputWrapper = document.createElement('div');
                    inputWrapper.className = 'col-12';

                    let inputEl;
                    if (field.type === 'textarea') {
                        inputEl = document.createElement('textarea');
                        inputEl.rows = 4;
                    } else {
                        inputEl = document.createElement('input');
                        inputEl.type = field.type || 'text';
                    }

                    inputEl.className = 'form-control';
                    inputEl.name = field.name;
                    inputEl.value = values[field.name] ?? '';
                    if (field.placeholder) {
                        inputEl.placeholder = field.placeholder;
                    }

                    inputWrapper.appendChild(inputEl);

                    if (field.help) {
                        const help = document.createElement('div');
                        help.className = 'form-text text-muted';
                        help.textContent = field.help;
                        inputWrapper.appendChild(help);
                    }

                    wrapper.appendChild(labelEl);
                    wrapper.appendChild(inputWrapper);
                    fieldContainer.appendChild(wrapper);
                });

                modal.modal('show');
            });
        });

        document.getElementById('add-method-btn').addEventListener('click', () => {
            form.submit();
        });

        document.querySelectorAll('.close-model').forEach(btn => {
            btn.addEventListener('click', () => {
                errorTop.style.display = 'none';
                errorTop.textContent = '';
            });
        });
    });
</script>
@endsection
