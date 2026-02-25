<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmTransaction;

class FarmReportController extends Controller
{
    public function pnl(Request $request)
    {
        $query = FarmTransaction::where('account_id', $request->account_id)
            ->whereBetween('transaction_date', [
                $request->from ?? now()->subMonth(),
                $request->to ?? now()
            ]);

        if ($request->farm_id) {
            $query->where('farm_id', $request->farm_id);
        }

        $income = (clone $query)->where('type', 'income')->sum('amount');
        $expense = (clone $query)->where('type', 'expense')->sum('amount');
        $loss = (clone $query)->where('type', 'loss')->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'loss' => $loss,
            'profit' => $income - $expense - $loss
        ];
    }
}
