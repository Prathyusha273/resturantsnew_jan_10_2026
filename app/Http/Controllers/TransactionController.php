<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected ?array $currencyMeta = null;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('transaction.index');
    }

    public function data(Request $request)
    {
        $user = Auth::user();
        $vendorId = $user->firebase_id;

        $baseQuery = WalletTransaction::where('user_id', $vendorId);
        $recordsTotal = (clone $baseQuery)->count();

        $query = clone $baseQuery;

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $end = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->whereBetween('date', [$this->quoteIsoString($start), $this->quoteIsoString($end)]);
        }

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('amount', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = [
            0 => 'amount',
            1 => 'date',
            2 => 'payment_method',
            3 => 'note',
            4 => 'payment_status',
        ];

        $orderIndex = (int) $request->input('order.0.column', 1);
        $orderColumn = $columns[$orderIndex] ?? 'date';
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderedQuery = (clone $query)->orderBy($orderColumn, $orderDir);

        $length = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);

        $transactions = (clone $orderedQuery)
            ->skip($start)
            ->take($length <= 0 ? 10 : $length)
            ->get();

        $exportData = (clone $query)->get();

        $currency = $this->currencyMeta();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $transactions->map(fn ($transaction) => $this->formatRow($transaction, $currency)),
            'filteredData' => $exportData->map(fn ($transaction) => $this->formatExportRow($transaction)),
        ]);
    }

    protected function formatRow(WalletTransaction $transaction, array $currency): array
    {
        $date = $this->parseDate($transaction->date);
        $amountHtml = $this->formatAmount($transaction, $currency);
        $dateHtml = $date ? $date->format('d M Y h:i A') : '';
        $paymentMethodHtml = $this->formatPaymentMethod($transaction->payment_method);
        $statusHtml = $this->formatStatus($transaction->payment_status);

        return [
            $amountHtml,
            $dateHtml,
            $paymentMethodHtml,
            e($transaction->note ?? ''),
            $statusHtml,
        ];
    }

    protected function formatExportRow(WalletTransaction $transaction): array
    {
        $currency = $this->currencyMeta();
        $date = $this->parseDate($transaction->date);
        $amount = (float) ($transaction->amount ?? 0);

        // Format amount with currency
        $formatted = number_format($amount, $currency['decimal_digits']);
        $amountDisplay = $currency['symbol_at_right']
            ? $formatted . $currency['symbol']
            : $currency['symbol'] . $formatted;

        // Determine if credit or debit
        $isCredit = $transaction->isTopUp
            || strtolower((string) $transaction->payment_method) === 'cancelled order payment';

        if ($isCredit) {
            $amountDisplay = '+' . $amountDisplay;
        } else {
            $amountDisplay = '-' . $amountDisplay;
        }

        return [
            'amount' => $amountDisplay,
            'date' => $date ? $date->format('d M Y h:i A') : '',
            'payment_method' => $transaction->payment_method ?? '',
            'note' => $transaction->note ?? '',
            'payment_status' => $transaction->payment_status ?? '',
        ];
    }

    protected function formatAmount(WalletTransaction $transaction, array $currency): string
    {
        $amount = (float) ($transaction->amount ?? 0);
        $formatted = number_format($amount, $currency['decimal_digits']);
        $display = $currency['symbol_at_right']
            ? $formatted . $currency['symbol']
            : $currency['symbol'] . $formatted;

        $isCredit = $transaction->isTopUp
            || strtolower((string) $transaction->payment_method) === 'cancelled order payment';

        if ($isCredit) {
            return '<span class="text-green">' . $display . '</span>';
        }

        if ($transaction->isTopUp === false) {
            return '<span class="text-red">(' . $display . ')</span>';
        }

        return $display;
    }

    protected function formatPaymentMethod(?string $method): string
    {
        if (empty($method)) {
            return '';
        }

        if (strtolower($method) === 'wallet') {
            $image = asset('images/foodie_wallet.png');

            return '<img style="width:100px" alt="wallet" src="' . $image . '">';
        }

        return e($method);
    }

    protected function formatStatus(?string $status): string
    {
        $status = $status ?? '';
        $class = match (strtolower($status)) {
            'success' => 'badge badge-success',
            'undefined' => 'badge badge-secondary',
            'refund success' => 'badge badge-info',
            default => 'badge badge-light',
        };

        return '<span class="' . $class . '">' . e($status) . '</span>';
    }

    protected function currencyMeta(): array
    {
        if ($this->currencyMeta !== null) {
            return $this->currencyMeta;
        }

        return $this->currencyMeta = $this->getCachedCurrency();
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $clean = trim($value, '"');

        try {
            return Carbon::parse($clean);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function quoteIsoString(Carbon $date): string
    {
        return '"' . $date->toIso8601String() . '"';
    }
}
