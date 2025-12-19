<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WithdrawMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PayoutsController extends Controller
{
    protected ?array $currencyMeta = null;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        return view('restaurants_payouts.index', [
            'statusQuery' => $request->get('status', ''),
        ]);
    }

    public function data(Request $request)
    {
        $vendor = $this->currentVendor();
        $query = Payout::where('vendorID', $vendor->id);

        if ($statuses = $this->normalizeStatuses($request->input('status'))) {
            $query->whereIn('paymentStatus', $statuses);
        }

        $recordsTotal = (clone $query)->count();

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('note', 'like', "%{$search}%")
                    ->orWhere('paymentStatus', 'like', "%{$search}%")
                    ->orWhere('withdrawMethod', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = [
            0 => 'amount',
            1 => 'paidDate',
            2 => 'note',
            3 => 'paymentStatus',
            4 => 'withdrawMethod',
        ];
        $orderIndex = (int) $request->input('order.0.column', 1);
        $orderColumn = $columns[$orderIndex] ?? 'paidDate';
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length <= 0) {
            $length = 10;
        }

        $currency = $this->currencyMeta();

        $payouts = $query->orderBy($orderColumn, $orderDir)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (Payout $payout) use ($currency) {
                return [
                    'amount' => $this->formatCurrency($payout->amount ?? 0, $currency),
                    'date' => $this->formatDate($payout->paidDate),
                    'note' => e($payout->note ?? '—'),
                    'status' => $this->renderStatusBadge($payout->paymentStatus),
                    'method' => ucfirst($payout->withdrawMethod ?? '—'),
                ];
            });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $payouts,
        ]);
    }

    public function create()
    {
        $user = $this->currentUser();
        $currency = $this->currencyMeta();

        return view('restaurants_payouts.create', [
            'walletBalance' => $user->wallet_amount ?? 0,
            'currency' => $currency,
            'withdrawMethods' => $this->availableWithdrawMethods($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->currentUser();
        $vendor = $this->currentVendor();
        $wallet = (float) ($user->wallet_amount ?? 0);
        $methods = $this->availableWithdrawMethods($user);
        $methodKeys = collect($methods)->pluck('value')->filter()->all();

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'withdraw_method' => 'required|string',
            'note' => 'nullable|string|max:500',
        ]);

        if (!in_array($data['withdraw_method'], $methodKeys, true)) {
            return back()
                ->withErrors(['withdraw_method' => 'Selected withdrawal method is not available.'])
                ->withInput();
        }

        $amount = (float) $data['amount'];
        if ($amount > $wallet) {
            return back()
                ->withErrors(['amount' => 'Insufficient wallet balance.'])
                ->withInput();
        }

        $payout = new Payout();
        $payout->note = $data['note'] ?? '';
        $payout->amount = $amount;
        $payout->withdrawMethod = $data['withdraw_method'];
        $payout->paidDate = Carbon::now()->toIso8601String();
        $payout->vendorID = $vendor->id;
        $payout->paymentStatus = 'Pending';
        $payout->save();

        $user->wallet_amount = $wallet - $amount;
        $user->save();

        return redirect()->route('payments')->with('success', 'Payout request submitted successfully.');
    }

    protected function currentUser(): User
    {
        return Auth::user();
    }

    protected function currentVendor(): Vendor
    {
        return $this->getCachedVendor();
    }

    protected function availableWithdrawMethods(User $user): array
    {
        $methods = [];
        $withdraw = WithdrawMethod::where('userId', $user->firebase_id)->first();

        if (!empty($user->userBankDetails) && is_array($user->userBankDetails)) {
            $methods[] = [
                'value' => 'bank',
                'label' => 'Bank Transfer',
            ];
        }

        $map = [
            'stripe' => 'stripeSettings',
            'razorpay' => 'razorpaySettings',
            'paypal' => 'paypalSettings',
            'flutterwave' => 'flutterWave',
        ];

        foreach ($map as $column => $settingName) {
            $data = $withdraw?->{$column};
            if (empty($data) || (isset($data['enable']) && !$data['enable'])) {
                continue;
            }
            if (!$this->withdrawEnabled($settingName)) {
                continue;
            }
            $methods[] = [
                'value' => $column,
                'label' => $data['name'] ?? ucfirst($column),
            ];
        }

        if (empty($methods)) {
            $methods[] = [
                'value' => 'bank',
                'label' => 'Bank Transfer',
            ];
        }

        return $methods;
    }

    protected function withdrawEnabled(string $documentName): bool
    {
        $fields = Setting::getFields($documentName);

        return (bool) ($fields['isWithdrawEnabled'] ?? false);
    }

    protected function currencyMeta(): array
    {
        if ($this->currencyMeta !== null) {
            return $this->currencyMeta;
        }

        return $this->currencyMeta = $this->getCachedCurrency();
    }

    protected function formatCurrency(float $amount, array $meta): string
    {
        $formatted = number_format($amount, $meta['decimal_digits']);

        return $meta['symbol_at_right']
            ? $formatted . $meta['symbol']
            : $meta['symbol'] . $formatted;
    }

    protected function formatDate($value): string
    {
        $date = $this->parseDate($value);

        return $date ? $date->format('d M Y H:i') : '—';
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

    protected function renderStatusBadge(?string $status): string
    {
        $status = $status ?? 'Pending';
        $class = match ($status) {
            'Success' => 'badge badge-success',
            'Failed', 'Reject' => 'badge badge-danger',
            'Pending' => 'badge badge-warning',
            default => 'badge badge-secondary',
        };

        return '<span class="' . e($class) . '">' . e($status) . '</span>';
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
}
