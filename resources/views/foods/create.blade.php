@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-6 align-self-center">
            <h3 class="text-themecolor">Create Food</h3>
        </div>
        <div class="col-md-6 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('foods') }}">Foods</a></li>
                <li class="breadcrumb-item active">Create</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="btn btn-primary mb-0">Food Details</h4>
                <a href="{{ route('foods') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left mr-1"></i> Back to list
                </a>
            </div>
        <div class="card-body">
                <form method="POST" action="{{ route('foods.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('foods.partials.form', [
                        'food' => null,
                        'categories' => $categories,
                        'extraPhotos' => [],
                        'addOns' => [],
                        'specifications' => [],
                        'placeholderImage' => $placeholderImage
                    ])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @include('foods.partials.form_scripts')
@endsection

