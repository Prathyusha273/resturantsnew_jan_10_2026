@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.vendors_payout_plural') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('payments') }}">{{ trans('lang.vendors_payout_table') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.vendors_payout_create') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-4">
                    <h4 class="font-weight-bold mb-1">{{ trans('lang.wallet_balance') }}</h4>
                    <p class="h3">
                        {{ $currency['symbol_at_right'] ? number_format($walletBalance, $currency['decimal_digits']) . $currency['symbol'] : $currency['symbol'] . number_format($walletBalance, $currency['decimal_digits']) }}
                    </p>
                </div>

                <form method="POST" action="{{ route('payments.store') }}">
                    @csrf
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ trans('lang.vendors_payout_amount') }}</label>
                        <div class="col-md-7">
                            <input type="number"
                                   name="amount"
                                   min="1"
                                   step="0.01"
                                   value="{{ old('amount') }}"
                                   class="form-control"
                                   required>
                            <small class="form-text text-muted">{{ trans('lang.vendors_payout_amount_placeholder') }}</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ trans('lang.withdrawal_method') }}</label>
                        <div class="col-md-7">
                            <select name="withdraw_method" class="form-control" required>
                                <option value="">{{ trans('lang.select_withdrawal_method') }}</option>
                                @foreach($withdrawMethods as $method)
                                    <option value="{{ $method['value'] }}" {{ old('withdraw_method') === $method['value'] ? 'selected' : '' }}>
                                        {{ $method['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ trans('lang.vendors_payout_note') }}</label>
                        <div class="col-md-7">
                            <textarea name="note" rows="4" class="form-control">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    <div class="form-group text-center mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> {{ trans('lang.save') }}
                        </button>
                        <a href="{{ route('payments') }}" class="btn btn-default">
                            <i class="fa fa-undo"></i> {{ trans('lang.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
