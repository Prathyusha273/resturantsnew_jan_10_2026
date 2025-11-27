<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\RestaurantOrder;
use App\Models\Setting;
use App\Models\Vendor;
use Carbon\Carbon;
use Google\Client as Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    protected ?array $currencyMeta = null;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return $this->renderOrderTable('orders.index');
    }

    public function placedOrders()
    {
        return $this->renderOrderTable('orders.placed', ['Order Placed']);
    }

    public function acceptedOrders()
    {
        return $this->renderOrderTable('orders.accepted', ['Order Accepted']);
    }

    public function rejectedOrders()
    {
        return $this->renderOrderTable('orders.rejected', ['Order Rejected', 'Order Cancelled', 'Driver Rejected']);
    }

    public function edit(string $id)
    {
        $vendor = $this->currentVendor();
        $order = $this->findVendorOrder($vendor->id, $id);
        $details = $this->buildOrderDetails($order);

        $currency = $this->currencyMeta();
        $availableDrivers = $this->getAvailableDrivers();

        return view('orders.edit', [
            'id' => $order->id,
            'order' => $order,
            'details' => $details,
            'statusOptions' => $this->statusOptions(),
            'currency' => $currency,
            'availableDrivers' => $availableDrivers,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $vendor = $this->currentVendor();
        $order = $this->findVendorOrder($vendor->id, $id);

        $data = $request->validate([
            'status' => 'required|string|max:255',
            'estimated_time' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order->status = $data['status'];
        if ($data['estimated_time'] !== null) {
            $order->estimatedTimeToPrepare = $data['estimated_time'];
        }
        if (array_key_exists('notes', $data)) {
            $order->notes = $data['notes'];
        }
        $order->save();

        return redirect()->route('orders.edit', $order->id)->with('success', 'Order updated successfully.');
    }

    public function destroy(string $id)
    {
        $vendor = $this->currentVendor();
        $order = $this->findVendorOrder($vendor->id, $id);
        $order->delete();

        return back()->with('success', 'Order deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $vendor = $this->currentVendor();
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        RestaurantOrder::where('vendorID', $vendor->id)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return back()->with('success', 'Selected orders deleted successfully.');
    }

    public function data(Request $request)
    {
        $vendor = $this->currentVendor();
        $statusFilter = $this->normalizeStatuses($request->input('status'));

        $query = RestaurantOrder::where('vendorID', $vendor->id);
        if (!empty($statusFilter)) {
            $query->whereIn('status', $statusFilter);
        }

        $recordsTotal = (clone $query)->count();

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('id', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('driver', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = [
            1 => 'id',
            2 => 'author',
            3 => 'driver',
            4 => 'status',
            5 => 'createdAt',
            6 => 'createdAt',
            7 => 'createdAt',
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 5);
        $orderColumn = $columns[$orderColumnIndex] ?? 'createdAt';
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length <= 0) {
            $length = 10;
        }

        $orders = $query->orderBy($orderColumn, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $currency = $this->currencyMeta();

        $data = $orders->map(function (RestaurantOrder $order) use ($currency) {
            return [
                'select' => $this->renderCheckbox($order),
                'id' => $this->renderOrderLink($order),
                'customer' => e($this->customerName($order)),
                'driver' => e($this->driverName($order)),
                'status' => $this->renderStatusBadge($order->status),
                'amount' => $this->formatCurrency($this->calculateSubtotal($order), $currency),
                'type' => $this->formatOrderType($order->takeAway),
                'date' => $this->formatDate($order->createdAt),
                'actions' => $this->renderActions($order),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function orderprint(string $id = '')
    {
        $vendor = $this->currentVendor();
        $order = $this->findVendorOrder($vendor->id, $id);
        $details = $this->buildOrderDetails($order);

        return view('orders.print', [
            'order' => $order,
            'details' => $details,
        ]);
    }

    public function sendnotification(Request $request)
    {
        $orderStatus = $request->orderStatus;

        if (
            Storage::disk('local')->has('firebase/credentials.json')
            && in_array($orderStatus, ['Order Accepted', 'Order Rejected', 'Order Completed'], true)
        ) {
            $client = new Google_Client();
            $client->setAuthConfig(storage_path('app/firebase/credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $clientToken = $client->getAccessToken();
            $accessToken = $clientToken['access_token'] ?? null;

            $fcmToken = $request->fcm;

            if (!empty($accessToken) && !empty($fcmToken)) {
                $projectId = env('FIREBASE_PROJECT_ID');
                $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

                $payload = [
                    'message' => [
                        'notification' => [
                            'title' => $request->subject,
                            'body' => $request->message,
                        ],
                        'token' => $fcmToken,
                    ],
                ];

                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

                $result = curl_exec($ch);
                if ($result === false) {
                    curl_close($ch);
                    return response()->json([
                        'success' => false,
                        'message' => 'FCM Send Error: ' . curl_error($ch),
                    ], 500);
                }
                curl_close($ch);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification successfully sent.',
                    'result' => json_decode($result, true),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Missing sender id or token to send notification.',
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'Firebase credentials file not found.',
        ], 404);
    }

    protected function renderOrderTable(string $view, array $statuses = [])
    {
        return view($view, [
            'statusFilter' => $statuses,
            'statusQuery' => implode(',', $statuses),
        ]);
    }

    protected function renderCheckbox(RestaurantOrder $order): string
    {
        $id = e($order->id);

        return '<input type="checkbox" class="is_open" value="' . $id . '">';
    }

    protected function renderOrderLink(RestaurantOrder $order): string
    {
        $route = route('orders.edit', $order->id);
        $id = e($order->id);

        return '<a href="' . e($route) . '" class="redirecttopage" data-url="' . e($route) . '">' . $id . '</a>';
    }

    protected function renderStatusBadge(?string $status): string
    {
        $class = $this->statusClass($status ?? '');
        return '<span class="' . e($class) . '">' . e($status ?? 'N/A') . '</span>';
    }

    protected function renderActions(RestaurantOrder $order): string
    {
        $edit = route('orders.edit', $order->id);
        $print = route('vendors.orderprint', $order->id);
        $delete = route('orders.destroy', $order->id);

        return '<span class="action-btn">
            <a href="' . e($print) . '" target="_blank"><i class="fa fa-print" style="font-size:20px;"></i></a>
            <a href="' . e($edit) . '"><i class="fa fa-edit"></i></a>
            <a href="javascript:void(0)" class="order-delete-btn" data-route="' . e($delete) . '" data-id="' . e($order->id) . '">
                <i class="fa fa-trash"></i>
            </a>
        </span>';
    }

    protected function calculateSubtotal(RestaurantOrder $order): float
    {
        $products = $this->decodeJson($order->products);
        $subtotal = 0;

        foreach ($products as $product) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $price = $product['discountPrice'] ?? $product['price'] ?? 0;
            $extras = $product['extras_price'] ?? 0;

            $lineTotal = ((float) $price * $quantity) + (float) $extras;
            $subtotal += $lineTotal;
        }

        return $subtotal;
    }

    protected function customerName(RestaurantOrder $order): string
    {
        $author = $this->decodeJson($order->author);
        $first = $author['firstName'] ?? '';
        $last = $author['lastName'] ?? '';
        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : '—';
    }

    protected function driverName(RestaurantOrder $order): string
    {
        $driver = $this->decodeJson($order->driver);
        $first = $driver['firstName'] ?? '';
        $last = $driver['lastName'] ?? '';
        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : '—';
    }

    protected function formatOrderType($value): string
    {
        $isTakeAway = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        return $isTakeAway ? trans('lang.order_takeaway') : trans('lang.order_delivery');
    }

    protected function formatDate($value): string
    {
        $date = $this->parseDate($value);

        if (!$date) {
            return '—';
        }

        return $date->timezone('Asia/Kolkata')->format('d M Y h:i A');
    }


    protected function renderCurrency(float $amount): string
    {
        return $this->formatCurrency($amount, $this->currencyMeta());
    }

    protected function formatCurrency(float $amount, array $currencyMeta): string
    {
        $formatted = number_format($amount, $currencyMeta['decimal_digits']);
        return $currencyMeta['symbol_at_right']
            ? $formatted . $currencyMeta['symbol']
            : $currencyMeta['symbol'] . $formatted;
    }

    protected function statusClass(string $status): string
    {
        return match ($status) {
            'Order Placed' => 'order_placed',
            'Order Accepted', 'Driver Accepted' => 'order_accepted',
            'Order Rejected', 'Order Cancelled' => 'order_rejected',
            'Driver Pending' => 'driver_pending',
            'Driver Rejected' => 'driver_rejected',
            'Order Shipped' => 'order_shipped',
            'In Transit' => 'in_transit',
            'Order Completed' => 'order_completed',
            default => 'order_status_default',
        };
    }

    protected function currencyMeta(): array
    {
        if ($this->currencyMeta !== null) {
            return $this->currencyMeta;
        }

        $currency = Currency::where('isActive', true)->first();

        return $this->currencyMeta = [
            'symbol' => $currency->symbol ?? '₹',
            'symbol_at_right' => (bool) ($currency->symbolAtRight ?? false),
            'decimal_digits' => $currency->decimal_degits ?? 2,
        ];
    }

    protected function normalizeStatuses($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_filter(array_map('trim', $value));
        }

        return array_filter(array_map('trim', explode(',', (string) $value)));
    }

    protected function buildOrderDetails(RestaurantOrder $order): array
    {
        $currency = $this->currencyMeta();
        $products = $this->decodeJson($order->products);
        $author = $this->decodeJson($order->author);
        $address = $this->decodeJson($order->address);
        $driver = $this->decodeJson($order->driver);
        $vendorData = $this->decodeJson($order->vendor);
        if (empty($vendorData) || !isset($vendorData['title'])) {
            $vendorRecord = Vendor::find($order->vendorID);
            if ($vendorRecord) {
                $vendorData = [
                    'id' => $vendorRecord->id,
                    'title' => $vendorRecord->title,
                    'phonenumber' => $vendorRecord->phonenumber ?? $vendorRecord->phone ?? null,
                    'phoneNumber' => $vendorRecord->phonenumber ?? $vendorRecord->phone ?? null,
                    'email' => $vendorRecord->email ?? null,
                    'location' => $vendorRecord->location ?? $vendorRecord->address ?? null,
                    'photo' => $vendorRecord->photo ?? $vendorRecord->coverPhoto ?? null,
                ];
            }
        }
        $taxSetting = $this->decodeJson($order->taxSetting);
        $specialDiscount = $this->decodeJson($order->specialDiscount);

        $summary = $this->calculateTotals($order, $products, $taxSetting, $specialDiscount, $currency);

        return [
            'customer' => [
                'name' => $this->customerName($order),
                'email' => $author['email'] ?? null,
                'phone' => $author['phoneNumber'] ?? null,
            ],
            'address' => $address,
            'driver' => $driver,
            'vendor' => $vendorData,
            'products' => $this->mapProducts($products, $currency),
            'summary' => $summary,
            'order_type' => $this->formatOrderType($order->takeAway),
            'created_at' => $this->formatDate($order->createdAt),
//            'schedule_time' => $this->formatDate($order->scheduleTime),
            'schedule_time' => $this->formatDate($order->scheduleTime ?: $order->createdAt),
//            'estimated_time' => $order->estimatedTimeToPrepare,
            'estimated_time' => $order->estimatedTimeToPrepare ?: '20',
            'payment_method' => $this->formatPaymentMethod($order->payment_method),
            'status' => $order->status ?? 'N/A',
            'status_class' => $this->statusClass($order->status ?? ''),
            'notes' => $order->notes,
            'coupon_code' => $order->couponCode,
            'placeholder' => $this->placeholderImage(),
        ];
    }

    protected function mapProducts(array $products, array $currency): array
    {
        $placeholder = $this->placeholderImage();

        return array_map(function ($product) use ($currency, $placeholder) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $price = (float) ($product['discountPrice'] ?? $product['price'] ?? 0);
            $extrasPrice = (float) ($product['extras_price'] ?? 0);
            $lineTotal = ($price * $quantity) + $extrasPrice;

            return [
                'name' => $product['name'] ?? 'Item',
                'photo' => $product['photo'] ?? $placeholder,
                'price' => $this->formatCurrency($price, $currency),
                'quantity' => $quantity,
                'extras_price' => $this->formatCurrency($extrasPrice, $currency),
                'total' => $this->formatCurrency($lineTotal, $currency),
                'extras' => $product['extras'] ?? [],
                'variant' => $product['variant_info']['variant_options'] ?? [],
            ];
        }, $products);
    }

    protected function calculateTotals(RestaurantOrder $order, array $products, array $taxSetting, array $specialDiscount, array $currency): array
    {
        $itemsTotal = 0;
        $addonTotal = 0;

        foreach ($products as $product) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $price = (float) ($product['discountPrice'] ?? $product['price'] ?? 0);
            $extras = (float) ($product['extras_price'] ?? 0);

            $itemsTotal += $price * $quantity;
            $addonTotal += $extras;
        }

        $subtotal = $itemsTotal + $addonTotal;
        $discount = (float) ($order->discount ?? 0);
        $special = (float) ($specialDiscount['special_discount'] ?? 0);
        $afterDiscount = max($subtotal - $discount - $special, 0);

        $taxes = $this->calculateTaxes($taxSetting, $afterDiscount, $currency);
        $delivery = (float) ($order->deliveryCharge ?? 0);
        $tip = (float) ($order->tip_amount ?? 0);

        $total = $afterDiscount + $taxes['total_raw'] + $delivery + $tip;

        return [
            'items_total' => $this->formatCurrency($itemsTotal, $currency),
            'addons_total' => $this->formatCurrency($addonTotal, $currency),
            'subtotal' => $this->formatCurrency($subtotal, $currency),
            'discount' => $this->formatCurrency($discount, $currency),
            'special_discount' => $this->formatCurrency($special, $currency),
            'after_discount' => $this->formatCurrency($afterDiscount, $currency),
            'taxes' => $taxes['items'],
            'tax_total' => $this->formatCurrency($taxes['total_raw'], $currency),
            'delivery' => $this->formatCurrency($delivery, $currency),
            'tip' => $this->formatCurrency($tip, $currency),
            'grand_total' => $this->formatCurrency($total, $currency),
        ];
    }

    protected function calculateTaxes(array $taxSetting, float $amount, array $currency): array
    {
        $taxItems = [];
        $taxTotal = 0;

        if (!empty($taxSetting)) {
            if ($this->isAssoc($taxSetting)) {
                $taxSetting = [$taxSetting];
            }

            foreach ($taxSetting as $tax) {
                if (empty($tax['enable'])) {
                    continue;
                }

                $title = $tax['title'] ?? 'Tax';
                $type = strtolower($tax['type'] ?? 'percentage');
                $value = (float) ($tax['tax'] ?? 0);
                $computed = $type === 'percentage' ? ($amount * $value / 100) : $value;

                $taxTotal += $computed;

                $label = $title;
                if ($type === 'percentage') {
                    $label .= ' (' . $value . '%)';
                }

                $taxItems[] = [
                    'label' => $label,
                    'amount' => $this->formatCurrency($computed, $currency),
                ];
            }
        }

        return [
            'items' => $taxItems,
            'total_raw' => $taxTotal,
        ];
    }

    protected function formatPaymentMethod(?string $method): string
    {
        if (!$method) {
            return '—';
        }

        $methodLower = strtolower($method);
        $image = '';
        $label = '';

        switch ($methodLower) {
            case 'stripe':
                $image = asset('images/stripe.png');
                $label = 'Stripe';
                break;
            case 'cod':
                $image = asset('images/cashondelivery.png');
//                $label = 'CASH ON DELIVERY';
                break;
            case 'razorpay':
                $image = asset('images/razorpay.png');
//                $label = 'Razorpay';
                break;
            case 'paypal':
                $image = asset('images/paypal.png');
                $label = 'PayPal';
                break;
            case 'payfast':
                $image = asset('images/payfast.png');
                $label = 'PayFast';
                break;
            case 'paystack':
                $image = asset('images/paystack.png');
                $label = 'Paystack';
                break;
            case 'flutterwave':
                $image = asset('images/flutter_wave.png');
                $label = 'Flutterwave';
                break;
            case 'mercado pago':
                $image = asset('images/marcado_pago.png');
                $label = 'Mercado Pago';
                break;
            case 'wallet':
                $image = asset('images/foodie_wallet.png');
                $label = 'Wallet';
                break;
            case 'paytm':
                $image = asset('images/paytm.png');
                $label = 'Paytm';
                break;
            default:
                $label = ucfirst(str_replace('_', ' ', $methodLower));
                break;
        }

        if ($image) {
            return '<img src="' . e($image) . '" alt="' . e($label) . '" style="width:30%;height:30%;display:inline-block;vertical-align:middle;margin-right:5px;"> ' . e($label);
        }

        return e($label);
    }

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if (is_array($value)) {
            if (isset($value['_seconds'])) {
                return Carbon::createFromTimestamp($value['_seconds']);
            }
            if (isset($value['seconds'])) {
                return Carbon::createFromTimestamp($value['seconds']);
            }
        }

        $clean = trim((string) $value);
        $clean = trim($clean, '"');
        $clean = str_replace(['\\"', "\\'"], '', $clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded['_seconds'])) {
                return Carbon::createFromTimestamp($decoded['_seconds']);
            }
            if (isset($decoded['seconds'])) {
                return Carbon::createFromTimestamp($decoded['seconds']);
            }
        }

        try {
            return Carbon::parse($clean);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        $clean = trim((string) $value);
        $clean = trim($clean, '"');
        $clean = str_replace(['\\"', "\\'"], '"', $clean);

        $decoded = json_decode($clean, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    protected function placeholderImage(): string
    {
        $fields = Setting::getFields('placeHolderImage');
        return $fields['image'] ?? asset('assets/images/placeholder.png');
    }

    protected function statusOptions(): array
    {
        return [
            'Order Accepted',
            'Order Rejected',
        ];
    }

    protected function findVendorOrder(string $vendorId, string $orderId): RestaurantOrder
    {
        return RestaurantOrder::where('vendorID', $vendorId)
            ->where('id', $orderId)
            ->firstOrFail();
    }

    protected function currentVendor(): Vendor
    {
        $user = Auth::user();

        if ($user && $user->vendorID) {
            $vendor = Vendor::where('id', $user->vendorID)->first();
            if ($vendor) {
                return $vendor;
            }
        }

        $vendor = Vendor::where('author', Auth::id())->first();

        if (!$vendor) {
            abort(403, 'Vendor profile not found.');
        }

        return $vendor;
    }

    protected function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function getAvailableDrivers(): array
    {
        try {
            $drivers = \App\Models\User::where('role', 'driver')
                ->orWhere('role', 'Driver')
                ->where('isEnabled', true)
                ->select('id', 'firstName', 'lastName', 'phoneNumber', 'email')
                ->get()
                ->map(function ($driver) {
                    return [
                        'id' => $driver->id,
                        'firstName' => $driver->firstName ?? '',
                        'lastName' => $driver->lastName ?? '',
                        'phoneNumber' => $driver->phoneNumber ?? '',
                        'email' => $driver->email ?? '',
                    ];
                })
                ->toArray();

            return $drivers;
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getLatestOrderForVendor($vendorID)
    {
        $last = DB::table('restaurant_orders')
            ->where('vendorID', $vendorID)
            ->orderByRaw('CAST(SUBSTRING(id, 6) AS UNSIGNED) DESC')
            ->select('id', 'scheduleTime', 'status')
            ->first();

        return response()->json(['latest_id' => $last->id ?? '']);
    }

    public function getOrder($id)
    {
        $order = DB::table('restaurant_orders')->where('id', $id)->first();
        if (!$order) return response()->json([]);

        // Merge JSON values
        $order->author = json_decode($order->author, true);

        // Fetch vendor details properly
        $vendor = DB::table('vendors')
            ->where('id', $order->vendorID)
            ->select('id', 'title', 'photo', 'vType', 'phonenumber', 'location')
            ->first();

        $order->vendor_title = $vendor->title ?? null;
        $order->vendor_photo = $vendor->photo ?? null;

        return response()->json($order);
    }
    public function getRingtone() {
        $row = DB::table('settings') ->where('document_name', 'globalSettings') ->first();
        if (!$row) {
            return response()->json(['ringtone' => null]);
        }
        $fields = json_decode($row->fields, true);

        return response()->json([
            'ringtone' => $fields['order_ringtone_url'] ?? null,
            'status' => 'OK',
            'route_debug' => request()->route(), ]);
    }
}

