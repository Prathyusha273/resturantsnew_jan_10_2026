<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor;
use App\Models\Currency;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Get current vendor with caching (5 minutes)
     */
    protected function getCachedVendor(): Vendor
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'User not authenticated.');
        }

        $cacheKey = 'vendor_' . $user->id . '_' . ($user->vendorID ?? 'none');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            if ($user->vendorID) {
                $vendor = Vendor::select(['id', 'title', 'author'])->where('id', $user->vendorID)->first();
                if ($vendor) {
                    return $vendor;
                }
            }

            $vendor = Vendor::select(['id', 'title', 'author'])->where('author', $user->firebase_id ?? $user->id)->first();
            if (!$vendor) {
                abort(403, 'Vendor profile not found.');
            }

            return $vendor;
        });
    }

    /**
     * Get active currency with caching (5 minutes)
     */
    protected function getCachedCurrency(): array
    {
        return Cache::remember('active_currency_meta', 300, function () {
            $currency = Currency::where('isActive', true)->first();

            return [
                'symbol' => $currency->symbol ?? '₹',
                'symbol_at_right' => (bool) ($currency->symbolAtRight ?? false),
                'decimal_digits' => $currency->decimal_degits ?? 2,
            ];
        });
    }

    /**
     * Clear vendor cache for current user
     */
    protected function clearVendorCache(): void
    {
        $user = Auth::user();
        if ($user) {
            Cache::forget('vendor_' . $user->id . '_' . ($user->vendorID ?? 'none'));
        }
    }
}
