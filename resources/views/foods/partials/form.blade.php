@php
    $editing = isset($food);
    $selectedCategory = old('categoryID', $food->categoryID ?? '');
    $existingPhoto = $food->photo ?? null;
    $placeholderImage = $placeholderImage ?? asset('assets/images/placeholder.png');

    $oldAddOnTitles = old('add_ons_title', []);
    $oldAddOnPrices = old('add_ons_price', []);
    $addOnRows = [];

    if (!empty($oldAddOnTitles) || !empty($oldAddOnPrices)) {
        $count = max(count($oldAddOnTitles), count($oldAddOnPrices));
        for ($i = 0; $i < $count; $i++) {
            $title = $oldAddOnTitles[$i] ?? '';
            $price = $oldAddOnPrices[$i] ?? '';

            // Ensure both are strings
            $title = is_array($title) ? '' : (string) $title;
            $price = is_array($price) ? '' : (string) $price;

            $addOnRows[] = [
                'title' => $title,
                'price' => $price,
            ];
        }
    } elseif (!empty($addOns ?? [])) {
        // Ensure addOns from database are also strings
        $addOnRows = [];
        foreach ($addOns as $addOn) {
            $addOnRows[] = [
                'title' => is_array($addOn['title'] ?? null) ? '' : (string) ($addOn['title'] ?? ''),
                'price' => is_array($addOn['price'] ?? null) ? '' : (string) ($addOn['price'] ?? ''),
            ];
        }
    }

    if (empty($addOnRows)) {
        $addOnRows = [['title' => '', 'price' => '']];
    }

    $oldSpecLabels = old('specification_label', []);
    $oldSpecValues = old('specification_value', []);
    $specRows = [];

    if (!empty($oldSpecLabels) || !empty($oldSpecValues)) {
        $count = max(count($oldSpecLabels), count($oldSpecValues));
        for ($i = 0; $i < $count; $i++) {
            $label = $oldSpecLabels[$i] ?? '';
            $value = $oldSpecValues[$i] ?? '';

            // Ensure both are strings
            $label = is_array($label) ? '' : (string) $label;
            $value = is_array($value) ? '' : (string) $value;

            if ($label === '' && $value === '') {
                continue;
            }
            $specRows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }
    } elseif (!empty($specifications ?? [])) {
        foreach ($specifications as $label => $value) {
            // Ensure value is always a string (handle arrays and other types)
            $stringValue = '';
            if (is_array($value)) {
                // If it's an array, take the first value if it's a simple array, or empty string
                $stringValue = !empty($value) && is_string($value[0] ?? null) ? (string) $value[0] : '';
            } elseif (is_string($value) || is_numeric($value) || is_bool($value)) {
                $stringValue = (string) $value;
            }

            $specRows[] = ['label' => (string) $label, 'value' => $stringValue];
        }
    }

    $galleryTextarea = old('gallery_urls');

    // Prepare available_days and available_timings data
    $availableDays = old('available_days', []);
    $availableTimings = old('available_timings', []);

    if (empty($availableDays) && isset($food) && $food->available_days) {
        $availableDays = is_array($food->available_days) ? $food->available_days : json_decode($food->available_days, true) ?? [];
    }

    if (empty($availableTimings) && isset($food) && $food->available_timings) {
        $rawTimings = is_array($food->available_timings) ? $food->available_timings : json_decode($food->available_timings, true) ?? [];

        // Convert from old format to new format if needed (backward compatibility)
        if (!empty($rawTimings) && isset($rawTimings[0]['day'])) {
            // Already in new format
            $availableTimings = $rawTimings;
        } else {
            // Old format: { "Monday": ["09:00-12:00"] } -> Convert to new format
            $availableTimings = [];
            foreach ($rawTimings as $day => $slots) {
                if (is_array($slots)) {
                    $timeslot = [];
                    foreach ($slots as $slot) {
                        if (strpos($slot, '-') !== false) {
                            list($from, $to) = explode('-', $slot, 2);
                            $timeslot[] = ['from' => trim($from), 'to' => trim($to)];
                        }
                    }
                    if (!empty($timeslot)) {
                        $availableTimings[] = ['day' => $day, 'timeslot' => $timeslot];
                    }
                }
            }
        }
    }

    $availableDays = is_array($availableDays) ? $availableDays : [];
    $availableTimings = is_array($availableTimings) ? $availableTimings : [];

    // Create a helper array for easy day lookup
    $timingsByDay = [];
    foreach ($availableTimings as $timing) {
        if (isset($timing['day']) && isset($timing['timeslot'])) {
            $timingsByDay[$timing['day']] = $timing['timeslot'];
        }
    }

    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following issues:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    // Get subscription info for edit form
    $hasSubscription = $hasSubscription ?? false;
    $applyPercentage = $applyPercentage ?? 30;
    $planType = $planType ?? 'commission';
    $gstAgreed = $gstAgreed ?? false;
    $subscriptionPlacePercentage = $subscriptionPlacePercentage ?? 0; // Subscription plan's place percentage for settlement calculation (EDIT page only)
    $merchantPrice = $food->merchant_price ?? '';
    $onlinePrice = $food->price ?? ''; // Always use stored price value

    // Calculate price for display purposes only (shows what it would be if calculated)
    $priceCalculation = null;
    if ($merchantPrice && is_numeric($merchantPrice) && $merchantPrice > 0) {
        $priceCalculation = \App\Services\PricingCalculationService::calculatePrice(
            (float)$merchantPrice,
            $hasSubscription,
            $applyPercentage,
            $gstAgreed,
            $planType
        );
        // Don't overwrite $onlinePrice - keep stored value, calculation is just for display text
    }
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Food Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $food->name ?? '') }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Merchant Price <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="merchant_price" id="merchant_price" class="form-control"
                   value="{{ old('merchant_price', $merchantPrice) }}" required>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Online Price <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="online_price" id="online_price" class="form-control"
                   value="{{ old('online_price', $onlinePrice) }}" required>
            <small class="form-text text-muted">Auto-calculated from merchant price. You can edit this value
                manually.</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Discount Price</label>
            <input type="number" step="0.01" name="disPrice" id="disPrice" class="form-control"
                   value="{{ old('disPrice', $food->disPrice ?? '') }}">
            <small class="text-danger" id="discount-price-error" style="display: none;"></small>
        </div>
    </div>
</div>

<div class="row" id="price-calculation-section">
    <div class="col-md-12">
        <div class="alert alert-info" id="price-calculation-display">
            @if($priceCalculation)
                <strong>Pricing Calculation:</strong><br>
                <pre
                    style="white-space: pre-wrap; font-family: inherit; margin: 0;">{{ \App\Services\PricingCalculationService::getCalculationText($priceCalculation, '₹') }}</pre>
            @else
                <strong>Pricing Calculation:</strong><br>
                <pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">Enter merchant price to see calculation.</pre>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const hasSubscription = {{ $hasSubscription ? 'true' : 'false' }};
        const applyPercentage = {{ $applyPercentage }};
        const planType = '{{ $planType ?? 'commission' }}';
        const gstAgreed = {{ $gstAgreed ? 'true' : 'false' }};
        const gstPercentage = 5; // 5% GST
        const subscriptionPlacePercentage = {{ $subscriptionPlacePercentage ?? 0 }}; // Subscription plan's place percentage for settlement calculation (EDIT page only)
        const merchantPriceInput = document.getElementById('merchant_price');
        const onlinePriceInput = document.getElementById('online_price');
        const discountPriceInput = document.getElementById('disPrice');
        const errorMsgElement = document.getElementById('discount-price-error');
        const calculationDisplay = document.getElementById('price-calculation-display');

        // Track if online price was manually edited (persist across merchant price changes)
        let onlinePriceManuallyEdited = false;
        // Store the original online price value on page load to detect manual edits
        const originalOnlinePrice = {{ isset($onlinePrice) && $onlinePrice !== '' && is_numeric($onlinePrice) ? (float)$onlinePrice : 'null' }};

        // Helper function to check if online price is empty/zero
        function isOnlinePriceEmpty() {
            if (!onlinePriceInput) return true;
            const value = onlinePriceInput.value;
            // Check if value is empty string, null, undefined, or whitespace
            if (!value || value.trim() === '') return true;
            // Check if parsed value is 0 or NaN
            const parsed = parseFloat(value);
            return isNaN(parsed) || parsed === 0;
        }

        // Function to calculate online price based on merchant price, subscription, and GST
        // Only auto-calculates if online price is empty or 0 AND not manually edited (allows manual override)
        function calculateOnlinePrice(forceRecalculate = false) {
            if (merchantPriceInput && onlinePriceInput) {
                const merchantPriceNum = parseFloat(merchantPriceInput.value) || 0;
                const currentOnlinePrice = parseFloat(onlinePriceInput.value) || 0;

                // Only auto-calculate if:
                // 1. Online price is empty/0 AND not manually edited, OR
                // 2. forceRecalculate is true AND not manually edited
                const shouldCalculate = merchantPriceNum > 0 &&
                    !onlinePriceManuallyEdited &&
                    (isOnlinePriceEmpty() || forceRecalculate);

                if (shouldCalculate) {
                    let onlinePrice = 0;
                    const isCommissionBased = !hasSubscription || planType === 'commission';

                    if (isCommissionBased) {
                        // Scenario 1: Commission-Based Model
                        const commission = merchantPriceNum * (applyPercentage / 100);
                        const priceBeforeGst = merchantPriceNum + commission;

                        if (gstAgreed) {
                            // Case 1: Merchant AGREED for GST - Platform absorbs GST
                            onlinePrice = priceBeforeGst;
                        } else {
                            // Case 2: Merchant NOT AGREED for GST - GST is 5% of Merchant Price
                            const gstAmount = merchantPriceNum * (gstPercentage / 100);
                            onlinePrice = priceBeforeGst + gstAmount;
                        }
                    } else {
                        // Scenario 2: Subscription-Based Model (No Commission in online price)
                        if (gstAgreed) {
                            // Case 1: Merchant AGREED for GST - Platform absorbs GST
                            onlinePrice = merchantPriceNum;
                        } else {
                            // Case 2: Merchant NOT AGREED for GST - Add GST to customer price
                            onlinePrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                        }
                    }

                    onlinePriceInput.value = onlinePrice.toFixed(2);
                    updateCalculationDisplay(merchantPriceNum, onlinePrice);
                } else if (merchantPriceNum <= 0) {
                    // Clear online price if merchant price is 0 or invalid (only if it was auto-calculated)
                    if ((isOnlinePriceEmpty() || forceRecalculate) && !onlinePriceManuallyEdited) {
                        onlinePriceInput.value = '';
                    }
                    updateCalculationDisplay(0, currentOnlinePrice);
                } else {
                    // Merchant price exists and online price has manual value - just update calculation display
                    updateCalculationDisplay(merchantPriceNum, currentOnlinePrice);
                }
            }
        }

        // Function to update calculation display
        // Function to update calculation display
        function updateCalculationDisplay(merchantPrice, onlinePrice) {
            // Find or create calculation display
            let calcDisplay = document.getElementById('price-calculation-display');
            if (!calcDisplay) {
                // Find the price calculation section or create it
                let calcSection = document.querySelector('#price-calculation-section');
                if (!calcSection) {
                    // Create the section after the discount price row
                    const discountRow = document.querySelector('input[name="disPrice"]')?.closest('.row');
                    if (discountRow) {
                        const calcRow = document.createElement('div');
                        calcRow.className = 'row';
                        calcRow.id = 'price-calculation-section';
                        calcRow.innerHTML = '<div class="col-md-12"><div class="alert alert-info" id="price-calculation-display"></div></div>';
                        discountRow.parentNode.insertBefore(calcRow, discountRow.nextSibling);
                        calcDisplay = document.getElementById('price-calculation-display');
                    }
                } else {
                    calcDisplay = calcSection.querySelector('#price-calculation-display');
                }
            }

            if (calcDisplay && merchantPrice > 0) {
                // Build calculation text as plain text (for pre tag)
                const isCommissionBased = !hasSubscription || planType === 'commission';
                let calcTextPlain = 'Pricing Calculation:\n';
                calcTextPlain += 'Merchant Price: ₹' + merchantPrice.toFixed(2) + '\n';

                if (isCommissionBased) {
                    const commission = merchantPrice * (applyPercentage / 100);
                    const priceBeforeGst = merchantPrice + commission;
                    calcTextPlain += 'Subscription Type: Commission (' + applyPercentage + '%)\n';
                    calcTextPlain += 'Commission: ₹' + commission.toFixed(2) + ' (added to customer price)\n';

                    if (gstAgreed) {
                        calcTextPlain += 'GST Agreement: Yes\n';
                        calcTextPlain += 'Online Price: ₹' + onlinePrice.toFixed(2) + '\n';
                        calcTextPlain += 'GST (5%) absorbed by platform\n';
                        // Calculate settlement: merchant_price - GST ONLY (no commission deduction for settlement)
                        let settlementDeduction = merchantPrice * (gstPercentage / 100);
                        calcTextPlain += 'Final Settlement to Merchant: ₹' + (merchantPrice - settlementDeduction).toFixed(2);
                    } else {
                        calcTextPlain += 'GST Agreement: No\n';
                        const gstAmount = merchantPrice * (gstPercentage / 100);
                        calcTextPlain += 'GST (5%): +₹' + gstAmount.toFixed(2) + ' (added to customer price)\n';
                        calcTextPlain += 'Online Price: ₹' + onlinePrice.toFixed(2) + '\n';
                        // Calculate settlement: merchant_price FULL (no commission deduction for settlement)
                        calcTextPlain += 'Final Settlement to Merchant: ₹' + merchantPrice.toFixed(2);
                    }
                } else {
                    // Subscription-Based Model
                    calcTextPlain += 'Subscription Type: Subscription (No Commission)\n';

                    if (gstAgreed) {
                        calcTextPlain += 'GST Agreement: Yes\n';
                        calcTextPlain += 'Online Price: ₹' + onlinePrice.toFixed(2) + '\n';
                        calcTextPlain += 'GST (5%) absorbed by platform\n';
                        // Calculate settlement: merchant_price - GST - subscription_place_percentage
                        let settlementDeduction = merchantPrice * (gstPercentage / 100);
                        if (hasSubscription && subscriptionPlacePercentage > 0) {
                            settlementDeduction += merchantPrice * (subscriptionPlacePercentage / 100);
                            calcTextPlain += 'Subscription Plan Commission (' + subscriptionPlacePercentage + '%): -₹' + (merchantPrice * (subscriptionPlacePercentage / 100)).toFixed(2) + '\n';
                        }
                        calcTextPlain += 'Final Settlement to Merchant: ₹' + (merchantPrice - settlementDeduction).toFixed(2);
                    } else {
                        calcTextPlain += 'GST Agreement: No\n';
                        calcTextPlain += 'GST (5%): +₹' + (merchantPrice * (gstPercentage / 100)).toFixed(2) + ' (added to customer price)\n';
                        calcTextPlain += 'Online Price: ₹' + onlinePrice.toFixed(2) + '\n';
                        // Calculate settlement: merchant_price - subscription_place_percentage (no GST deduction for settlement when GST not agreed)
                        let settlementAmount = merchantPrice;
                        if (hasSubscription && subscriptionPlacePercentage > 0) {
                            settlementAmount = merchantPrice - (merchantPrice * (subscriptionPlacePercentage / 100));
                            calcTextPlain += 'Subscription Plan Commission (' + subscriptionPlacePercentage + '%): -₹' + (merchantPrice * (subscriptionPlacePercentage / 100)).toFixed(2) + '\n';
                        }
                        calcTextPlain += 'Final Settlement to Merchant: ₹' + settlementAmount.toFixed(2);
                    }
                }

                // Update the display - replace pre tag if it exists, otherwise update innerHTML
                const preTag = calcDisplay.querySelector('pre');
                if (preTag) {
                    // Replace the pre tag content with formatted text
                    preTag.textContent = calcTextPlain;
                } else {
                    // If no pre tag, update innerHTML with HTML formatted text
                    calcDisplay.innerHTML = '<strong>Pricing Calculation:</strong><br>' + calcTextPlain.replace(/\n/g, '<br>');
                }
            } else if (calcDisplay && merchantPrice <= 0) {
                // Show placeholder when no merchant price
                const preTag = calcDisplay.querySelector('pre');
                if (preTag) {
                    preTag.textContent = 'Pricing Calculation:\nEnter merchant price to see calculation.';
                } else {
                    calcDisplay.innerHTML = '<strong>Pricing Calculation:</strong><br>Enter merchant price to see calculation.';
                }
            }
        }

        // Function to validate discount price
        function validateDiscountPrice() {
            if (discountPriceInput && onlinePriceInput) {
                const discountPriceNum = parseFloat(discountPriceInput.value) || 0;
                const onlinePriceNum = parseFloat(onlinePriceInput.value) || 0;

                if (discountPriceNum > 0 && onlinePriceNum > 0 && discountPriceNum > onlinePriceNum) {
                    // Get product name from the name input field
                    const productNameInput = document.querySelector('input[name="name"]');
                    const productName = productNameInput && productNameInput.value ? productNameInput.value.trim() : 'this product';

                    const errorMsg = `Discount price cannot be greater than online price for product "${productName}".`;
                    if (errorMsgElement) {
                        errorMsgElement.textContent = errorMsg;
                        errorMsgElement.style.display = 'block';
                    }
                    discountPriceInput.style.borderColor = '#dc3545';
                    discountPriceInput.value = '';
                    return false;
                } else {
                    if (errorMsgElement) {
                        errorMsgElement.style.display = 'none';
                    }
                    discountPriceInput.style.borderColor = '';
                    return true;
                }
            }
            return true;
        }

        // Calculate online price when merchant price changes (only if online price is empty/0 AND not manually edited)
        if (merchantPriceInput) {
            merchantPriceInput.addEventListener('input', function () {
                // Only auto-calculate if online price was not manually edited
                if (!onlinePriceManuallyEdited) {
                    calculateOnlinePrice(false); // Don't force, respect manual values
                } else {
                    // Just update calculation display with current values
                    const merchantPriceNum = parseFloat(merchantPriceInput.value || 0);
                    const onlinePriceNum = parseFloat(onlinePriceInput?.value || 0);
                    if (merchantPriceNum > 0) {
                        updateCalculationDisplay(merchantPriceNum, onlinePriceNum);
                    }
                }
                if (discountPriceInput) {
                    validateDiscountPrice();
                }
            });
            merchantPriceInput.addEventListener('change', function () {
                // Only auto-calculate if online price was not manually edited
                if (!onlinePriceManuallyEdited) {
                    calculateOnlinePrice(false); // Don't force, respect manual values
                } else {
                    // Just update calculation display with current values
                    const merchantPriceNum = parseFloat(merchantPriceInput.value || 0);
                    const onlinePriceNum = parseFloat(onlinePriceInput?.value || 0);
                    if (merchantPriceNum > 0) {
                        updateCalculationDisplay(merchantPriceNum, onlinePriceNum);
                    }
                }
                if (discountPriceInput) {
                    validateDiscountPrice();
                }
            });
        }

        // Track if online price was manually edited
        if (onlinePriceInput) {
            // Detect if user manually edits online_price (different from original or auto-calculated value)
            onlinePriceInput.addEventListener('input', function () {
                const currentValue = parseFloat(onlinePriceInput.value || 0);
                const merchantPriceNum = parseFloat(merchantPriceInput?.value || 0);

                // Mark as manually edited if:
                // 1. User is typing/changing the value, OR
                // 2. The value differs from what would be auto-calculated
                if (merchantPriceNum > 0) {
                    // Calculate what the auto-calculated value would be
                    let calculatedPrice = 0;
                    const isCommissionBased = !hasSubscription || planType === 'commission';

                    if (isCommissionBased) {
                        const commission = merchantPriceNum * (applyPercentage / 100);
                        const priceBeforeGst = merchantPriceNum + commission;
                        if (gstAgreed) {
                            calculatedPrice = priceBeforeGst;
                        } else {
                            const gstAmount = merchantPriceNum * (gstPercentage / 100);
                            calculatedPrice = priceBeforeGst + gstAmount;
                        }
                    } else {
                        if (gstAgreed) {
                            calculatedPrice = merchantPriceNum;
                        } else {
                            calculatedPrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                        }
                    }

                    // If current value differs from calculated value, mark as manually edited
                    if (Math.abs(currentValue - calculatedPrice) > 0.01) {
                        onlinePriceManuallyEdited = true;
                    }
                } else {
                    // If merchant price is 0 but online price has value, it's manually edited
                    if (currentValue > 0) {
                        onlinePriceManuallyEdited = true;
                    }
                }

                // Update calculation display when manually edited
                if (merchantPriceNum > 0) {
                    updateCalculationDisplay(merchantPriceNum, currentValue);
                }
                if (discountPriceInput) {
                    validateDiscountPrice();
                }
            });

            // Also track on blur to catch paste events
            onlinePriceInput.addEventListener('blur', function () {
                const currentValue = parseFloat(onlinePriceInput.value || 0);
                const merchantPriceNum = parseFloat(merchantPriceInput?.value || 0);

                if (merchantPriceNum > 0 && currentValue > 0) {
                    // Calculate what the auto-calculated value would be
                    let calculatedPrice = 0;
                    const isCommissionBased = !hasSubscription || planType === 'commission';

                    if (isCommissionBased) {
                        const commission = merchantPriceNum * (applyPercentage / 100);
                        const priceBeforeGst = merchantPriceNum + commission;
                        if (gstAgreed) {
                            calculatedPrice = priceBeforeGst;
                        } else {
                            const gstAmount = merchantPriceNum * (gstPercentage / 100);
                            calculatedPrice = priceBeforeGst + gstAmount;
                        }
                    } else {
                        if (gstAgreed) {
                            calculatedPrice = merchantPriceNum;
                        } else {
                            calculatedPrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                        }
                    }

                    // If current value differs from calculated value, mark as manually edited
                    if (Math.abs(currentValue - calculatedPrice) > 0.01) {
                        onlinePriceManuallyEdited = true;
                    }
                }
            });
        }

        // Initial calculation if merchant price exists and online price is empty/0
        // This ensures auto-calculation works in edit mode just like create mode
        if (merchantPriceInput && merchantPriceInput.value) {
            const merchantPriceNum = parseFloat(merchantPriceInput.value) || 0;
            if (merchantPriceNum > 0 && isOnlinePriceEmpty()) {
                // Auto-calculate if merchant price exists and online price is empty/0 (same as create mode)
                calculateOnlinePrice(true); // Force calculation on initial load if empty
            } else if (merchantPriceNum > 0 && !isOnlinePriceEmpty()) {
                // Online price has a value - check if it matches auto-calculated value
                const currentOnlinePrice = parseFloat(onlinePriceInput?.value || 0);
                // Calculate what the auto-calculated value would be
                let calculatedPrice = 0;
                const isCommissionBased = !hasSubscription || planType === 'commission';

                if (isCommissionBased) {
                    const commission = merchantPriceNum * (applyPercentage / 100);
                    const priceBeforeGst = merchantPriceNum + commission;
                    if (gstAgreed) {
                        calculatedPrice = priceBeforeGst;
                    } else {
                        const gstAmount = merchantPriceNum * (gstPercentage / 100);
                        calculatedPrice = priceBeforeGst + gstAmount;
                    }
                } else {
                    if (gstAgreed) {
                        calculatedPrice = merchantPriceNum;
                    } else {
                        calculatedPrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                    }
                }

                // If stored value differs from calculated value, it was manually edited
                if (Math.abs(currentOnlinePrice - calculatedPrice) > 0.01) {
                    onlinePriceManuallyEdited = true;
                }

                // Just update calculation display with existing values
                updateCalculationDisplay(merchantPriceNum, currentOnlinePrice);
            }
        }

        // Validate discount price when it changes
        if (discountPriceInput) {
            discountPriceInput.addEventListener('blur', function () {
                if (!validateDiscountPrice()) {
                    // Show alert if validation fails
                    const productNameInput = document.querySelector('input[name="name"]');
                    const productName = productNameInput && productNameInput.value ? productNameInput.value.trim() : 'this product';
                    alert(`Discount price cannot be greater than online price for product "${productName}".`);
                }
            });
            discountPriceInput.addEventListener('change', function () {
                if (!validateDiscountPrice()) {
                    // Show alert if validation fails
                    const productNameInput = document.querySelector('input[name="name"]');
                    const productName = productNameInput && productNameInput.value ? productNameInput.value.trim() : 'this product';
                    alert(`Discount price cannot be greater than online price for product "${productName}".`);
                }
            });
        }

        // Validate discount price when online price changes manually
        if (onlinePriceInput) {
            onlinePriceInput.addEventListener('change', function () {
                if (discountPriceInput) {
                    if (!validateDiscountPrice()) {
                        // Show alert if validation fails
                        const productNameInput = document.querySelector('input[name="name"]');
                        const productName = productNameInput && productNameInput.value ? productNameInput.value.trim() : 'this product';
                        alert(`Discount price cannot be greater than online price for product "${productName}".`);
                    }
                }
            });
        }

        // Validate before form submission
        const foodForm = document.querySelector('form[method="POST"]');
        if (foodForm) {
            foodForm.addEventListener('submit', function (e) {
                if (discountPriceInput && onlinePriceInput) {
                    if (!validateDiscountPrice()) {
                        e.preventDefault();
                        const productNameInput = document.querySelector('input[name="name"]');
                        const productName = productNameInput && productNameInput.value ? productNameInput.value.trim() : 'this product';
                        alert(`Discount price cannot be greater than online price for product "${productName}".`);
                        return false;
                    }
                }
            });
        }
    });
</script>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">
                {{ trans('lang.food_category_id') }} <span class="text-danger">*</span>
            </label>

            <!-- Selected categories display -->
            <div id="selected_categories" class="mb-2"></div>

            <!-- Search box -->
            <input type="text"
                   id="food_category_search"
                   class="form-control mb-2"
                   placeholder="Search categories...">

            <!-- Multi-select -->
            <select id="food_category"
                    {{--                        name="food_category[]"--}}
                    name="categoryID"
                    class="form-control"
                    multiple
                    required>
                <option value="">Select categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ $selectedCategory === $category->id ? 'selected' : '' }}>
                        {{ $category->title }}
                    </option>
                @endforeach
            </select>
            <small class="form-text text-muted">
                {{ trans('lang.food_category_id_help') }}
            </small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Quantity</label>
            <input type="number" name="quantity" class="form-control"
                   value="{{ old('quantity', $food->quantity ?? -1) }}">
            <small class="form-text text-muted">Use -1 for unlimited</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Status</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="publish"
                       name="publish" {{ old('publish', $food->publish ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="publish">Published</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="isAvailable"
                       name="isAvailable" {{ old('isAvailable', $food->isAvailable ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="isAvailable">Available</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="nonveg"
                       name="nonveg" {{ old('nonveg', $food->nonveg ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="nonveg">Non-Veg</label>
            </div>
        </div>
    </div>
</div>

<hr>
<div class="mt-4 border border 4px solid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <label class="btn btn-primary mb-0">
            <i class="fa fa-calendar mr-1"></i> Available Days & Timings
        </label>
        <button type="button" id="toggle-availability" class="btn btn-sm btn-outline-primary">
            <i class="fa fa-clock mr-1"></i> Manage Schedule
        </button>
    </div>
    <small class="form-text text-muted mb-3">Set specific days and time slots when this product is available.</small>

    <div id="availability-section" style="display: none;">
        <div class="mb-3">
            <label class="control-label font-weight-bold">Select Available Days:</label>
            <div class="mb-2">
                <div class="form-check">
                    <input type="checkbox"
                           id="select_all_days"
                           class="form-check-input">
                    <label class="form-check-label font-weight-bold"
                           for="select_all_days">
                        Select All Days
                    </label>
                </div>
            </div>
            <div class="row mt-2">
                @foreach ($daysOfWeek as $day)
                    <div class="col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input day-checkbox" type="checkbox"
                                   id="day_{{ $day }}"
                                   name="available_days[]"
                                   value="{{ $day }}"
                                   {{ in_array($day, $availableDays) ? 'checked' : '' }}
                                   data-day="{{ $day }}">
                            <label class="form-check-label" for="day_{{ $day }}">
                                {{ $day }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div id="timings-container" class="mt-3">
            @foreach ($daysOfWeek as $day)
                @php
                    $daySlots = $timingsByDay[$day] ?? [];
                    $daySlots = is_array($daySlots) ? $daySlots : [];
                    if (empty($daySlots) && in_array($day, $availableDays)) {
                        $daySlots = [['from' => '', 'to' => '']];
                    }
                @endphp
                <div class="day-timings-group mb-3" data-day="{{ $day }}"
                     style="display: {{ in_array($day, $availableDays) ? 'block' : 'none' }};">
                    <label class="control-label font-weight-bold">{{ $day }} Time Slots:</label>
                    <div class="timings-list mt-2" data-day="{{ $day }}">
                        @forelse ($daySlots as $index => $slot)
                            <div class="row align-items-end mb-2 timing-row" data-day="{{ $day }}"
                                 data-index="{{ $index }}">
                                <div class="col-md-4">
                                    <label class="form-label small">From</label>
                                    <input type="time"
                                           name="available_timings[{{ $day }}][{{ $index }}][from]"
                                           class="form-control form-control-sm time-slot-from"
                                           value="{{ $slot['from'] ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">To</label>
                                    <input type="time"
                                           name="available_timings[{{ $day }}][{{ $index }}][to]"
                                           class="form-control form-control-sm time-slot-to"
                                           value="{{ $slot['to'] ?? '' }}">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-time-slot w-100"
                                            title="Remove time slot">
                                        <i class="fa fa-times mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        @empty
                            @if (in_array($day, $availableDays))
                                <div class="row align-items-end mb-2 timing-row" data-day="{{ $day }}" data-index="0">
                                    <div class="col-md-4">
                                        <label class="form-label small">From</label>
                                        <input type="time"
                                               name="available_timings[{{ $day }}][0][from]"
                                               class="form-control form-control-sm time-slot-from">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">To</label>
                                        <input type="time"
                                               name="available_timings[{{ $day }}][0][to]"
                                               class="form-control form-control-sm time-slot-to">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger remove-time-slot w-100"
                                                title="Remove time slot">
                                            <i class="fa fa-times mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endforelse
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary add-time-slot" data-day="{{ $day }}">
                        <i class="fa fa-plus mr-1"></i> Add Time Slot
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <div id="availability-summary" class="mt-3">
        @if (!empty($availableDays))
            <div class="alert alert-info">
                <strong>Current Schedule:</strong><br>
                @foreach ($availableDays as $day)
                    @php
                        $daySlots = $timingsByDay[$day] ?? [];
                        $daySlots = is_array($daySlots) ? $daySlots : [];
                    @endphp
                    <div class="mt-1">
                        <strong>{{ $day }}:</strong>
                        @if (!empty($daySlots))
                            @foreach ($daySlots as $slot)
                                {{ ($slot['from'] ?? '') . ' – ' . ($slot['to'] ?? '') }}@if(!$loop->last)
                                    ,
                                @endif
                            @endforeach
                        @else
                            <span class="text-muted">No time slots set</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-secondary">
                No availability schedule set. Click "Manage Schedule" to configure.
            </div>
        @endif
    </div>
</div>

<div class="form-group">
    <div class="col-md-6">
        <label class="control-label font-weight-bold">Description <span class="text-danger">*</span></label>
        <textarea name="description" rows="4" class="form-control"
                  required>{{ old('description', $food->description ?? '') }}</textarea>
    </div>
</div>

<div class="mt-4 border border 4px solid p-4">
    <div class="col-md-4">
        <div class="form-group">
            <label class="control-label font-weight-bold">Image</label>
            <input type="file" name="photo_upload" class="form-control-file"
                   accept="image/jpeg,image/jpg,image/webp,image/png,image/jfif,image/avif">
            <small class="form-text text-muted">Recommended size 800x600px. Only JPG, JPEG, WEBP, PNG, JFIF, and AVIF
                formats allowed.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Or Photo URL</label>
            <input type="url" name="photo_url" class="form-control" value="{{ old('photo_url') }}">
            <small class="form-text text-muted">Paste a direct image URL if you host images elsewhere.</small>
        </div>
    </div>
</div>

<div class="mt-4 border border 4px solid p-4">
    <label class="control-label font-weight-bold d-block">Current Image</label>
    <img src="{{ $existingPhoto ?: $placeholderImage }}" alt="Current photo" class="rounded shadow"
         style="width: 160px; height: 120px; object-fit: cover;">
    @if ($editing && $existingPhoto)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo">
            <label class="form-check-label" for="remove_photo">Remove photo</label>
        </div>
    @else
        <small class="form-text text-muted">Placeholder image will be used until you upload your own photo.</small>
    @endif
</div>


<div class="row" style="display: none">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Gallery Uploads</label>
            <input type="file" name="gallery_uploads[]" class="form-control-file" multiple
                   accept="image/jpeg,image/jpg,image/webp">
            <small class="form-text text-muted">You can upload multiple images at once. Only JPG, JPEG, and WEBP formats
                allowed.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Gallery URLs (one per line)</label>
            <textarea name="gallery_urls" rows="4" class="form-control">{{ $galleryTextarea }}</textarea>
        </div>
    </div>
</div>

<hr>
<div class="mt-4 border border 4px solid p-4">
    <div class="d-flex justify-content-between align-items-center">
        <label class="btn btn-primary mb-3">Add-ons</label>
        <button type="button" class="btn btn-sm btn-outline-primary" data-addons-add>Add new</button>
    </div>
    <small class="form-text text-muted mb-2">Define optional add-ons for this food item.</small>
    <div data-addons-container>
        @foreach ($addOnRows as $row)
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-7">
                        <input type="text" name="add_ons_title[]" class="form-control" placeholder="Title"
                               value="{{ $row['title'] }}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" step="0.01" name="add_ons_price[]" class="form-control" placeholder="Price"
                               value="{{ $row['price'] }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<hr>
<div class="mt-4 border border 4px solid p-4">
    <div class="d-flex justify-content-between align-items-center">
        <label class="btn btn-primary mb-3">Product Specifications</label>
        <button type="button" class="btn btn-sm btn-outline-primary" data-specs-add>Add new</button>
    </div>
    <small class="form-text text-muted mb-2">Use specifications to highlight key product details (e.g., spicy level,
        calories).</small>
    <div data-specs-container>
        @forelse ($specRows as $row)
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-6">
                        <input type="text" name="specification_label[]" class="form-control" placeholder="Label"
                               value="{{ $row['label'] }}">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="specification_value[]" class="form-control" placeholder="Value"
                               value="{{ $row['value'] }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-6">
                        <input type="text" name="specification_label[]" class="form-control" placeholder="Label">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="specification_value[]" class="form-control" placeholder="Value">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
{{-- ✅ EDITABLE PRODUCT OPTIONS --}}
@if($editing && !empty($masterOptions))
    <hr>
    <div class="mt-4 border p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="btn btn-primary mb-0">
                <i class="fa fa-list mr-1"></i> Product Options
            </label>

            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    onclick="openEditOptionsModal()">
                Manage Options
            </button>
        </div>

        <small class="form-text text-muted mb-2">
            Select applicable options for this food item.
        </small>

        {{-- Hidden field that actually gets saved --}}
        + <input type="hidden" name="options_json" id="options_json"
                 value='@json($food->options ?? [])'>

        {{-- Preview selected options --}}
        <div id="options-preview">
            @if(!empty($food->options))
                <ul class="mb-0">
                    @foreach($food->options as $opt)
                        <li>
                            <strong>{{ $opt['title'] ?? '' }}</strong>
                            {{ !empty($opt['subtitle']) ? ' – '.$opt['subtitle'] : '' }}

                            @if(isset($opt['original_price']) || isset($opt['price']))
                                (
                                @if(isset($opt['original_price']))
                                    Original: ₹{{ $opt['original_price'] }}
                                @endif

                                @if(isset($opt['original_price']) && isset($opt['price']))
                                    |
                                @endif

                                @if(isset($opt['price']))
                                    Price: ₹{{ $opt['price'] }}
                                @endif
                                )
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <span class="text-muted">No options selected</span>
            @endif
        </div>
    </div>
@endif



<div class="text-center mt-4">
    <button type="submit" class="btn btn-primary px-5">
        <i class="fa fa-save mr-1"></i> {{ $editing ? 'Update Food' : 'Create Food' }}
    </button>
    <a href="{{ route('foods') }}" class="btn btn-secondary mx-2">
        <i class="fa fa-undo mr-1"></i> Cancel
    </a>
</div>

<template id="add-on-template">
    <div class="repeatable-row border rounded p-3 mb-2">
        <div class="form-row">
            <div class="col-md-7">
                <input type="text" name="add_ons_title[]" class="form-control" placeholder="Title">
            </div>
            <div class="col-md-4">
                <input type="number" step="0.01" name="add_ons_price[]" class="form-control" placeholder="Price">
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
            </div>
        </div>
    </div>
</template>

<template id="spec-template">
    <div class="repeatable-row border rounded p-3 mb-2">
        <div class="form-row">
            <div class="col-md-6">
                <input type="text" name="specification_label[]" class="form-control" placeholder="Label">
            </div>
            <div class="col-md-5">
                <input type="text" name="specification_value[]" class="form-control" placeholder="Value">
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
            </div>
        </div>
    </div>
</template>
