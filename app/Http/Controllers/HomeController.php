<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\VendorUsers;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\RestaurantOrder;
use App\Models\Currency;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();

        // Get firebase id from users table
        $firebaseId = $user->firebase_id;

        // Cache vendor lookup (5 minutes)
        $vendor = \Illuminate\Support\Facades\Cache::remember('vendor_' . $firebaseId, 300, function () use ($firebaseId) {
            return Vendor::where('author', $firebaseId)->select(['id', 'title', 'author'])->first();
        });

        // Cache currency meta (5 minutes)
        $currencyMeta = \Illuminate\Support\Facades\Cache::remember('active_currency_meta', 300, function () {
            $currency = Currency::where('isActive', true)->first();
            return [
                'symbol' => $currency->symbol ?? '₹',
                'symbol_at_right' => (bool) ($currency->symbolAtRight ?? false),
                'decimal_digits' => $currency->decimal_degits ?? 2,
            ];
        });

        // Cache dashboard data (5 minutes) - only load recent orders for dashboard
        $cacheKey = 'dashboard_' . ($vendor->id ?? 'none');
        $dashboard = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($vendor, $currencyMeta) {
            $orders = collect();
            $productCount = 0;

            if ($vendor) {
                // Only fetch recent orders (last 100) instead of all orders for dashboard
                $orders = RestaurantOrder::where('vendorID', $vendor->id)
                    ->orderByDesc('createdAt')
                    ->limit(100)
                    ->select(['id', 'status', 'products', 'author', 'createdAt', 'discount', 'deliveryCharge', 'toPayAmount', 'ToPay', 'adminCommission', 'adminCommissionType', 'taxSetting', 'takeAway'])
                    ->get();

                // Cache product count separately (5 minutes)
                $productCount = \Illuminate\Support\Facades\Cache::remember('product_count_' . $vendor->id, 300, function () use ($vendor) {
                    return VendorProduct::where('vendorID', $vendor->id)->count();
                });
            }

            return $this->buildDashboardData($orders, $productCount, $currencyMeta);
        });

        return view('home', [
            'stats' => $dashboard['totals'],
            'statusCounts' => $dashboard['status_counts'],
            'recentOrders' => $dashboard['recent_orders'],
            'charts' => $dashboard['charts'],
            'currencyMeta' => $currencyMeta,
            'vendorExists' => (bool) $vendor,
        ]);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function welcome()
    {
        return view('welcome');
    }

    public function dashboard()
    {
        return view('dashboard');
    }

    public function users()
    {
        return view('users');
    }

    public function storeFirebaseService(Request $request){
		if(!empty($request->serviceJson) && !Storage::disk('local')->has('firebase/credentials.json')){
			Storage::disk('local')->put('firebase/credentials.json',file_get_contents(base64_decode($request->serviceJson)));
		}
	}

    /**
     * Build dashboard metrics from SQL data.
     */
    protected function buildDashboardData($orders, int $productCount, array $currencyMeta): array
    {
        $statusMap = [
            'placed' => ['Order Placed'],
            'confirmed' => ['Order Accepted', 'Driver Accepted'],
            'shipped' => ['Order Shipped', 'In Transit'],
            'completed' => ['Order Completed'],
            'canceled' => ['Order Rejected', 'Order Cancelled'],
            'failed' => ['Driver Rejected'],
            'pending' => ['Driver Pending'],
        ];

        $statusCounts = array_fill_keys(array_keys($statusMap), 0);
        $totalOrders = $orders->count();

        $earnings = 0;
        $commissionTotal = 0;
        $salesByMonth = array_fill(1, 12, 0);
        $recentOrders = [];

        $currentYear = Carbon::now()->year;

        foreach ($orders as $order) {
            $status = $order->status ?? '';

            foreach ($statusMap as $label => $statuses) {
                if (in_array($status, $statuses, true)) {
                    $statusCounts[$label]++;
                    break;
                }
            }

            $parsed = $this->parseOrderTotals($order);

            if ($status === 'Order Completed') {
                $earnings += $parsed['total'];
                $commissionTotal += $parsed['admin_commission'];

                if ($parsed['date'] && $parsed['date']->year === $currentYear) {
                    $salesByMonth[(int) $parsed['date']->format('n')] += $parsed['total'];
                }
            }
        }

        $recentOrders = $this->buildRecentOrders($orders, $currencyMeta);

        $totals = [
            'total_orders' => $totalOrders,
            'total_products' => $productCount,
            'total_earnings' => $earnings,
            'total_earnings_formatted' => $this->formatCurrency($earnings, $currencyMeta),
            'admin_commission' => $commissionTotal,
            'admin_commission_formatted' => $this->formatCurrency($commissionTotal, $currencyMeta),
        ];

        $charts = [
            'sales' => [
                'labels' => ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'],
                'data' => array_values($salesByMonth),
            ],
            'visitors' => [
                'orders' => $totalOrders,
                'products' => $productCount,
            ],
            'commission' => [
                'labels' => ['Total Earnings'],
                'data' => [$earnings],
            ],
        ];

        return [
            'totals' => $totals,
            'status_counts' => $statusCounts,
            'recent_orders' => $recentOrders,
            'charts' => $charts,
        ];
    }

    /**
     * Parse order totals and related metadata.
     */
    protected function parseOrderTotals(RestaurantOrder $order): array
    {
        $products = $this->decodeJson($order->products);
        $author = $this->decodeJson($order->author);
        $taxSetting = $this->decodeJson($order->taxSetting);

        $subtotal = 0;
        $productCount = 0;

        foreach ($products as $product) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $price = $product['discountPrice'] ?? $product['price'] ?? 0;
            $extras = $product['extras_price'] ?? 0;

            $lineTotal = ((float) $price * $quantity) + (float) $extras;
            $subtotal += $lineTotal;
            $productCount += $quantity;
        }

        $discount = (float) ($order->discount ?? 0);
        $minPrice = max($subtotal - $discount, 0);

        $tax = 0;
        if (!empty($taxSetting) && isset($taxSetting['tax'])) {
            $taxValue = (float) $taxSetting['tax'];
            $type = $taxSetting['type'] ?? 'percent';
            $tax = $type === 'percent' ? ($minPrice * $taxValue / 100) : $taxValue;
        }

        $delivery = (float) ($order->deliveryCharge ?? 0);

        $total = max($minPrice + $tax + $delivery, 0);

        if (!empty($order->toPayAmount)) {
            $total = (float) $order->toPayAmount;
        } elseif (!empty($order->ToPay)) {
            $total = (float) $order->ToPay;
        }

        $commission = 0;
        $commissionValue = (float) ($order->adminCommission ?? 0);
        if ($commissionValue > 0) {
            $type = $order->adminCommissionType ?? 'Percent';
            $commission = $type === 'Percent' ? ($total * $commissionValue / 100) : $commissionValue;
        }

        return [
            'total' => $total,
            'admin_commission' => $commission,
            'date' => $this->parseDate($order->createdAt),
            'author' => $author,
            'products' => $products,
            'product_count' => $productCount,
            'status' => $order->status ?? '',
            'takeAway' => $order->takeAway ?? null,
        ];
    }

    /**
     * Build recent orders collection.
     */
    protected function buildRecentOrders($orders, array $currencyMeta): array
    {
        return $orders->map(function ($order) use ($currencyMeta) {
            $parsed = $this->parseOrderTotals($order);
            $date = $parsed['date'];
            $author = $parsed['author'];

            $customerName = trim(($author['firstName'] ?? '') . ' ' . ($author['lastName'] ?? ''));
            $customerName = $customerName !== '' ? $customerName : 'N/A';

            return [
                'id' => $order->id,
                'customer' => $customerName,
                'type' => $this->formatOrderType($parsed['takeAway']),
                'subtotal' => $this->formatCurrency($parsed['total'], $currencyMeta),
                'products' => $parsed['product_count'],
                'date' => $date ? $date->format('d M Y H:i') : '—',
                'timestamp' => $date ? $date->timestamp : 0,
                'status' => $order->status ?? 'N/A',
                'status_class' => $this->statusClass($order->status ?? ''),
                'url' => route('orders.edit', $order->id),
            ];
        })
        ->sortByDesc(function ($order) {
            return $order['timestamp'];
        })
        ->take(10)
        ->map(function ($order) {
            unset($order['timestamp']);
            return $order;
        })
        ->values()
        ->toArray();
    }

    protected function formatOrderType($value): string
    {
        $isTakeAway = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        return $isTakeAway ? 'Take away' : 'Order Delivery';
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

    protected function formatCurrency(float $amount, array $currencyMeta): string
    {
        $formatted = number_format($amount, $currencyMeta['decimal_digits']);
        return $currencyMeta['symbol_at_right']
            ? $formatted . $currencyMeta['symbol']
            : $currencyMeta['symbol'] . $formatted;
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
}
