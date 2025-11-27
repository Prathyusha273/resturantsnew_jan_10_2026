@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{ trans('lang.order_plural') }}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">{{ trans('lang.dashboard') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('orders') }}">{{ trans('lang.order_plural') }}</a>
                    </li>
                    <li class="breadcrumb-item active">{{ trans('lang.order_edit') }}</li>
            </ol>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card-body pb-5 p-0">
            <div class="text-right print-btn pb-3">
                    <a href="{{ route('vendors.orderprint', $order->id) }}">
                        <button type="button"
                                class="btn btn-primary"
                                style="background:#ff8c00; border:none; color:#fff;">
                            <i class="fa fa-print" style="color:#fff;"></i>
                        </button>
                </a>
            </div>

            <div class="order_detail" id="order_detail">
                <div class="order_detail-top">
                    <div class="row">
                        <div class="order_edit-genrl col-lg-7 col-md-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h3>{{ trans('lang.general_details') }}</h3>
                                </div>
                                <div class="card-body">
                                    <div class="order_detail-top-box">
                                        <div class="form-group row widt-100 gendetail-col">
                                                <label class="col-12 control-label">
                                                    <strong>{{ trans('lang.date_created') }}: </strong>
                                                    <span>{{ $details['created_at'] }}</span>
                                                </label>
                                        </div>

                                        <div class="form-group row widt-100 gendetail-col payment_method">
                                                <label class="col-12 control-label">
                                                    <strong>{{ trans('lang.payment_methods') }}: </strong>
                                                    <span>{!! $details['payment_method'] !!}</span>
                                                </label>
                                        </div>

                                        <div class="form-group row widt-100 gendetail-col">
                                                <label class="col-12 control-label">
                                                    <strong>{{ trans('lang.order_type') }}: </strong>
                                                    <span>{{ $details['order_type'] }}</span>
                                                </label>
                                        </div>

{{--                                            @if($details['schedule_time'])--}}
                                        <div class="form-group row widt-100 gendetail-col schedule_date">
                                                    <label class="col-12 control-label">
                                                        <strong>{{ trans('lang.schedule_date_time') }}: </strong>
                                                        <span>{{ $details['created_at'] }}</span>
                                                    </label>
                                        </div>
{{--                                            @endif--}}

                                            @if($details['estimated_time'])
                                        <div class="form-group row widt-100 gendetail-col prepare_time">
                                                    <label class="col-12 control-label">
                                                        <strong>{{ trans('lang.prepare_time') }}: </strong>
                                                        <span>{{ $details['estimated_time'] }}</span>
                                                    </label>
                                        </div>
                                            @endif

                                            <form method="POST" action="{{ route('orders.update', $order->id) }}" id="order-status-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="estimated_time" id="estimated_time_input" value="{{ old('estimated_time', $details['estimated_time']) }}">
                                                <div class="form-group row width-100">
                                                    <label class="col-3 control-label">{{ trans('lang.status') }}
                                                        :</label>
                                            <div class="col-7">
                                                        <select id="order_status" name="status" class="form-control">
                                                            @foreach($statusOptions as $status)
                                                                <option
                                                                    value="{{ $status }}" {{ old('status', $order->status) === $status ? 'selected' : '' }}>
                                                                    {{ $status }}
                                                    </option>
                                                            @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row width-100">
                                            <label class="col-3 control-label"></label>
                                            <div class="col-7 text-right">
                                                        <button type="submit" class="btn btn-primary edit-form-btn">
                                                            <i class="fa fa-save"></i> {{ trans('lang.update') }}
                                                </button>
                                            </div>
                                        </div>
                                            </form>
                                    </div>
                                </div>
                            </div>

                            <div class="order-items-list mt-4">
                                <div class="card">
                                        <div class="card-header bg-white">
                                            <h3>{{ trans('lang.order_items') }}</h3>
                                        </div>
                                    <div class="card-body">
                                        <table cellpadding="0" cellspacing="0"
                                            class="table table-striped table-valign-middle">
                                            <thead>
                                                <tr>
                                                    <th>{{ trans('lang.item') }}</th>
                                                    <th class="text-center">{{ trans('lang.price') }}</th>
                                                    <th>{{ trans('lang.qty') }}</th>
                                                    <th>{{ trans('lang.extras') }}</th>
                                                    <th>{{ trans('lang.total') }}</th>
                                                </tr>
                                            </thead>
                                                <tbody>
                                                @foreach($details['products'] as $product)
                                                    <tr>
                                                        <td class="order-product">
                                                            <div class="order-product-box">
                                                                <img src="{{ $product['photo'] }}"
                                                                     onerror="this.onerror=null;this.src='{{ $details['placeholder'] }}'"
                                                                     class="img-circle img-size-32 mr-2"
                                                                     style="width:60px;height:60px;"
                                                                     alt="image">
                                                                <div class="orders-tracking">
                                                                    <h6>{{ $product['name'] }}</h6>
                                                                    @if(!empty($product['variant']))
                                                                        <div class="variant-info">
                                                                            <ul>
                                                                                @foreach($product['variant'] as $label => $value)
                                                                                    <li class="variant">
                                                                                        <span class="label">{{ $label }}:</span>
                                                                                        <span
                                                                                            class="value">{{ $value }}</span>
                                                                                    </li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </div>
                                                                    @endif
                                                                    @if(!empty($product['extras']))
                                                                        <div class="extra">
                                                                            <span>{{ trans('lang.extras') }}: </span>
                                                                            <span
                                                                                class="ext-item">{{ implode(', ', $product['extras']) }}</span>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-green text-center">
                                                            <span class="item-price">{{ $product['price'] }}</span>
                                                            <br>
                                                            <small class="text-muted">(Base
                                                                Price: {{ $product['price'] }})</small>
                                                        </td>
                                                        <td> × {{ $product['quantity'] }}</td>
                                                        <td class="text-green"> + {{ $product['extras_price'] }}</td>
                                                        <td class="text-green">{{ $product['total'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                        <div class="order-data-row order-totals-items">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="table-responsive bk-summary-table">
                                                        <table class="order-totals">
                                                                <tbody>
                                                                <tr>
                                                                    <td class="seprater" colspan="2">
                                                                        <hr>
                                                                        <span>{{ trans('lang.sub_total') }}</span>
                                                                    </td>
                                                                </tr>
                                                                <tr class="final-rate">
                                                                    <td class="label">Subtotal</td>
                                                                    <td class="sub_total" style="color:green">
                                                                        ({{ $details['summary']['subtotal'] }})
                                                                    </td>
                                                                </tr>
                                                                @if($details['summary']['discount'] !== '₹0.00' && $details['summary']['discount'] !== '$0.00')
                                                                    <tr>
                                                                        <td class="seprater" colspan="2">
                                                                            <hr>
                                                                            <span>{{ trans('lang.discount') }}</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="label">{{ trans('lang.discount') }}</td>
                                                                        <td class="discount text-danger">
                                                                            (-{{ $details['summary']['discount'] }})
                                                                        </td>
                                                                    </tr>
                                                                @endif
                                                                @if($details['summary']['special_discount'] !== '₹0.00' && $details['summary']['special_discount'] !== '$0.00')
                                                                    <tr>
                                                                        <td class="label">{{ trans('lang.special_offer') }} {{ trans('lang.discount') }}</td>
                                                                        <td class="special_discount text-danger">
                                                                            (-{{ $details['summary']['special_discount'] }}
                                                                            )
                                                                        </td>
                                                                    </tr>
                                                                @endif
                                                                @if(!empty($details['summary']['taxes']))
                                                                    <tr>
                                                                        <td class="seprater" colspan="2">
                                                                            <hr>
                                                                            <span>Tax Calculation</span>
                                                                        </td>
                                                                    </tr>
                                                                    @foreach($details['summary']['taxes'] as $tax)
                                                                        <tr>
                                                                            <td class="label">{{ $tax['label'] }}</td>
                                                                            <td class="tax_amount" id="greenColor">
                                                                                +{{ $tax['amount'] }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                @endif
                                                                @if($details['summary']['delivery'] !== '₹0.00' && $details['summary']['delivery'] !== '$0.00')
                                                                    <tr>
                                                                        <td class="seprater" colspan="2">
                                                                            <hr>
                                                                            <span>{{ trans('lang.delivery_charge') }}</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="label">{{ trans('lang.deliveryCharge') }}</td>
                                                                        <td class="deliveryCharge" id="greenColor">
                                                                            +{{ $details['summary']['delivery'] }}</td>
                                                                    </tr>
                                                                @endif
                                                                @if($details['summary']['tip'] !== '₹0.00' && $details['summary']['tip'] !== '$0.00')
                                                                    <tr>
                                                                        <td class="seprater" colspan="2">
                                                                            <hr>
                                                                            <span>{{ trans('lang.tip') }}</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="label">{{ trans('lang.tip_amount') }}</td>
                                                                        <td class="tip_amount_val" id="greenColor">
                                                                            +{{ $details['summary']['tip'] }}</td>
                                                                    </tr>
                                                                @endif
                                                                <tr>
                                                                    <td class="seprater" colspan="2">
                                                                        <hr>
                                                                    </td>
                                                                </tr>
                                                                <tr class="grand-total">
                                                                    <td class="label">{{ trans('lang.total_amount') }}</td>
                                                                    <td class="total_price_val"
                                                                        id="greenColor">{{ $details['summary']['grand_total'] }}</td>
                                                                </tr>
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

                        <div class="order_addre-edit col-lg-5 col-md-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h3>{{ trans('lang.billing_details') }}</h3>
                                </div>
                                <div class="card-body">
                                    <div class="address order_detail-top-box">
                                        <p>
                                                <strong>{{ trans('lang.name') }}: </strong>
                                                <span>{{ $details['customer']['name'] }}</span>
                                        </p>
                                        <p>
                                                <strong>{{ trans('lang.address') }}: </strong>
                                                @if(!empty($details['address']))
                                                    @if(!empty($details['address']['address']))
                                                        <span>{{ $details['address']['address'] }}</span><br>
                                                    @endif
                                                    @if(!empty($details['address']['locality']))
                                                        <span>{{ $details['address']['locality'] }}</span>
                                                    @endif
                                                    @if(!empty($details['address']['landmark']))
                                                        <span>{{ $details['address']['landmark'] }}</span>
                                                    @endif
                                                    @if(!empty($details['address']['city']))
                                                        <span>{{ $details['address']['city'] }}</span>
                                                    @endif
                                                    @if(!empty($details['address']['state']))
                                                        <span>{{ $details['address']['state'] }}</span>
                                                    @endif
                                                    @if(!empty($details['address']['country']))
                                                        <span>{{ $details['address']['country'] }}</span>
                                                    @endif
                                                    @if(!empty($details['address']['zipCode']))
                                                        <span>{{ $details['address']['zipCode'] }}</span>
                                                    @endif
                                                @endif
                                            </p>
                                            <p>
                                                <strong>{{ trans('lang.email_address') }}: </strong>
                                                <span style="color: red;">
                                                @if(!empty($details['customer']['email']))
                                                        <a href="mailto:{{ $details['customer']['email'] }}">{{ $details['customer']['email'] }}</a>
                                                    @else
                                                        N/A
                                                    @endif
                                            </span>
                                            </p>
                                            <p>
                                                <strong>{{ trans('lang.phone') }}: </strong>
                                                <span>{{ $details['customer']['phone'] ?? 'N/A' }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                                @if(!empty($details['driver']))
                                    <div class="order_addre-edit driver_details_hide" style="display: none;">
                                <div class="card mt-4">
                                    <div class="card-header bg-white">
                                        <h3>{{ trans('lang.driver_detail') }}</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="address order_detail-top-box">
                                            <p>
                                                        <strong>{{ trans('lang.name') }}: </strong>
                                                        <span>{{ $details['driver']['firstName'] ?? '' }} {{ $details['driver']['lastName'] ?? '' }}</span>
                                                    </p>
                                                    <p>
                                                        <strong>{{ trans('lang.email_address') }}: </strong>
                                                        <span>
                                                    @if(!empty($details['driver']['email']))
                                                                <a href="mailto:{{ $details['driver']['email'] }}">{{ $details['driver']['email'] }}</a>
                                                            @else
                                                                N/A
                                                            @endif
                                                </span>
                                                    </p>
                                                    <p>
                                                        <strong>{{ trans('lang.phone') }}: </strong>
                                                        <span>{{ $details['driver']['phoneNumber'] ?? 'N/A' }}</span>
                                                    </p>
                                                    @if(!empty($details['driver']['carName']))
                                                        <p>
                                                            <strong>{{ trans('lang.car_name') }}: </strong>
                                                            <span>{{ $details['driver']['carName'] }}</span>
                                                        </p>
                                                    @endif
                                                    @if(!empty($details['driver']['carNumber']))
                                                        <p>
                                                            <strong>{{ trans('lang.car_number') }}: </strong>
                                                            <span>{{ $details['driver']['carNumber'] }}</span>
                                                        </p>
                                                    @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                                @endif

                            <div class="resturant-detail mt-4">
                                <div class="card">
                                        <div class="card-header bg-white">
                                        <h4 class="card-header-title">{{ trans('lang.restaurant') }}</h4>
                                    </div>
                                    <div class="card-body">
                                            @if(!empty($details['vendor']))
                                                <a href="#" class="row redirecttopage align-items-center">
                                            <div class="col-md-3">
                                                        <img
                                                            src="{{ $details['vendor']['photo'] ?? $details['placeholder'] }}"
                                                            onerror="this.onerror=null;this.src='{{ $details['placeholder'] }}'"
                                                            class="resturant-img rounded-circle"
                                                            alt="vendor"
                                                            width="70px"
                                                            height="70px">
                                            </div>
                                            <div class="col-md-9">
                                                        <h4 class="vendor-title">{{ $details['vendor']['title'] ?? 'N/A' }}</h4>
                                            </div>
                                        </a>
                                        <h5 class="contact-info">{{ trans('lang.contact_info') }}:</h5>
                                                <p>
                                                    <strong>{{ trans('lang.phone') }}: </strong>
                                                    <span>{{ $details['vendor']['phonenumber'] ?? $details['vendor']['phoneNumber'] ?? 'N/A' }}</span>
                                        </p>
                                                <p>
                                                    <strong>{{ trans('lang.address') }}: </strong>
                                                    <span>{{ $details['vendor']['location'] ?? 'N/A' }}</span>
                                        </p>
                                            @endif
                                    </div>
                                </div>
                            </div>
                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

<!-- Estimated Time Modal -->
<div class="modal fade" id="estimatedTimeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Add Preparation Time') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="estimated-time-form">
                    <div class="form-group">
                        <label for="estimated_time_field">{{ __('Time') }}</label>
                        <input type="time" class="form-control" id="estimated_time_field" required>
                        <div class="invalid-feedback">{{ __('Please enter preparation time') }}</div>
                                </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">{{ __('submit') }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('close') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('style')
    <style>
        .order_detail-top-box p {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #dee2e6;
        }

        .order_detail-top-box p:last-child {
            border-bottom: none;
        }

        .order-product-box {
            display: flex;
            align-items: center;
        }

        .orders-tracking {
            margin-left: 10px;
        }

        .orders-tracking h6 {
            margin-bottom: 5px;
        }

        .variant-info ul {
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }

        .variant-info li {
            display: inline-block;
            margin-right: 10px;
        }

        .variant .label {
            font-weight: bold;
        }

        .order-totals {
            width: 100%;
        }

        .order-totals td {
            padding: 8px 0;
        }

        .order-totals .seprater {
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }

        .order-totals .label {
            font-weight: bold;
        }

        #greenColor {
            color: green;
        }

        .resturant-img {
            object-fit: cover;
        }
    </style>
@endsection

@section('scripts')
<script>
    (function () {
        var $statusSelect = $('#order_status');
        var $modal = $('#estimatedTimeModal');
        var $timeField = $('#estimated_time_field');
        var $hiddenField = $('#estimated_time_input');
        var $form = $('#order-status-form');

        $statusSelect.on('change', function () {
            if (this.value === 'Order Accepted') {
                $timeField.val($hiddenField.val() || '');
                $modal.modal('show');
            }
        });

        $('#estimated-time-form').on('submit', function (e) {
            e.preventDefault();
            if (!$timeField.val()) {
                $timeField.addClass('is-invalid');
                return;
            }
            $hiddenField.val($timeField.val());
            $timeField.removeClass('is-invalid');
            $modal.modal('hide');
        });

        $form.on('submit', function (e) {
            if ($statusSelect.val() === 'Order Accepted' && !$hiddenField.val()) {
                e.preventDefault();
                $timeField.val('');
                $modal.modal('show');
            }
        });

        $modal.on('hidden.bs.modal', function () {
            $timeField.removeClass('is-invalid');
        });
    })();
</script>
@endsection
