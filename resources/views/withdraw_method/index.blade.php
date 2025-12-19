@extends('layouts.app')

@section('content')
<div class="withdraw-method-list page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.withdrawal_method') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ trans('lang.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.withdrawal_method') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <div class="title-head d-flex align-items-center mb-4 border-bottom pb-3">
                            <h4 class="mb-0">{{ trans('lang.withdrawal_method') }}</h4>
                            <a href="{{ $addRoute }}" class="ml-auto btn btn-primary">{{ trans('lang.add_withdrawal_method') }}</a>
                        </div>

                        <div class="list-data">
                            @forelse($configuredMethods as $method)
                                <div class="method d-flex border-bottom pb-3 mb-3 align-items-center">
                                    <div class="image d-flex align-items-center">
                                        <img src="{{ asset('images/' . $method['key'] . '.png') }}" style="width: 90px;">
                                        <div class="ml-3">
                                            <h5 class="mb-1">{{ $method['label'] }}</h5>
                                        </div>
                                    </div>
                                    <div class="ml-auto">
                                        <a href="{{ $method['route'] }}" class="btn btn-outline-primary">{{ trans('lang.edit') }}</a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center font-weight-bold mb-0">{{ trans('lang.no_record_found') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
