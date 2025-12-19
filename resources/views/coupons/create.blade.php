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
                <li class="breadcrumb-item"><a href="{{ route('coupons') }}">{{ trans('lang.coupon_plural') }}</a></li>
                <li class="breadcrumb-item active">{{ trans('lang.coupon_create') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('coupons.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('coupons.partials.form', ['coupon' => $coupon ?? null])
                    <div class="form-group col-12 text-center btm-btn">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> {{ trans('lang.save') }}
                        </button>
                        <a href="{{ route('coupons') }}" class="btn btn-default">
                            <i class="fa fa-undo"></i> {{ trans('lang.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
