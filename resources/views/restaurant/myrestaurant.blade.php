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
        $workingHours = collect($vendorData->workingHours ?? [])
            ->mapWithKeys(fn ($item) => [$item['day'] => $item['timeslot'] ?? []])
            ->all();
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
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ trans('lang.dashboard') }}</a>
                    </li>
                    <li class="breadcrumb-item active">{{ trans('lang.myrestaurant_plural') }}</li>
                </ol>
            </div>
        </div>

        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success mb-4">{{ session('success') }}</div>
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
                                               required>
                                        <small
                                            class="form-text text-muted">{{ __('Auto-generated from restaurant name, but you can edit it.') }}</small>
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
                                                    value="{{ $cuisine->id }}" {{ $selectedCuisine === $cuisine->id ? 'selected' : '' }}>
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
                                <div class="col-md-6">
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
                            </div>

                            <div class="row">
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
                                    {{--<div class="d-flex justify-content-center gap-2">--}}
{{--                                        <button type="button" class="btn btn-outline-danger btn-sm"--}}
{{--                                                id="remove-photo-btn">--}}
{{--                                            <i class="fa fa-times"></i> {{ __('Remove Photo') }}--}}
{{--                                        </button>--}}
{{--                                    </div>--}}
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
                            <a href="{{ route('dashboard') }}" class="btn btn-default">
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
                nameInput.addEventListener('input', () => {
                    if (slugInput.dataset.manual === 'true') {
                        return;
                    }
                    slugInput.value = slugify(nameInput.value);
                });
                slugInput.addEventListener('input', () => {
                    slugInput.dataset.manual = 'true';
                });
            }

            if (zoneSelect && zoneSlug) {
                const updateZoneSlug = () => {
                    const option = zoneSelect.options[zoneSelect.selectedIndex];
                    if (option && option.value) {
                        zoneSlug.value = slugify(option.text);
                    }
                };
                zoneSelect.addEventListener('change', updateZoneSlug);
                updateZoneSlug();
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
        })();
    </script>
@endsection
