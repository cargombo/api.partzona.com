<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::all();

        $rates = [];
        foreach ($currencies as $currency) {
            $key = $currency->from_currency . '_' . $currency->to_currency;
            $rates[$key] = (float) $currency->rate;
        }

        return response()->json([
            'rates' => $rates,
            'updated_at' => $currencies->first()?->updated_at,
        ]);
    }
}
