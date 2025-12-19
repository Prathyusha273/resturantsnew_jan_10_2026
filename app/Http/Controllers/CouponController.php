<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    private array $currency;

    public function __construct()
    {
        $this->middleware('auth');
        $this->currency = $this->activeCurrency();
    }

    public function index()
    {
        $vendor = $this->currentVendor();

        return view('coupons.index', [
            'vendor' => $vendor,
            'currency' => $this->currency,
        ]);
    }

    public function data(Request $request)
    {
        $vendor = $this->currentVendor();

        $baseQuery = Coupon::where('cType', 'restaurant')
            ->where(function ($query) use ($vendor) {
                $query->where('resturant_id', $vendor->id)
                    ->orWhere('resturant_id', 'ALL');
            });

        $recordsTotal = (clone $baseQuery)->count();

        $searchValue = trim((string) $request->input('search.value'));
        if ($searchValue !== '') {
            $baseQuery->where(function ($query) use ($searchValue) {
                $query->where('code', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('discount', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        $orderableColumns = [
            'code',
            'discount',
            'description',
            'expiresAt',
            'isEnabled',
            'isPublic',
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 1) - 1;
        $orderColumn = $orderableColumns[$orderColumnIndex] ?? 'expiresAt';
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $pageLength = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);

        $coupons = $baseQuery
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($pageLength)
            ->get();

        $data = $coupons->map(function (Coupon $coupon) {
            return [
                $this->renderCheckbox($coupon),
                e($coupon->code),
                $this->formatDiscount($coupon),
                e($coupon->description ?? ''),
                $this->formatDate($coupon->expiresAt),
                $this->renderToggle($coupon, 'isEnabled', $coupon->isEnabled ? 'success' : 'secondary'),
                $this->renderToggle($coupon, 'isPublic', $coupon->isPublic ? 'success' : 'danger', $coupon->isPublic ? __('Public') : __('Private')),
                $this->renderActions($coupon),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values()->all(),
        ]);
    }

    public function create()
    {
        return view('coupons.create', [
            'coupon' => new Coupon([
                'isPublic' => true,
                'isEnabled' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->currentVendor();
        $data = $this->validateCoupon($request);

        $coupon = new Coupon();
        $coupon->id = Str::random(12);
        $this->fillCoupon($coupon, $data, $request, $vendor);

        // Clear relevant caches
        \Illuminate\Support\Facades\Cache::forget('dashboard_' . $vendor->id);

        return redirect()->route('coupons')->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        $vendor = $this->currentVendor();

//        if ($coupon->resturant_id !== $vendor->id) {
//            abort(403);
//        }

        return view('coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $vendor = $this->currentVendor();

//        if ($coupon->resturant_id !== $vendor->id) {
//            abort(403);
//        }

        $data = $this->validateCoupon($request, $coupon->id);
        $this->fillCoupon($coupon, $data, $request, $vendor);

        // Clear relevant caches
        \Illuminate\Support\Facades\Cache::forget('dashboard_' . $vendor->id);

        return redirect()->route('coupons')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $vendor = $this->currentVendor();

        if ($coupon->resturant_id !== $vendor->id) {
            abort(403);
        }

        if ($coupon->image && $this->isLocalImage($coupon->image)) {
            Storage::disk('public')->delete($coupon->image);
        }

        $coupon->delete();

        // Clear relevant caches
        \Illuminate\Support\Facades\Cache::forget('dashboard_' . $vendor->id);

        return redirect()->route('coupons')->with('success', 'Coupon deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $vendor = $this->currentVendor();

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $coupons = Coupon::whereIn('id', $validated['ids'])
            ->where('resturant_id', $vendor->id)
            ->get();

        foreach ($coupons as $coupon) {
            if ($coupon->image && $this->isLocalImage($coupon->image)) {
                Storage::disk('public')->delete($coupon->image);
            }
            $coupon->delete();
        }

        return redirect()->route('coupons')->with('success', 'Selected coupons deleted successfully.');
    }

    public function toggle(Request $request, Coupon $coupon)
    {
        $vendor = $this->currentVendor();

//        if ($coupon->resturant_id !== $vendor->id) {
//            abort(403);
//        }

        $request->validate([
            'field' => 'required|in:isEnabled,isPublic',
        ]);

        $field = $request->input('field');
        $coupon->{$field} = !$coupon->{$field};
        $coupon->save();

        // Clear relevant caches
        \Illuminate\Support\Facades\Cache::forget('dashboard_' . $vendor->id);

        return redirect()->route('coupons')->with('success', 'Coupon updated successfully.');
    }

    protected function validateCoupon(Request $request, ?string $couponId = null): array
    {
        $rules = [
            'code' => 'required|string|max:255|unique:coupons,code,' . $couponId . ',id',
            'discount_type' => 'required|in:Percentage,Fix Price',
            'discount' => 'required|numeric|min:0',
            'expires_at' => 'required|date',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'isEnabled' => 'sometimes|boolean',
            'isPublic' => 'sometimes|boolean',
            'minimum_order' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'cType' => 'required|in:restaurant,mart',
        ];

        return $request->validate($rules, [], [
            'discount_type' => 'discount type',
            'expires_at' => 'expiry date',
            'minimum_order' => 'minimum order',
        ]);
    }

    protected function fillCoupon(Coupon $coupon, array $data, Request $request, Vendor $vendor): void
    {
        $coupon->code = $data['code'];
        $coupon->discountType = $data['discount_type'];
        $coupon->discount = $data['discount'];
        $coupon->description = $data['description'] ?? null;
        $coupon->expiresAt = $this->formatExpiryDate($data['expires_at']);
        $coupon->isEnabled = $request->boolean('isEnabled');
        $coupon->isPublic = $request->boolean('isPublic');
        $coupon->item_value = $data['minimum_order'] !== '' ? $data['minimum_order'] : null;
        $coupon->usageLimit = $data['usage_limit'] !== '' ? $data['usage_limit'] : null;
        $coupon->resturant_id = $vendor->id;
        $coupon->cType = $data['cType'];

        if ($request->hasFile('image')) {
            if ($coupon->image && $this->isLocalImage($coupon->image)) {
                Storage::disk('public')->delete($coupon->image);
            }
            $path = $request->file('image')->store('coupons', 'public');
            $coupon->image = $path;
        }

        $coupon->save();
    }

    protected function renderCheckbox(Coupon $coupon): string
    {
        return '<input type="checkbox" class="is_open" name="ids[]" value="' . e($coupon->id) . '">';
    }

    protected function renderToggle(Coupon $coupon, string $field, string $color, ?string $label = null): string
    {
        $labelText = $label ?? ($coupon->{$field} ? __('Enabled') : __('Disabled'));
        $route = route('coupons.toggle', $coupon->id);

        return '<form method="POST" action="' . $route . '">' .
            csrf_field() .
            method_field('PATCH') .
            '<input type="hidden" name="field" value="' . $field . '">' .
            '<button type="submit" class="btn btn-sm btn-' . $color . '">' . e($labelText) . '</button>' .
            '</form>';
    }

    protected function renderActions(Coupon $coupon): string
    {
        $editRoute = route('coupons.edit', $coupon->id);
        $deleteRoute = route('coupons.destroy', $coupon->id);

        $deleteForm = '<form method="POST" action="' . $deleteRoute . '" class="d-inline" onsubmit="return confirm(\'Delete this coupon?\');">'
            . csrf_field()
            . method_field('DELETE')
            . '<button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>'
            . '</form>';

        return '<span class="action-btn">'
            . '<a href="' . $editRoute . '" class="btn btn-sm btn-outline-info"><i class="fa fa-edit"></i></a>'
            . $deleteForm
            . '</span>';
    }

    protected function formatDiscount(Coupon $coupon): string
    {
        if ($coupon->discountType === 'Percentage') {
            return rtrim(rtrim(number_format((float) $coupon->discount, 2), '0'), '.') . '%';
        }

        return $this->formatCurrency($coupon->discount);
    }

    protected function formatCurrency($amount): string
    {
        $formatted = number_format((float) $amount, $this->currency['decimals']);

        if ($this->currency['symbolAtRight']) {
            return $formatted . $this->currency['symbol'];
        }

        return $this->currency['symbol'] . $formatted;
    }

    protected function formatDate($value): string
    {
        $normalized = $this->normalizeDateValue($value);

        if (!$normalized) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($normalized)->format('M d, Y H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    protected function formatExpiryDate(string $date): string
    {
        return \Carbon\Carbon::parse($date)->endOfDay()->toIso8601String();
    }

    protected function normalizeDateValue($value): ?string
    {
        if (!$value) {
            return null;
        }

        $clean = trim((string) $value);
        $clean = trim($clean, "\"\\");

        return $clean === '' ? null : $clean;
    }

    protected function currentVendor(): Vendor
    {
        return $this->getCachedVendor();
    }

    protected function activeCurrency(): array
    {
        $cached = $this->getCachedCurrency();
        return [
            'symbol' => $cached['symbol'],
            'symbolAtRight' => $cached['symbol_at_right'],
            'decimals' => $cached['decimal_digits'],
        ];
    }

    protected function isLocalImage(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return !Str::startsWith($path, ['http://', 'https://']);
    }
}
