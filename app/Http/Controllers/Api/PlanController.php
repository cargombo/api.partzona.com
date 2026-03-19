<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return response()->json(Plan::orderBy('price_monthly')->get());
    }

    public function show(int $id)
    {
        return response()->json(Plan::findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:plans,name',
            'display_name' => 'required|string',
            'rpm_limit' => 'required|integer|min:0',
            'daily_limit' => 'required|integer|min:0',
            'monthly_limit' => 'required|integer|min:0',
            'max_concurrent' => 'required|integer|min:1',
            'max_categories' => 'nullable|integer|min:1',
            'sandbox' => 'boolean',
            'ip_whitelist' => 'boolean',
            'webhook' => 'boolean',
            'sla' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
        ]);

        $plan = Plan::create($validated);
        return response()->json($plan, 201);
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:plans,name,' . $id,
            'display_name' => 'sometimes|string',
            'rpm_limit' => 'sometimes|integer|min:0',
            'daily_limit' => 'sometimes|integer|min:0',
            'monthly_limit' => 'sometimes|integer|min:0',
            'max_concurrent' => 'sometimes|integer|min:1',
            'max_categories' => 'nullable|integer|min:1',
            'sandbox' => 'sometimes|boolean',
            'ip_whitelist' => 'sometimes|boolean',
            'webhook' => 'sometimes|boolean',
            'sla' => 'nullable|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,archived',
        ]);

        $plan->update($validated);
        return response()->json($plan);
    }

    public function destroy(int $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['status' => 'archived']);
        return response()->json(['message' => __('api.plan_archived')]);
    }
}
