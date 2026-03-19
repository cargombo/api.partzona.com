<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Bütün tranzaksiyalar (admin)
     * GET /api/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with('partner:id,company_name');

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        return response()->json($transactions);
    }

    /**
     * Maliyyə statistikası (admin)
     * GET /api/transactions/stats
     */
    public function stats(): JsonResponse
    {
        $totalDeposits = Transaction::where('type', 'deposit')->sum('amount');
        $totalCharges = Transaction::where('type', 'charge')->sum('amount');
        $totalWithdrawals = Transaction::where('type', 'withdrawal')->sum('amount');
        $totalRefunds = Transaction::where('type', 'refund')->sum('amount');

        $totalOutstanding = Partner::sum('outstanding_balance');
        $totalDebitsUsed = Partner::sum('debit_used');

        return response()->json([
            'total_deposits' => (float) $totalDeposits,
            'total_charges' => (float) abs($totalCharges),
            'total_withdrawals' => (float) abs($totalWithdrawals),
            'total_refunds' => (float) $totalRefunds,
            'total_outstanding' => (float) $totalOutstanding,
            'total_debits_used' => (float) $totalDebitsUsed,
        ]);
    }

    /**
     * Partnerin öz tranzaksiyaları (portal)
     * GET /api/partner/transactions
     */
    public function myTransactions(Request $request): JsonResponse
    {
        $transactions = Transaction::where('partner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }
}
