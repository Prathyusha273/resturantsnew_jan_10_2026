@php
    $user = $layoutUser ?? auth()->user();
    $vendor = $layoutVendor ?? null;
    $documentRequired = $layoutDocumentVerificationRequired ?? false;
    $dineInEnabled = $layoutDineInEnabled ?? false;
    $isDocumentVerified = (bool) ($user->isDocumentVerify ?? false);
    $canManageRestaurant = ! $documentRequired || $isDocumentVerified;
    $hasVendor = ! empty($vendor);
@endphp

<nav class="sidebar-nav">
    <ul id="sidebarnav" style="color: #222; font-weight: 600;">
        <li>
            <a class="waves-effect waves-dark" href="{{ route('home') }}">
                <i class="mdi mdi-home"></i>
                <span class="hide-menu">{{ trans('lang.dashboard') }}</span>
            </a>
        </li>

        @if($documentRequired)
            <li>
                <a class="waves-effect waves-dark" href="{{ route('vendors.document') }}">
                    <i class="mdi mdi-file-document"></i>
                    <span class="hide-menu">{{ trans('lang.document_plural') }}</span>
                </a>
            </li>
        @endif

        @if($canManageRestaurant)
            <li>
                <a class="waves-effect waves-dark" href="{{ route('restaurant') }}">
                    <i class="mdi mdi-store"></i>
                    <span class="hide-menu">{{ trans('lang.myrestaurant_plural') }}</span>
                </a>
            </li>
        @endif

        @if($canManageRestaurant && $hasVendor)
            <li>
                <a class="waves-effect waves-dark" href="{{ route('foods') }}">
                    <i class="mdi mdi-food"></i>
                    <span class="hide-menu">{{ trans('lang.food_plural') }}</span>
                </a>
            </li>

            <li>
                <a class="has-arrow waves-effect waves-dark" href="#" data-toggle="collapse" data-target="#orderDropdown">
                    <i class="mdi mdi-reorder-horizontal"></i>
                    <span class="hide-menu">{{ trans('lang.order_plural') }}</span>
                </a>
                <ul id="orderDropdown" class="collapse">
                    <li><a href="{{ route('orders') }}">{{ trans('lang.order_plural') }}</a></li>
                    <li><a href="{{ route('placedOrders') }}">{{ trans('lang.placed_orders') }}</a></li>
                    <li><a href="{{ route('acceptedOrders') }}">{{ trans('lang.accepted_orders') }}</a></li>
                    <li><a href="{{ route('rejectedOrders') }}">{{ trans('lang.rejected_orders') }}</a></li>
                </ul>
            </li>

            @if($dineInEnabled)
                <li>
                    <a class="waves-effect waves-dark" href="{{ route('booktable') }}">
                        <i class="fa fa-table"></i>
                        <span class="hide-menu">{{ trans('lang.book_table') }} / DINE IN</span>
                    </a>
                </li>
            @endif

            <li>
                <a class="waves-effect waves-dark" href="{{ route('coupons') }}">
                    <i class="mdi mdi-sale"></i>
                    <span class="hide-menu">{{ trans('lang.coupon_plural') }}</span>
                </a>
            </li>

            <li>
                <a class="waves-effect waves-dark" href="{{ route('payments') }}">
                    <i class="mdi mdi-wallet"></i>
                    <span class="hide-menu">{{ trans('lang.payment_plural') }}</span>
                </a>
            </li>

            <li>
                <a class="waves-effect waves-dark" href="{{ route('withdraw-method') }}">
                    <i class="fa fa-credit-card"></i>
                    <span class="hide-menu">{{ trans('lang.withdrawal_method') }}</span>
                </a>
            </li>

            <li>
                <a class="waves-effect waves-dark" href="{{ route('wallettransaction.index') }}">
                    <i class="mdi mdi-swap-horizontal"></i>
                    <span class="hide-menu">{{ trans('lang.wallet_transaction_plural') }}</span>
                </a>
            </li>
        @endif
    </ul>

    <p class="web_version text-center mt-4 text-muted small">
        {{ config('app.name') }} · {{ __('Powered by JIPPYMART') }}
    </p>
</nav>

