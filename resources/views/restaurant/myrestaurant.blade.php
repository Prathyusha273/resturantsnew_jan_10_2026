@extends('layouts.app')

@section('content')
    @php
        $currencyMeta = $currency ?? ['symbol' => '₹', 'symbol_at_right' => false, 'decimal_digits' => 2];
        $walletAmount = (float) ($user->wallet_amount ?? 0);
        $walletFormatted = $currencyMeta['symbol_at_right']
            ? number_format($walletAmount, $currencyMeta['decimal_digits']) . $currencyMeta['symbol']
            : $currencyMeta['symbol'] . number_format($walletAmount, $currencyMeta['decimal_digits']);
        $vendorData = $vendor ?? new \stdClass();
        $selectedCategories = old('category_ids', (array) ($vendorData->categoryID ?? []));
        $selectedCuisine = old('cuisine_id', $vendorData->cuisineID ?? '');
        $placeholderFields = $settings['placeHolderImage']->fields ?? [];
        $placeholderImageUrl = $placeholderFields['image'] ?? asset('images/placeholder.png');
        $currentPhoto = $vendorData->photo ?? $placeholderImageUrl;
        $galleryImages = $vendorData->photos ?? [];
        $adminCommissionSetting = $settings['AdminCommission']->fields ?? [];

        // Format phone number for Razorpay
        // Razorpay expects: International format [country_code][phone_number] all digits
        // Example for India: 919876543217 (91 is country code, 9876543217 is 10-digit number)

        // Try user's phone number first
        $phoneNumber = trim($user->phoneNumber ?? '');
        $countryCode = trim($user->countryCode ?? '+91');

        // If user phone number is incomplete, try vendor's phone number as fallback
        $phoneDigits = preg_replace('/\D+/', '', $phoneNumber);
        if (empty($phoneDigits) || strlen($phoneDigits) < 10) {
            // Try vendor's phone number from vendors table
            $vendorPhone = trim($vendorData->phonenumber ?? '');
            if (!empty($vendorPhone)) {
                $phoneNumber = $vendorPhone;
                $phoneDigits = preg_replace('/\D+/', '', $vendorPhone);
            }
        }

        // Extract country code digits (remove + and any non-digits)
        $countryCodeDigits = preg_replace('/\D+/', '', $countryCode);
        if (empty($countryCodeDigits)) {
            $countryCodeDigits = '91'; // Default to India
        }

        // Remove country code from phone if it's already included at the start
        if (!empty($phoneDigits) && !empty($countryCodeDigits)) {
            $countryCodeLength = strlen($countryCodeDigits);
            // Check if phone number already starts with country code
            if (strlen($phoneDigits) > $countryCodeLength && substr($phoneDigits, 0, $countryCodeLength) === $countryCodeDigits) {
                // Remove country code from the beginning to get pure phone number
                $phoneDigits = substr($phoneDigits, $countryCodeLength);
            }
        }

        // Format for Razorpay: country code + phone number (all digits, no spaces)
        // This is the E.164 format without the + sign
        // Only send if we have a valid complete phone number (at least 10 digits for India)
        if (!empty($phoneDigits) && strlen($phoneDigits) >= 10) {
            // For Indian numbers, ensure we have exactly 10 digits
            if ($countryCodeDigits === '91') {
                // If exactly 10 digits, use as is
                if (strlen($phoneDigits) === 10) {
                    $razorpayContact = $countryCodeDigits . $phoneDigits; // 91 + 10 digits = 12 digits total
                } elseif (strlen($phoneDigits) > 10) {
                    // If more than 10, take last 10 digits (most likely the actual number)
                    $phoneDigits = substr($phoneDigits, -10);
                    $razorpayContact = $countryCodeDigits . $phoneDigits;
                } else {
                    // If less than 10, it's incomplete - don't send it
                    $razorpayContact = '';
                }
            } else {
                // For other countries, combine country code + phone digits
                $razorpayContact = $countryCodeDigits . $phoneDigits;
            }
        } else {
            // Phone number is empty or too short - don't send it
            // Razorpay will prompt user to enter it manually
            $razorpayContact = '';
        }
        $adminCommissionValue = old(
            'admin_commission',
            data_get($vendorData->adminCommission ?? [], 'fix_commission', $adminCommissionSetting['commission'] ?? '')
        );
        $adminCommissionType = old(
            'admin_commission_type',
            data_get($vendorData->adminCommission ?? [], 'commissionType', 'Percent')
        );
        $vendorFilters = $vendorData->filters ?? [];
        $daysOfWeek = [
            'Sunday' => trans('lang.sunday'),
            'Monday' => trans('lang.monday'),
            'Tuesday' => trans('lang.tuesday'),
            'Wednesday' => trans('lang.wednesday'),
            'Thursday' => trans('lang.thursday'),
            'Friday' => trans('lang.friday'),
            'Saturday' => trans('lang.Saturday'),
        ];
        $specialOfferDays = $daysOfWeek;
//        $workingHours = collect($vendorData->workingHours ?? [])
//            ->mapWithKeys(fn ($item) => [$item['day'] => $item['timeslot'] ?? []])
//            ->all();
//$workingHours = old(
//    'working_hours',
//    collect($vendorData->workingHours ?? [])
//        ->mapWithKeys(function ($item) {
//            return [
//                $item['day'] => array_values($item['timeslot'] ?? [])
//            ];
//        })
//        ->all()
//);
          $rawWorkingHours = $vendorData->workingHours ?? null;

// Decode JSON safely (because column is TEXT)
if (is_string($rawWorkingHours)) {
    $rawWorkingHours = json_decode($rawWorkingHours, true);
}

// Normalize for UI
$workingHours = old(
    'working_hours',
    collect($rawWorkingHours ?? [])
        ->filter(fn ($item) => is_array($item) && isset($item['day']))
        ->mapWithKeys(function ($item) {
            return [
                $item['day'] => array_values($item['timeslot'] ?? [])
            ];
        })
        ->all()
);

        $specialDiscountOld = old('special_discount');
        if (is_array($specialDiscountOld)) {
            $specialDiscountData = [];
            foreach ($specialDiscountOld as $dayKey => $slots) {
                $specialDiscountData[$dayKey] = array_values($slots ?? []);
            }
        } else {
            $specialDiscountData = collect($vendorData->specialDiscount ?? [])
                ->mapWithKeys(fn ($item) => [$item['day'] => array_values($item['timeslot'] ?? [])])
                ->all();
        }
        $specialDiscountEnabled = old('special_discount_enable', $vendorData->specialDiscountEnable ?? false);
        $hasSpecialDiscountRows = collect($specialDiscountData)->flatten(1)->isNotEmpty();
    @endphp
    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-5 align-self-center">
                <h3 class="text-themecolor">{{ trans('lang.myrestaurant_plural') }}</h3>
            </div>
            <div class="col-md-7 align-self-center">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ trans('lang.dashboard') }}</a>
                    </li>
                    <li class="breadcrumb-item active">{{ trans('lang.myrestaurant_plural') }}</li>
                </ol>
            </div>
        </div>

        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible mb-4" role="alert" id="success-message-alert" style="display: block !important; opacity: 1 !important; visibility: visible !important;">
                            <i class="fa fa-check-circle mr-2"></i>
                            <strong>{{ __('Success!') }}</strong> {{ session('success') }}
                            <button type="button" class="close" aria-label="Close" onclick="document.getElementById('success-message-alert').style.display='none'">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <script>
                            // Minimal script for success message - isolated and non-conflicting
                            (function() {
                                var alertEl = document.getElementById('success-message-alert');
                                if (alertEl) {
                                    // Remove Bootstrap fade class to prevent conflicts
                                    alertEl.classList.remove('fade');
                                    // Ensure visibility with inline styles (highest priority)
                                    alertEl.style.cssText += 'display: block !important; opacity: 1 !important; visibility: visible !important;';

                                    // Scroll to top smoothly
                                    if (window.pageYOffset > 50) {
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }

                                    // Auto-dismiss after 8 seconds (optional)
                                    setTimeout(function() {
                                        if (alertEl && alertEl.parentNode) {
                                            alertEl.style.opacity = '0';
                                            alertEl.style.transition = 'opacity 0.3s';
                                            setTimeout(function() {
                                                if (alertEl && alertEl.parentNode) {
                                                    alertEl.style.display = 'none';
                                                }
                                            }, 300);
                                        }
                                    }, 8000);
                                }
                            })();
                        </script>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger mb-4">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="error_top mb-3"></div>

                    <form method="POST" action="{{ route('restaurant.update') }}" enctype="multipart/form-data"
                          id="restaurant-form">
                        @csrf
                        <input type="hidden" name="admin_commission_type" value="{{ $adminCommissionType }}">
                        <input type="hidden" name="remove_photo" id="remove_photo_input" value="0">

                        <fieldset class="mb-4">
                            <button class="btn btn-primary mb-3">{{ trans('lang.restaurant_details') }}</button>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="restaurant_name">{{ trans('lang.restaurant_name') }}</label>
                                        <input type="text"
                                               id="restaurant_name"
                                               name="title"
                                               class="form-control @error('title') is-invalid @enderror"
                                               value="{{ old('title', $vendorData->title ?? '') }}"
                                               required>
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_name_help') }}</small>
                                        @error('title')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="restaurant_slug">{{ __('Restaurant Slug') }}</label>
                                        <input type="text"
                                               id="restaurant_slug"
                                               name="restaurant_slug"
                                               class="form-control @error('restaurant_slug') is-invalid @enderror"
                                               value="{{ old('restaurant_slug', $vendorData->restaurant_slug ?? '') }}"
                                               readonly>
                                        <small
                                            class="form-text text-muted">{{ __('Auto-generated from restaurant name.') }}</small>
                                        @error('restaurant_slug')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ trans('lang.wallet_amount') }}</label>
                                        <div class="h4 text-primary mb-0">{{ $walletFormatted }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vendor_cuisine">{{ trans('lang.restaurant_cuisines') }}</label>
                                        <select id="vendor_cuisine"
                                                name="cuisine_id"
                                                class="form-control @error('cuisine_id') is-invalid @enderror">
                                            <option value="">{{ trans('lang.select_cuisines') }}</option>
                                            @foreach($cuisines as $cuisine)
                                                <option
                                                    value="{{ $cuisine->id }}" {{ (string) $selectedCuisine === (string) $cuisine->id ? 'selected' : '' }}>
                                                    {{ $cuisine->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_cuisines_help') }}</small>
                                        @error('cuisine_id')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Categories -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_search">{{ trans('lang.restaurant_categories') }}</label>
                                        <input type="text"
                                               id="category_search"
                                               class="form-control mb-2"
                                               placeholder="{{ __('Search categories...') }}">
                                        <select id="restaurant_category"
                                                name="category_ids[]"
                                                class="form-control @error('category_ids') is-invalid @enderror"
                                                multiple>
                                            @foreach($categories as $category)
                                                <option
                                                    value="{{ $category->id }}" {{ in_array($category->id, $selectedCategories, true) ? 'selected' : '' }}>
                                                    {{ $category->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ trans('lang.restaurant_categories_help') }}</small>
                                        @error('category_ids')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <!-- Vendor Type -->
                                <div class="col-md-6">
                                    <div class="form-group row">
                                        <label class="col-4 control-label">
                                            Vendor Type <span class="required-field"></span>
                                        </label>
                                        <div class="col-8">
                                            <select id="vendor_type"
                                                    name="vendor_type"
                                                    class="form-control"
                                                    required>
                                                <option value="restaurant"
                                                    {{ old('vendor_type', $vendorData->vType ?? 'restaurant') === 'restaurant' ? 'selected' : '' }}>
                                                    Restaurant (Default)
                                                </option>
                                                <option value="mart"
                                                    {{ old('vendor_type', $vendorData->vType ?? 'restaurant') === 'mart' ? 'selected' : '' }}>
                                                    Mart
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="row">
                                <div class="col-md-6" style="display:none;">
                                    <div class="form-group">
                                        <label for="admin_commission">{{ __('Admin Commission') }}</label>
                                        <input type="text"
                                               id="admin_commission"
                                               name="admin_commission"
                                               class="form-control"
                                               value="{{ $adminCommissionValue }}"
                                               readonly>
                                        <small
                                            class="form-text text-muted">{{ __('Commission value provided by admin.') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="restaurant_phone">{{ trans('lang.restaurant_phone') }}</label>
                                        <input type="text"
                                               id="restaurant_phone"
                                               name="phone"
                                               class="form-control @error('phone') is-invalid @enderror"
                                               value="{{ old('phone', $vendorData->phonenumber ?? '') }}"
                                               required>
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_phone_help') }}</small>
                                        @error('phone')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Zone Selection Fields (Must come before Subscription Plan) --}}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zone">{{ trans('lang.zone') }}</label>
                                        <select id="zone"
                                                name="zone_id"
                                                class="form-control @error('zone_id') is-invalid @enderror">
                                            <option value="">{{ trans('lang.select_zone') }}</option>
                                            @foreach($zones as $zone)
                                                <option
                                                    value="{{ $zone->id }}" {{ old('zone_id', $vendorData->zoneId ?? '') === $zone->id ? 'selected' : '' }}>
                                                    {{ $zone->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('zone_id')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zone_slug">{{ __('Zone Slug') }}</label>
                                        <input type="text"
                                               id="zone_slug"
                                               name="zone_slug"
                                               class="form-control @error('zone_slug') is-invalid @enderror"
                                               value="{{ old('zone_slug', $vendorData->zone_slug ?? '') }}"
                                               readonly>
                                        <small
                                            class="form-text text-muted">{{ __('Auto-generated from selected zone.') }}</small>
                                        @error('zone_slug')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Address and Location Fields (Must come before Subscription Plan) --}}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="restaurant_address">{{ trans('lang.restaurant_address') }}</label>
                                        <input type="text"
                                               id="restaurant_address"
                                               name="location"
                                               class="form-control @error('location') is-invalid @enderror"
                                               value="{{ old('location', $vendorData->location ?? '') }}"
                                               required>
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_address_help') }}</small>
                                        @error('location')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="restaurant_latitude">{{ trans('lang.restaurant_latitude') }}</label>
                                        <input type="number"
                                               step="any"
                                               id="restaurant_latitude"
                                               name="latitude"
                                               class="form-control @error('latitude') is-invalid @enderror"
                                               value="{{ old('latitude', $vendorData->latitude ?? '') }}">
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_latitude_help') }}</small>
                                        @error('latitude')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label
                                            for="restaurant_longitude">{{ trans('lang.restaurant_longitude') }}</label>
                                        <input type="number"
                                               step="any"
                                               id="restaurant_longitude"
                                               name="longitude"
                                               class="form-control @error('longitude') is-invalid @enderror"
                                               value="{{ old('longitude', $vendorData->longitude ?? '') }}">
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_longitude_help') }}</small>
                                        @error('longitude')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Subscription Plan Selection --}}
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>{{ __('Select Subscription Plan') }} <span class="text-danger">*</span></label>
                                        <select id="subscription_plan_select"
                                                name="subscription_plan_id"
                                                class="form-control @error('subscription_plan_id') is-invalid @enderror"
                                                required>
                                            <option value="">{{ __('-- Select a subscription plan --') }}</option>
                                            @foreach($subscriptionPlans as $plan)
                                                <option value="{{ $plan->id }}"
                                                        data-name="{{ $plan->name }}"
                                                        data-price="{{ $plan->price }}"
                                                        data-place="{{ $plan->place }}"
                                                        data-expiry-day="{{ $plan->expiryDay }}"
                                                        data-description="{{ $plan->description ?? '' }}"
                                                        data-plan-type="{{ $plan->plan_type ?? ($plan->type ?? 'commission') }}"
                                                        data-zone="{{ $plan->zone ?? '' }}"
                                                    {{ $vendor && $vendor->subscriptionPlanId == $plan->id ? 'selected' : '' }}>
                                                    {{ $plan->name }} - {{ $currency['symbol'] }}{{ number_format($plan->price, 2) }} ({{ $plan->place }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ __('Select a subscription plan to proceed with payment. Plans are filtered by selected zone.') }}</small>
                                        @error('subscription_plan_id')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Plan Details Display --}}
                            <div id="plan-details-container" class="row" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">{{ __('Selected Plan Details') }}</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>{{ __('Plan Name') }}:</strong> <span id="plan-name-display"></span></p>
                                                    <p><strong>{{ __('Price') }}:</strong> <span id="plan-price-display"></span></p>
                                                    <p id="plan-commission-container" style="display:none;"><strong>{{ __('Commission Percentage') }}:</strong> <span id="plan-place-display"></span>%</p>
                                                    <p id="plan-type-display-container"><strong>{{ __('Plan Type') }}:</strong> <span id="plan-type-display"></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>{{ __('Description') }}:</strong> <span id="plan-description-display"></span></p>
                                                    <p><strong>{{ __('Expiry Days') }}:</strong> <span id="plan-expiry-display"></span></p>
                                                </div>
                                            </div>
                                            <div class="text-center mt-3">
                                                <button type="button"
                                                        class="btn btn-success btn-lg"
                                                        id="proceed-to-payment-btn"
                                                        @if(!$vendor) disabled @endif>
                                                    <i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}
                                                </button>
                                                @if(!$vendor)
                                                    <p class="text-danger mt-2 mb-0">
                                                        {{ __('Please save your restaurant details once before purchasing a subscription plan.') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Subscription/Commission Type Fields - Hidden by default, shown dynamically when plan is selected --}}
                            <div id="subscription_details_container" class="row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Subscription') }}</label>
                                        <input type="text"
                                               id="subscription_status_display"
                                               class="form-control"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6" style="display:none;">
                                    <div class="form-group">
                                        <label>{{ __('Commission Type') }}</label>
                                        <input type="text"
                                               id="commission_type_display"
                                               class="form-control"
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <div id="plan_commission_details_container" class="row" style="display: none;">
                                {{-- Subscription Slab: Only show for subscription plans (not commission) --}}
                                <div class="col-md-6" id="subscription_slab_container" style="display:none;">
                                    <div class="form-group">
                                        <label>{{ __('Subscription Slab') }}</label>
                                        <input type="text"
                                               id="subscription_slab_display"
                                               class="form-control"
                                               readonly>
                                        <small class="form-text text-muted">{{ __('Display if subscription plan selected (not commission)') }}</small>
                                    </div>
                                </div>
                                {{-- Commission Percentage: Show for commission plan --}}
                                <div class="col-md-6" id="commission_percentage_container" style="display:none;">
                                    <div class="form-group">
                                        <label>{{ __('% added on Item') }}</label>
                                        <input type="text"
                                               id="commission_percentage_display"
                                               class="form-control"
                                               readonly>
                                        <small class="form-text text-muted">{{ __('Commission percentage (shown for commission plan)') }}</small>
                                    </div>
                                </div>
                            </div>
                            {{-- Plan Details: Show for both commission and subscription plans when plan is selected --}}
                            <div id="plan_details_info_container" class="row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Plan Name') }}</label>
                                        <input type="text"
                                               id="plan_name_display"
                                               class="form-control"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Plan Type') }}</label>
                                        <input type="text"
                                               id="plan_type_display"
                                               class="form-control"
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <div id="plan_payment_info_container" class="row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Payment Status') }}</label>
                                        <input type="text"
                                               id="payment_status_display"
                                               class="form-control"
                                               value=""
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6" id="expiry_date_container" style="display:none;">
                                    <div class="form-group">
                                        <label>{{ __('Expiry Date') }}</label>
                                        <input type="text"
                                               id="expiry_date_display"
                                               class="form-control"
                                               value=""
                                               readonly>
                                    </div>
                                </div>
                            </div>

                            {{-- GST Agreement Toggle --}}
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   id="gst_agreement"
                                                   name="gst"
                                                   value="1"
                                                {{ old('gst', $vendorData->gst ?? 0) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="gst_agreement">
                                                <strong>{{ __('GST Agreement') }}</strong>
                                                <small class="text-muted d-block">{{ __('I agree to GST terms. Platform will absorb 5% GST if checked.') }}</small>
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">
                                            {{ __('If checked: Platform absorbs 5% GST. If unchecked: GST (5%) will be added to customer price.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="restaurant_description">{{ trans('lang.restaurant_description') }}</label>
                                <textarea id="restaurant_description"
                                          name="description"
                                          rows="5"
                                          class="form-control @error('description') is-invalid @enderror"
                                          required>{{ old('description', $vendorData->description ?? '') }}</textarea>
                                @error('description')
                                <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-check">
                                <input type="checkbox"
                                       id="is_open"
                                       name="is_open"
                                       value="1"
                                       class="form-check-input"
                                    {{ old('is_open', $vendorData->isOpen ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_open">{{ __('Open / Closed') }}</label>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="font-weight-bold text-dark">{{ trans('lang.restaurant_image') }}</legend>
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <img src="{{ $vendorData->photo ?? $placeholderImageUrl }}"
                                         alt="{{ trans('lang.restaurant_image') }}"
                                         class="rounded border mb-3"
                                         {{--                                         class="img-fluid rounded border mb-3"--}}
                                         id="restaurant-photo-preview"
                                         width="150px" height="150px"
                                         data-placeholder="{{ $placeholderImageUrl }}" >
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                id="remove-photo-btn">
                                            <i class="fa fa-times"></i> {{ __('Remove Photo') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="restaurant_photo">{{ __('Upload New Photo') }}</label>
                                        <input type="file"
                                               id="restaurant_photo"
                                               name="photo"
                                               accept="image/*"
                                               class="form-control-file @error('photo') is-invalid @enderror">
                                        <small
                                            class="form-text text-muted">{{ trans('lang.restaurant_image_help') }}</small>
                                        @error('photo')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 border border 4px solid p-4" style="display: none;">
                            <button class="btn btn-primary mb-3">{{ trans('lang.gallery') }}</button>
                            @if(!empty($galleryImages))
                                <div class="row">
                                    @foreach($galleryImages as $index => $image)
                                        <div class="col-md-3 mb-3">
                                            <div class="border rounded p-2 h-100 text-center">
                                                <img src="{{ $image }}"
                                                     alt="{{ __('Gallery image #:number', ['number' => $loop->iteration]) }}"
                                                     class="img-fluid mb-2">
                                                <div class="form-check">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           name="remove_gallery[]"
                                                           value="{{ $image }}"
                                                           id="remove_gallery_{{ $index }}">
                                                    <label class="form-check-label" for="remove_gallery_{{ $index }}">
                                                        {{ __('Remove') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted">{{ __('No gallery images uploaded yet.') }}</p>
                            @endif
                            <div class="form-group mb-0">
                                <label for="gallery_input">{{ __('Add Gallery Images') }}</label>
                                <input type="file"
                                       id="gallery_input"
                                       name="gallery[]"
                                       accept="image/*"
                                       class="form-control-file @error('gallery.*') is-invalid @enderror"
                                       multiple>
                                @error('gallery.*')
                                <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 border border 4px solid p-4" style="display:none">
                            <button class="btn btn-primary mb-3">{{ trans('lang.services') }}</button>
                            <div class="row">
                                @foreach($filterOptions as $label => $translationKey)
                                    @php
                                        $checked = old("filters.$label", ($vendorFilters[$label] ?? 'No') === 'Yes');
                                    @endphp
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   id="filter_{{ \Illuminate\Support\Str::slug($label, '_') }}"
                                                   name="filters[{{ $label }}]"
                                                   value="1"
                                                {{ $checked ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                   for="filter_{{ \Illuminate\Support\Str::slug($label, '_') }}">
                                                {{ trans($translationKey) }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 border border 4px solid p-4">
                            <button class="btn btn-primary mb-3">{{ __('Special Offer') }}</button>
                            <div class="form-check mb-3">
                                <input type="checkbox"
                                       id="special_discount_enable"
                                       name="special_discount_enable"
                                       value="1"
                                       class="form-check-input"
                                    {{ $specialDiscountEnabled ? 'checked' : '' }}>
                                <label class="form-check-label"
                                       for="special_discount_enable">{{ __('Enable Special Discount') }}</label>
                            </div>
                            <p class="text-danger mb-3">{{ __('NOTE : Please Click on Edit Button After Making Changes in Special Discount, Otherwise Data may not Save!!') }}</p>
                            <button type="button" class="btn btn-primary mb-3"
                                    id="toggle-special-offer">{{ __('Add Special Offer') }}</button>
                            <div id="special-offer-panel"
                                 class="{{ !$specialDiscountEnabled && !$hasSpecialDiscountRows ? 'd-none' : '' }}"
                                 data-has-rows="{{ $hasSpecialDiscountRows ? 'true' : 'false' }}">
                                @foreach($specialOfferDays as $dayKey => $dayLabel)
                                    @php
                                        $daySlots = $specialDiscountData[$dayKey] ?? [];
                                        $nextSpecialIndex = count($daySlots);
                                    @endphp
                                    <div class="special-offer-day border rounded p-3 mb-3" data-day="{{ $dayKey }}">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="mb-0">{{ $dayLabel }}</h5>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary special-offer-add"
                                                    data-day="{{ $dayKey }}">
                                                {{ __('Add') }}
                                            </button>
                                        </div>
                                        <div class="table-responsive special-offer-table" data-day="{{ $dayKey }}">
                                            <table class="table table-sm mb-0">
                                                <thead class="thead-light">
                                                <tr>
                                                    <th>{{ trans('lang.Opening_Time') }}</th>
                                                    <th>{{ trans('lang.Closing_Time') }}</th>
                                                    <th>{{ trans('lang.coupon_discount') }}</th>
                                                    <th>{{ __('Discount Type') }}</th>
                                                    <th>{{ __('Applies To') }}</th>
                                                    <th>{{ trans('lang.actions') }}</th>
                                                </tr>
                                                </thead>
                                                <tbody data-next-index="{{ $nextSpecialIndex }}">
                                                @forelse($daySlots as $index => $slot)
                                                    <tr class="special-offer-row">
                                                        <td>
                                                            <input type="time"
                                                                   class="form-control"
                                                                   name="special_discount[{{ $dayKey }}][{{ $index }}][from]"
                                                                   value="{{ $slot['from'] ?? '' }}">
                                                        </td>
                                                        <td>
                                                            <input type="time"
                                                                   class="form-control"
                                                                   name="special_discount[{{ $dayKey }}][{{ $index }}][to]"
                                                                   value="{{ $slot['to'] ?? '' }}">
                                                        </td>
                                                        <td>
                                                            <input type="number"
                                                                   step="0.01"
                                                                   min="0"
                                                                   class="form-control"
                                                                   name="special_discount[{{ $dayKey }}][{{ $index }}][discount]"
                                                                   value="{{ $slot['discount'] ?? '' }}">
                                                        </td>
                                                        <td>
                                                            <select class="form-control"
                                                                    name="special_discount[{{ $dayKey }}][{{ $index }}][type]">
                                                                <option
                                                                    value="percentage" {{ ($slot['type'] ?? 'percentage') === 'percentage' ? 'selected' : '' }}>
                                                                    %
                                                                </option>
                                                                <option
                                                                    value="amount" {{ ($slot['type'] ?? 'percentage') === 'amount' ? 'selected' : '' }}>
                                                                    {{ $currencyMeta['symbol'] }}
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select class="form-control"
                                                                    name="special_discount[{{ $dayKey }}][{{ $index }}][discount_type]">
                                                                <option
                                                                    value="delivery" {{ ($slot['discount_type'] ?? 'delivery') === 'delivery' ? 'selected' : '' }}>
                                                                    {{ __('Delivery Discount') }}
                                                                </option>
                                                                <option
                                                                    value="dinein" {{ ($slot['discount_type'] ?? 'delivery') === 'dinein' ? 'selected' : '' }}>
                                                                    {{ __('Dine-In Discount') }}
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <button type="button"
                                                                    class="btn btn-outline-danger btn-sm special-offer-remove">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="text-muted small mb-0 special-offer-empty {{ empty($daySlots) ? '' : 'd-none' }}">{{ __('No slots added yet.') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 border border 4px solid p-4">
                            <button
                                class="btn btn-primary mb-3">{{ trans('lang.dine_in_future_setting') }}</button>
                            <div class="form-check mb-3">
                                <input type="checkbox"
                                       id="dine_in_feature"
                                       name="enabled_dine_in_future"
                                       value="1"
                                       class="form-check-input"
                                    {{ old('enabled_dine_in_future', $vendorData->enabledDiveInFuture ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label"
                                       for="dine_in_feature">{{ trans('lang.enable_dine_in_feature') }}</label>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="openDineTime">{{ trans('lang.Opening_Time') }}</label>
                                        <input type="time"
                                               id="openDineTime"
                                               name="open_dine_time"
                                               class="form-control @error('open_dine_time') is-invalid @enderror"
                                               value="{{ old('open_dine_time', $vendorData->openDineTime ?? '') }}">
                                        @error('open_dine_time')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="closeDineTime">{{ trans('lang.Closing_Time') }}</label>
                                        <input type="time"
                                               id="closeDineTime"
                                               name="close_dine_time"
                                               class="form-control @error('close_dine_time') is-invalid @enderror"
                                               value="{{ old('close_dine_time', $vendorData->closeDineTime ?? '') }}">
                                        @error('close_dine_time')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="restaurant_cost">{{ __('Cost') }}</label>
                                        <input type="number"
                                               id="restaurant_cost"
                                               name="restaurant_cost"
                                               class="form-control @error('restaurant_cost') is-invalid @enderror"
                                               value="{{ old('restaurant_cost', $vendorData->restaurantCost ?? '') }}"
                                               min="0"
                                               step="0.01">
                                        @error('restaurant_cost')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="border border 4px solid p-4">
                            <button class="btn btn-primary mb-3">{{ trans('lang.working_hours') }}</button>
                            <p class="text-danger">{{ trans('lang.working_hour_note') }}</p>
                            <div class="working-hours-wrapper">
                                @foreach($daysOfWeek as $dayKey => $dayLabel)
                                    @php
                                        $slots = $workingHours[$dayKey] ?? [];
                                        $nextIndex = count($slots);
                                    @endphp
                                    <div class="card mb-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span class="font-weight-bold">{{ $dayLabel }}</span>
                                            <button type="button"
                                                    class="btn btn-primary mb-3 add-slot"
                                                    data-day="{{ $dayKey }}">
                                                <i class="fa fa-plus"></i> {{ trans('lang.add_more') }}
                                            </button>
                                        </div>
                                        <div class="card-body working-slots"
                                             data-day="{{ $dayKey }}"
                                             data-next-index="{{ $nextIndex }}"
                                             data-empty-text="{{ __('No slots added yet.') }}">
                                            @forelse($slots as $index => $slot)
                                                @include('restaurant.partials.working-slot', ['day' => $dayKey, 'index' => $index, 'slot' => $slot])
                                            @empty
                                                <p class="text-muted small mb-0 empty-message">{{ __('No slots added yet.') }}</p>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="mt-4 border border 4px solid p-4">
                            <button class="btn btn-primary mb-3">{{ __('Story') }}</button>
                            <p class="text-danger mb-4">{{ __('NOTE : Please Click on Save Button After Making Changes in Image Or Video, Otherwise Data may not Save!!') }}</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="d-block">{{ __('Choose humbling GIF/Image') }}</label>
                                        <input type="file"
                                               name="story_thumbnail"
                                               accept="image/*"
                                               class="form-control-file @error('story_thumbnail') is-invalid @enderror">
                                        @error('story_thumbnail')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                        @enderror
                                        @if(!empty($story?->video_thumbnail))
                                            <div class="mt-3">
                                                <img src="{{ $story->video_thumbnail }}"
                                                     alt="{{ __('Story thumbnail') }}"
                                                     class="img-fluid rounded border"
                                                     style="max-height: 200px;">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="d-block">{{ __('Select Story Video') }}</label>
                                        <input type="file"
                                               name="story_video"
                                               accept="video/*"
                                               class="form-control-file @error('story_video') is-invalid @enderror">
                                        @error('story_video')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                        @enderror
                                        @php
                                            $existingVideo = is_array($story?->video_url ?? null) ? ($story->video_url[0] ?? null) : null;
                                        @endphp
                                        @if($existingVideo)
                                            <div class="mt-3">
                                                <video controls width="100%" style="max-height:240px;">
                                                    <source src="{{ $existingVideo }}" type="video/mp4">
                                                    {{ __('Your browser does not support the video tag.') }}
                                                </video>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> {{ trans('lang.save') }}
                            </button>
                            <a href="{{ route('home') }}" class="btn btn-default">
                                <i class="fa fa-undo"></i> {{ trans('lang.cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script type="text/template" id="special-offer-row-template">
        <tr class="special-offer-row">
            <td>
                <input type="time"
                       class="form-control"
                       name="special_discount[__DAY__][__INDEX__][from]">
            </td>
            <td>
                <input type="time"
                       class="form-control"
                       name="special_discount[__DAY__][__INDEX__][to]">
            </td>
            <td>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-control"
                       name="special_discount[__DAY__][__INDEX__][discount]">
            </td>
            <td>
                <select class="form-control"
                        name="special_discount[__DAY__][__INDEX__][type]">
                    <option value="percentage">%</option>
                    <option value="amount">{{ $currencyMeta['symbol'] }}</option>
                </select>
            </td>
            <td>
                <select class="form-control"
                        name="special_discount[__DAY__][__INDEX__][discount_type]">
                    <option value="delivery">{{ __('Delivery Discount') }}</option>
                    <option value="dinein">{{ __('Dine-In Discount') }}</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-primary mb-3 special-offer-remove">
                    <i class="fa fa-times"></i>
                </button>
            </td>
        </tr>
    </script>
    <script type="text/template" id="working-slot-template">
        <div class="row align-items-end working-slot" data-index="__INDEX__">
            <div class="col-md-5 mb-2">
                <label class="form-label">{{ trans('lang.from') }}</label>
                <input type="time"
                       class="form-control"
                       name="working_hours[__DAY__][__INDEX__][from]">
            </div>
            <div class="col-md-5 mb-2">
                <label class="form-label">{{ trans('lang.to') }}</label>
                <input type="time"
                       class="form-control"
                       name="working_hours[__DAY__][__INDEX__][to]">
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary mb-3 remove-slot w-100" data-day="__DAY__">
                    {{ __('Remove') }}
                </button>
            </div>
        </div>
    </script>
    <script>
        (function () {
            const slugify = (text) => text
                .toString()
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            const nameInput = document.getElementById('restaurant_name');
            const slugInput = document.getElementById('restaurant_slug');
            const zoneSelect = document.getElementById('zone');
            const zoneSlug = document.getElementById('zone_slug');
            const categorySelect = document.getElementById('restaurant_category');
            const categorySearch = document.getElementById('category_search');
            const workingSlotTemplate = document.getElementById('working-slot-template').innerHTML.trim();
            const specialOfferTemplate = document.getElementById('special-offer-row-template')?.innerHTML.trim();
            const removePhotoBtn = document.getElementById('remove-photo-btn');
            const photoInput = document.getElementById('restaurant_photo');
            const photoPreview = document.getElementById('restaurant-photo-preview');
            const removePhotoInput = document.getElementById('remove_photo_input');
            const specialOfferPanel = document.getElementById('special-offer-panel');
            const specialOfferToggle = document.getElementById('toggle-special-offer');
            const specialDiscountEnable = document.getElementById('special_discount_enable');

            if (nameInput && slugInput) {
                // Auto-update restaurant slug when name changes (readonly field)
                nameInput.addEventListener('input', () => {
                    slugInput.value = slugify(nameInput.value);
                });
                // Initial slug generation if empty
                if (!slugInput.value && nameInput.value) {
                    slugInput.value = slugify(nameInput.value);
                }
            }

            if (zoneSelect && zoneSlug) {
                const updateZoneSlug = () => {
                    const option = zoneSelect.options[zoneSelect.selectedIndex];
                    if (option && option.value) {
                        zoneSlug.value = slugify(option.text);
                    }
                };
                zoneSelect.addEventListener('change', function() {
                    updateZoneSlug();
                    // Reset subscription plan selection when zone changes
                    if (subscriptionPlanSelect) {
                        subscriptionPlanSelect.value = '';
                        updateSubscriptionDetailsDisplay(null);
                        if (planDetailsContainer) {
                            planDetailsContainer.style.display = 'none';
                        }
                    }
                    updateSubscriptionPlansByZone();
                });
                updateZoneSlug();
            }

            // Function to update subscription plans based on selected zone
            function updateSubscriptionPlansByZone() {
                const zoneSelect = document.getElementById('zone');
                const subscriptionPlanSelect = document.getElementById('subscription_plan_select');
                const planDetailsContainer = document.getElementById('plan-details-container');

                if (!zoneSelect || !subscriptionPlanSelect) {
                    return;
                }


                // Get zone_id from the zone dropdown (ID from zone table)
                const zoneId = zoneSelect.value;

                // Store currently selected plan ID (if any) to try to preserve it
                const currentSelectedPlanId = subscriptionPlanSelect.value;

                // Show loading state
                subscriptionPlanSelect.disabled = true;
                subscriptionPlanSelect.innerHTML = '<option value="">Loading plans...</option>';

                // Fetch subscription plans for the selected zone using zone_id
                const url = '{{ route("restaurant.subscription-plans") }}' +
                    (zoneId ? '?zone_id=' + encodeURIComponent(zoneId) : '');
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })




                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.plans) {
                            // Clear existing options
                            subscriptionPlanSelect.innerHTML = '<option value="">{{ __('-- Select a subscription plan --') }}</option>';

                            let foundPreviousSelection = false;

                            // Add new options
                            data.plans.forEach(function(plan) {
                                const option = document.createElement('option');
                                option.value = plan.id;
                                option.setAttribute('data-name', plan.name || '');
                                option.setAttribute('data-price', plan.price || 0);
                                option.setAttribute('data-place', plan.place || 0);
                                option.setAttribute('data-expiry-day', plan.expiryDay || '-1');
                                option.setAttribute('data-description', plan.description || '');
                                option.setAttribute('data-plan-type', plan.plan_type || (plan.type || 'commission'));
                                option.setAttribute('data-zone', plan.zone || '');

                                // Try to preserve previous selection if this plan matches
                                if (currentSelectedPlanId && plan.id === currentSelectedPlanId) {
                                    option.selected = true;
                                    foundPreviousSelection = true;
                                }

                                const currencySymbol = '{{ $currencyMeta['symbol'] ?? '₹' }}';
                                const planType = plan.plan_type || (plan.place > 0 ? 'commission' : 'subscription');
                                let planText = plan.name + ' - ' + currencySymbol + parseFloat(plan.price || 0).toFixed(2);

                                // Only show commission percentage for commission plans
                                if (planType === 'commission' && plan.place > 0) {
                                    planText += ' (' + plan.place + '%)';
                                }

                                option.textContent = planText;

                                subscriptionPlanSelect.appendChild(option);
                            });

                            // If previous selection was preserved, trigger change event to update plan details
                            if (foundPreviousSelection && subscriptionPlanSelect.value) {
                                subscriptionPlanSelect.dispatchEvent(new Event('change'));
                            } else {
                                // Hide plan details if previous selection not found
                                if (planDetailsContainer) {
                                    planDetailsContainer.style.display = 'none';
                                }
                            }
                        } else {
                            subscriptionPlanSelect.innerHTML = '<option value="">{{ __('-- Select a subscription plan --') }}</option>';
                            if (planDetailsContainer) {
                                planDetailsContainer.style.display = 'none';
                            }
                            if (data.message) {
                                console.warn('Failed to load subscription plans:', data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching subscription plans:', error);
                        subscriptionPlanSelect.innerHTML = '<option value="">{{ __('-- Select a subscription plan --') }}</option>';
                        if (planDetailsContainer) {
                            planDetailsContainer.style.display = 'none';
                        }
                        alert('{{ __('Failed to load subscription plans. Please refresh the page.') }}');
                    })
                    .finally(() => {
                        subscriptionPlanSelect.disabled = false;
                    });
            }

            if (categorySelect && categorySearch) {
                const updateSearchWithSelected = () => {
                    const titles = Array.from(categorySelect.selectedOptions).map(opt => opt.text);
                    categorySearch.value = titles.join(', ');
                };

                categorySearch.addEventListener('input', () => {
                    const query = categorySearch.value.toLowerCase();
                    Array.from(categorySelect.options).forEach(option => {
                        if (!option.value) {
                            return;
                        }
                        option.hidden = option.text.toLowerCase().indexOf(query) === -1;
                    });
                });

                categorySelect.addEventListener('change', updateSearchWithSelected);
                updateSearchWithSelected();
            }

            document.querySelectorAll('.add-slot').forEach(button => {
                button.addEventListener('click', () => {
                    const day = button.dataset.day;
                    const container = document.querySelector(`.working-slots[data-day="${day}"]`);
                    if (!container) {
                        return;
                    }
                    container.querySelector('.empty-message')?.remove();
                    const index = parseInt(container.dataset.nextIndex || '0', 10);
                    container.dataset.nextIndex = index + 1;
                    const html = workingSlotTemplate
                        .replace(/__DAY__/g, day)
                        .replace(/__INDEX__/g, index);
                    container.insertAdjacentHTML('beforeend', html);
                });
            });

            document.addEventListener('click', (event) => {
                const removeBtn = event.target.closest('.remove-slot');
                if (!removeBtn) {
                    return;
                }
                event.preventDefault();
                const slot = removeBtn.closest('.working-slot');
                const container = removeBtn.closest('.working-slots');
                if (slot) {
                    slot.remove();
                }
                if (container && !container.querySelector('.working-slot')) {
                    container.insertAdjacentHTML(
                        'beforeend',
                        `<p class="text-muted small mb-0 empty-message">${container.dataset.emptyText}</p>`
                    );
                }
            });

            if (removePhotoBtn && photoPreview && removePhotoInput) {
                removePhotoBtn.addEventListener('click', () => {
                    photoPreview.src = photoPreview.dataset.placeholder;
                    removePhotoInput.value = '1';
                    if (photoInput) {
                        photoInput.value = '';
                    }
                });
            }

            if (photoInput && photoPreview && removePhotoInput) {
                photoInput.addEventListener('change', (event) => {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        photoPreview.src = e.target.result;
                        removePhotoInput.value = '0';
                    };
                    reader.readAsDataURL(file);
                });
            }

            const updateSpecialOfferVisibility = () => {
                if (!specialOfferPanel || !specialDiscountEnable) {
                    return;
                }
                const hasRows = specialOfferPanel.querySelectorAll('tbody tr').length > 0;
                specialOfferPanel.dataset.hasRows = hasRows ? 'true' : 'false';
                const shouldShow = specialDiscountEnable.checked || hasRows;
                specialOfferPanel.classList.toggle('d-none', !shouldShow);
            };

            specialOfferToggle?.addEventListener('click', () => {
                if (specialDiscountEnable) {
                    specialDiscountEnable.checked = true;
                }
                specialOfferPanel?.classList.remove('d-none');
            });

            specialDiscountEnable?.addEventListener('change', updateSpecialOfferVisibility);

            document.querySelectorAll('.special-offer-add').forEach(button => {
                button.addEventListener('click', () => {
                    if (!specialOfferTemplate) {
                        return;
                    }
                    const day = button.dataset.day;
                    const tableBody = document.querySelector(`.special-offer-table[data-day="${day}"] tbody`);
                    if (!tableBody) {
                        return;
                    }
                    const emptyMsg = button.closest('.special-offer-day')?.querySelector('.special-offer-empty');
                    emptyMsg?.classList.add('d-none');
                    const index = parseInt(tableBody.dataset.nextIndex || '0', 10);
                    tableBody.dataset.nextIndex = index + 1;
                    const html = specialOfferTemplate
                        .replace(/__DAY__/g, day)
                        .replace(/__INDEX__/g, index);
                    tableBody.insertAdjacentHTML('beforeend', html);
                    if (specialDiscountEnable) {
                        specialDiscountEnable.checked = true;
                    }
                    updateSpecialOfferVisibility();
                });
            });

            document.addEventListener('click', (event) => {
                const removeButton = event.target.closest('.special-offer-remove');
                if (!removeButton) {
                    return;
                }
                const row = removeButton.closest('tr');
                const dayContainer = removeButton.closest('.special-offer-day');
                const tableBody = row?.parentElement;
                row?.remove();
                if (dayContainer && tableBody && !tableBody.querySelector('tr')) {
                    dayContainer.querySelector('.special-offer-empty')?.classList.remove('d-none');
                }
                updateSpecialOfferVisibility();
            });

            updateSpecialOfferVisibility();

            // Subscription Plan Selection and Razorpay Payment
            const subscriptionPlanSelect = document.getElementById('subscription_plan_select');
            const planDetailsContainer = document.getElementById('plan-details-container');
            const proceedToPaymentBtn = document.getElementById('proceed-to-payment-btn');
            const razorpayKey = '{{ $razorpayKey }}';
            const razorpayEnabled = {{ $razorpayEnabled ? 'true' : 'false' }};

            // Function to update subscription details display - ONLY show if payment is completed
            function updateSubscriptionDetailsDisplay(selectedOption) {
                // Always hide subscription details first
                document.getElementById('subscription_details_container').style.display = 'none';
                document.getElementById('plan_commission_details_container').style.display = 'none';
                document.getElementById('plan_details_info_container').style.display = 'none';
                document.getElementById('plan_payment_info_container').style.display = 'none';

                if (!selectedOption || !selectedOption.value) {
                    // No plan selected, keep everything hidden
                    return;
                }

                // Check if payment is completed for this plan
                const selectedPlanId = selectedOption.value;
                const vendorPlanId = '{{ ($vendor && $vendor->subscriptionPlanId) ? $vendor->subscriptionPlanId : "" }}';
                const billStatus = '{{ ($vendor && isset($vendor->bill_status)) ? $vendor->bill_status : "" }}';
                const isPaymentCompleted = (vendorPlanId === selectedPlanId && billStatus === 'paid');

                // Only show subscription details if payment is completed for this specific plan
                if (!isPaymentCompleted) {
                    // Payment not completed, keep details hidden
                    return;
                }

                // Payment is completed, show the details
                // Get plan type
                const planType = selectedOption.dataset.planType || (parseFloat(selectedOption.dataset.place || 0) > 0 ? 'commission' : 'subscription');
                const isCommissionPlan = planType === 'commission';
                const placeValue = parseFloat(selectedOption.dataset.place || 0);

                // Show subscription details container
                document.getElementById('subscription_details_container').style.display = 'block';
                document.getElementById('plan_commission_details_container').style.display = 'block';
                document.getElementById('plan_details_info_container').style.display = 'block';

                // Update subscription status
                document.getElementById('subscription_status_display').value = isCommissionPlan ? '{{ __('No') }}' : '{{ __('Yes') }}';
                document.getElementById('commission_type_display').value = isCommissionPlan ? '{{ __('Commission') }}' : '{{ __('Subscription') }}';

                // Show/hide and update commission percentage or subscription slab
                if (isCommissionPlan && placeValue > 0) {
                    // Show commission percentage
                    document.getElementById('commission_percentage_container').style.display = 'block';
                    document.getElementById('subscription_slab_container').style.display = 'none';
                    document.getElementById('commission_percentage_display').value = placeValue + '%';
                } else {
                    // Show subscription slab
                    document.getElementById('commission_percentage_container').style.display = 'none';
                    document.getElementById('subscription_slab_container').style.display = 'block';
                    const planPrice = parseFloat(selectedOption.dataset.price || 0);
                    document.getElementById('subscription_slab_display').value = '{{ $currencyMeta['symbol'] }}' + planPrice.toFixed(2);
                }

                // Update plan details
                document.getElementById('plan_name_display').value = selectedOption.dataset.name || '';
                document.getElementById('plan_type_display').value = isCommissionPlan ? '{{ __('Commission Plan') }}' : '{{ __('Subscription Plan') }}';

                // Show payment info since payment is completed
                document.getElementById('plan_payment_info_container').style.display = 'block';
                document.getElementById('payment_status_display').value = 'paid';

                const expiryDay = selectedOption.dataset.expiryDay || '-1';
                if (!isCommissionPlan && expiryDay !== '-1') {
                    document.getElementById('expiry_date_container').style.display = 'block';
                    // Get expiry date from vendor if available
                    const vendorExpiryDate = '{{ ($vendor && $vendor->subscriptionExpiryDate) ? $vendor->subscriptionExpiryDate : "" }}';
                    document.getElementById('expiry_date_display').value = vendorExpiryDate || 'N/A';
                } else {
                    document.getElementById('expiry_date_container').style.display = 'none';
                }
            }

            if (subscriptionPlanSelect) {
                // Initialize display on page load if plan is already selected and paid
                const initialSelectedOption = subscriptionPlanSelect.options[subscriptionPlanSelect.selectedIndex];
                if (initialSelectedOption && initialSelectedOption.value) {
                    // Show plan details card if plan is selected (for payment)
                    const planType = initialSelectedOption.dataset.planType || (parseFloat(initialSelectedOption.dataset.place || 0) > 0 ? 'commission' : 'subscription');
                    const isCommissionPlan = planType === 'commission';

                    document.getElementById('plan-name-display').textContent = initialSelectedOption.dataset.name || '';
                    document.getElementById('plan-price-display').textContent = '{{ $currencyMeta['symbol'] }}' + parseFloat(initialSelectedOption.dataset.price || 0).toFixed(2);
                    document.getElementById('plan-description-display').textContent = initialSelectedOption.dataset.description || 'N/A';
                    const expiryDay = initialSelectedOption.dataset.expiryDay || '-1';
                    document.getElementById('plan-expiry-display').textContent = expiryDay === '-1' ? 'Unlimited' : expiryDay + ' days';

                    const commissionContainer = document.getElementById('plan-commission-container');
                    if (isCommissionPlan && parseFloat(initialSelectedOption.dataset.place || 0) > 0) {
                        document.getElementById('plan-place-display').textContent = initialSelectedOption.dataset.place || '0';
                        commissionContainer.style.display = 'block';
                    } else {
                        commissionContainer.style.display = 'none';
                    }

                    document.getElementById('plan-type-display').textContent = isCommissionPlan ? '{{ __('Commission Plan') }}' : '{{ __('Subscription Plan') }}';

                    // Check if payment is completed
                    const vendorPlanId = '{{ ($vendor && $vendor->subscriptionPlanId) ? $vendor->subscriptionPlanId : "" }}';
                    const billStatus = '{{ ($vendor && isset($vendor->bill_status)) ? $vendor->bill_status : "" }}';
                    const isPaymentCompleted = (vendorPlanId === initialSelectedOption.value && billStatus === 'paid');

                    // Only show plan details card if payment is NOT completed (to allow payment)
                    // If payment is completed, hide the card and show subscription details instead
                    if (isPaymentCompleted) {
                        planDetailsContainer.style.display = 'none';
                        updateSubscriptionDetailsDisplay(initialSelectedOption);
                    } else {
                        planDetailsContainer.style.display = 'block';
                    }
                }

                subscriptionPlanSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];

                    // Check if payment is completed for this plan
                    const selectedPlanId = selectedOption ? selectedOption.value : '';
                    const vendorPlanId = '{{ ($vendor && $vendor->subscriptionPlanId) ? $vendor->subscriptionPlanId : "" }}';
                    const billStatus = '{{ ($vendor && isset($vendor->bill_status)) ? $vendor->bill_status : "" }}';
                    const isPaymentCompleted = (vendorPlanId === selectedPlanId && billStatus === 'paid');

                    if (selectedOption && selectedOption.value) {
                        // Show plan details
                        const planType = selectedOption.dataset.planType || (parseFloat(selectedOption.dataset.place || 0) > 0 ? 'commission' : 'subscription');
                        const isCommissionPlan = planType === 'commission';

                        document.getElementById('plan-name-display').textContent = selectedOption.dataset.name || '';
                        document.getElementById('plan-price-display').textContent = '{{ $currencyMeta['symbol'] }}' + parseFloat(selectedOption.dataset.price || 0).toFixed(2);
                        document.getElementById('plan-description-display').textContent = selectedOption.dataset.description || 'N/A';
                        const expiryDay = selectedOption.dataset.expiryDay || '-1';
                        document.getElementById('plan-expiry-display').textContent = expiryDay === '-1' ? 'Unlimited' : expiryDay + ' days';

                        // Show/hide commission percentage based on plan type
                        const commissionContainer = document.getElementById('plan-commission-container');
                        if (isCommissionPlan && parseFloat(selectedOption.dataset.place || 0) > 0) {
                            document.getElementById('plan-place-display').textContent = selectedOption.dataset.place || '0';
                            commissionContainer.style.display = 'block';
                        } else {
                            commissionContainer.style.display = 'none';
                        }

                        // Show plan type
                        document.getElementById('plan-type-display').textContent = isCommissionPlan ? '{{ __('Commission Plan') }}' : '{{ __('Subscription Plan') }}';

                        // Show plan details card ONLY if payment is NOT completed (to allow payment)
                        // If payment is completed, hide the card and show subscription details instead
                        if (isPaymentCompleted) {
                            planDetailsContainer.style.display = 'none';
                        } else {
                            planDetailsContainer.style.display = 'block';
                        }
                    } else {
                        planDetailsContainer.style.display = 'none';
                    }

                    // Update subscription details display (will only show if payment is completed)
                    updateSubscriptionDetailsDisplay(selectedOption);
                });
            }

            if (proceedToPaymentBtn) {
                proceedToPaymentBtn.addEventListener('click', function() {
                    const selectedPlanId = subscriptionPlanSelect.value;
                    if (!selectedPlanId) {
                        alert('{{ __('Please select a subscription plan first') }}');
                        return;
                    }

                    if (!razorpayEnabled || !razorpayKey) {
                        alert('{{ __('Razorpay payment is not configured. Please contact administrator.') }}');
                        return;
                    }

                    const selectedOption = subscriptionPlanSelect.options[subscriptionPlanSelect.selectedIndex];
                    const planPrice = parseFloat(selectedOption.dataset.price || 0);
                    const planName = selectedOption.dataset.name || '';

                    if (planPrice <= 0) {
                        alert('{{ __('This plan is free. Processing subscription...') }}');
                        // Handle free plan
                        processFreeSubscription(selectedPlanId, selectedOption);
                        return;
                    }

                    // Create Razorpay order
                    createRazorpayOrder(selectedPlanId, planPrice, planName, selectedOption);
                });
            }

            function createRazorpayOrder(planId, amount, planName, planOption) {
                // Show loading
                proceedToPaymentBtn.disabled = true;
                proceedToPaymentBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> {{ __('Creating order...') }}';

                // Create order on server first
                fetch('{{ route("restaurant.subscription.create-order") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        amount: amount
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message || '{{ __('Failed to create payment order. Please try again.') }}');
                            proceedToPaymentBtn.disabled = false;
                            proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                            return;
                        }

                        // If free plan, process directly
                        if (data.order_id === null || amount <= 0) {
                            processFreeSubscription(planId, planOption);
                            return;
                        }

                        // Convert amount to paise (Razorpay uses smallest currency unit)
                        const amountInPaise = Math.round(amount * 100);

                        const options = {
                            key: razorpayKey,
                            amount: amountInPaise,
                            currency: data.currency || 'INR',
                            order_id: data.order_id,
                            name: '{{ $vendor->title ?? "Restaurant" }}',
                            description: planName,
                            handler: function(response) {
                                // Payment successful
                                processPaymentSuccess(response, planId, planOption);
                            },
                            prefill: {
                                name: '{{ $user->firstName ?? "" }} {{ $user->lastName ?? "" }}',
                                email: '{{ $user->email ?? "" }}'@if(!empty($razorpayContact)),
                                contact: '{{ $razorpayContact }}'@endif
                            },
                            theme: {
                                color: '#3399cc'
                            },
                            modal: {
                                ondismiss: function() {
                                    console.log('Payment cancelled');
                                    proceedToPaymentBtn.disabled = false;
                                    proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                                }
                            }
                        };

                        const razorpay = new Razorpay(options);
                        razorpay.open();

                        // Reset button state
                        proceedToPaymentBtn.disabled = false;
                        proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('{{ __('An error occurred. Please try again.') }}');
                        proceedToPaymentBtn.disabled = false;
                        proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                    });
            }

            function processPaymentSuccess(paymentResponse, planId, planOption) {
                // Show loading
                proceedToPaymentBtn.disabled = true;
                proceedToPaymentBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> {{ __('Processing...') }}';

                // Send payment details to server
                fetch('{{ route("restaurant.subscription.payment") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        payment_id: paymentResponse.razorpay_payment_id,
                        order_id: paymentResponse.razorpay_order_id,
                        signature: paymentResponse.razorpay_signature,
                        plan_name: planOption.dataset.name,
                        plan_price: planOption.dataset.price,
                        plan_place: planOption.dataset.place,
                        expiry_day: planOption.dataset.expiryDay
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('{{ __('Payment successful! Subscription activated.') }}');
                            location.reload();
                        } else {
                            alert(data.message || '{{ __('Payment processing failed. Please try again.') }}');
                            proceedToPaymentBtn.disabled = false;
                            proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('{{ __('An error occurred. Please try again.') }}');
                        proceedToPaymentBtn.disabled = false;
                        proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                    });
            }

            function processFreeSubscription(planId, planOption) {
                proceedToPaymentBtn.disabled = true;
                proceedToPaymentBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> {{ __('Processing...') }}';

                fetch('{{ route("restaurant.subscription.payment") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        payment_id: null,
                        order_id: null,
                        signature: null,
                        plan_name: planOption.dataset.name,
                        plan_price: planOption.dataset.price,
                        plan_place: planOption.dataset.place,
                        expiry_day: planOption.dataset.expiryDay,
                        is_free: true
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('{{ __('Subscription activated successfully!') }}');
                            location.reload();
                        } else {
                            alert(data.message || '{{ __('Subscription activation failed. Please try again.') }}');
                            proceedToPaymentBtn.disabled = false;
                            proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('{{ __('An error occurred. Please try again.') }}');
                        proceedToPaymentBtn.disabled = false;
                        proceedToPaymentBtn.innerHTML = '<i class="fa fa-credit-card mr-2"></i> {{ __('Proceed to Payment') }}';
                    });
            }
        })();
    </script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endsection
