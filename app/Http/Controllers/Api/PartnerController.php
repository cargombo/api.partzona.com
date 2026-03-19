<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerEndpointPerm;
use App\Models\Plan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    /**
     * Partner siyahısı (filter + search)
     */
    public function index(Request $request)
    {
        $query = Partner::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->plan);
        }

        if ($request->filled('payment_model')) {
            $query->where('payment_model', $request->payment_model);
        }

        $partners = $query->orderBy('created_at', 'desc')->get();

        // Password-u admin üçün göstər amma hash-siz saxla
        // (real app-da plain password saxlanmaz, bu dev üçündür)
        return response()->json($partners);
    }

    /**
     * Partner detayı (permissions daxil)
     */
    public function show(string $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $partner->toArray();
        $data['allowed_category_ids'] = $partner->allowedCategories()->pluck('categories_1688.category_id')->map(fn($v) => (int) $v)->values();
        $data['allowed_endpoints'] = $partner->allowedEndpoints()->pluck('endpoint')->values();

        return response()->json($data);
    }

    /**
     * Yeni partner yarat
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|unique:partners,email',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'plan_id' => 'required|exists:plans,id',
            'payment_model' => 'required|in:deposit,debit',
            'deposit_balance' => 'nullable|numeric|min:0',
            'debit_limit' => 'nullable|numeric|min:0',
            'rpm_limit' => 'nullable|integer|min:0',
            'daily_limit' => 'nullable|integer|min:0',
            'monthly_limit' => 'nullable|integer|min:0',
            'max_concurrent' => 'nullable|integer|min:1',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'allow_negative' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Plan-a görə default limitləri DB-dən oxu
        $plan = Plan::find($validated['plan_id']);
        $defaults = $plan ? [
            'rpm_limit' => $plan->rpm_limit,
            'daily_limit' => $plan->daily_limit,
            'monthly_limit' => $plan->monthly_limit,
            'max_concurrent' => $plan->max_concurrent,
        ] : ['rpm_limit' => 10, 'daily_limit' => 500, 'monthly_limit' => 5000, 'max_concurrent' => 5];

        // Şifrə avtomatik generasiya (max 10 simvol)
        $plainPassword = Str::random(8) . rand(10, 99);

        $partner = Partner::create([
            ...$validated,
            'password' => Hash::make($plainPassword),
            'plain_password' => $plainPassword,
            'status' => 'active',
            'approved_at' => now(),
            'deposit_balance' => $validated['deposit_balance'] ?? 0,
            'debit_limit' => $validated['debit_limit'] ?? 0,
            'debit_used' => 0,
            'outstanding_balance' => 0,
            'rpm_limit' => $validated['rpm_limit'] ?? $defaults['rpm_limit'],
            'daily_limit' => $validated['daily_limit'] ?? $defaults['daily_limit'],
            'monthly_limit' => $validated['monthly_limit'] ?? $defaults['monthly_limit'],
            'max_concurrent' => $validated['max_concurrent'] ?? $defaults['max_concurrent'],
            'allow_negative' => $validated['allow_negative'] ?? false,
        ]);

        return response()->json([
            'partner' => $partner,
            'generated_password' => $plainPassword,
        ], 201);
    }

    /**
     * Partner güncəllə
     */
    public function update(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'contact_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:partners,email,' . $id,
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'status' => 'sometimes|in:pending,active,suspended,rejected',
            'plan_id' => 'sometimes|exists:plans,id',
            'payment_model' => 'sometimes|in:deposit,debit',
            'deposit_balance' => 'sometimes|numeric',
            'debit_limit' => 'sometimes|numeric|min:0',
            'rpm_limit' => 'sometimes|integer|min:0',
            'daily_limit' => 'sometimes|integer|min:0',
            'monthly_limit' => 'sometimes|integer|min:0',
            'max_concurrent' => 'sometimes|integer|min:1',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'allow_negative' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        // Status active olduqda approved_at təyin et
        if (isset($validated['status']) && $validated['status'] === 'active' && !$partner->approved_at) {
            $validated['approved_at'] = now();
        }

        // Plan dəyişdikdə limitləri avtomatik yenilə
        if (isset($validated['plan_id']) && $validated['plan_id'] != $partner->plan_id) {
            $newPlan = Plan::find($validated['plan_id']);
            if ($newPlan) {
                $validated['rpm_limit'] = $validated['rpm_limit'] ?? $newPlan->rpm_limit;
                $validated['daily_limit'] = $validated['daily_limit'] ?? $newPlan->daily_limit;
                $validated['monthly_limit'] = $validated['monthly_limit'] ?? $newPlan->monthly_limit;
                $validated['max_concurrent'] = $validated['max_concurrent'] ?? $newPlan->max_concurrent;
            }
        }

        $partner->update($validated);

        return response()->json($partner->fresh());
    }

    /**
     * Partner şifrəsini dəyiş
     */
    public function updatePassword(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $partner->update([
            'password' => Hash::make($request->password),
            'plain_password' => $request->password,
        ]);

        return response()->json(['message' => __('api.password_updated')]);
    }

    /**
     * Partner sil
     */
    public function destroy(string $id)
    {
        $partner = Partner::findOrFail($id);
        $partner->tokens()->delete();
        $partner->delete();

        return response()->json(['message' => __('api.partner_deleted')]);
    }

    /**
     * Depozit əlavə et
     */
    public function addDeposit(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);

        $partner->deposit_balance += $request->amount;
        $partner->save();

        Transaction::create([
            'partner_id' => $partner->id,
            'amount' => $request->amount,
            'type' => 'deposit',
            'description' => $request->description ?? __('api.deposit_default_desc'),
            'reference_type' => 'manual',
            'balance_after' => $partner->deposit_balance,
        ]);

        return response()->json([
            'message' => __('api.deposit_added'),
            'new_balance' => $partner->deposit_balance,
        ]);
    }

    /**
     * Ödəniləcək məbləği yenilə (yalnız admin)
     */
    public function updateOutstanding(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $partner->outstanding_balance = $request->amount;
        $partner->save();

        return response()->json([
            'message' => __('api.outstanding_updated'),
            'outstanding_balance' => $partner->outstanding_balance,
        ]);
    }

    /**
     * Limit sayğaclarını sıfırla
     */
    public function resetLimits(string $id)
    {
        $partner = Partner::findOrFail($id);

        // api_logs-dan uğurlu sorğuları silmirik, amma Cache-i sıfırlayırıq
        // Limit yoxlaması üçün ayrı counter cədvəli istifadə edirik
        // Əslində api_logs COUNT hesablayır, ona görə reset = bu gündən sonrakı sorğuları sıfırlamaq üçün
        // Ən sadə həll: partner-ə reset_at timestamp yazaq

        $partner->update(['limits_reset_at' => now()]);

        // Cache-i təmizlə
        \Illuminate\Support\Facades\Cache::forget("partner_rpm:{$partner->id}");
        \Illuminate\Support\Facades\Cache::forget("partner_daily:{$partner->id}:" . now()->format('Y-m-d'));
        \Illuminate\Support\Facades\Cache::forget("partner_monthly:{$partner->id}:" . now()->format('Y-m'));

        return response()->json([
            'message' => __('api.limits_reset'),
            'reset_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Partner icazələrini yenilə (categories + endpoints)
     */
    public function updatePermissions(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'category_ids' => 'present|array',
            'category_ids.*' => 'integer',
            'endpoints' => 'present|array',
            'endpoints.*' => 'string',
        ]);

        // Kateqoriya icazələri — sync
        $partner->allowedCategories()->sync(
            collect($request->category_ids)->mapWithKeys(fn($catId) => [
                $catId => ['created_at' => now(), 'updated_at' => now()]
            ])->all()
        );

        // Endpoint icazələri — sil + yenidən yarat
        $partner->allowedEndpoints()->delete();
        foreach ($request->endpoints as $endpoint) {
            $partner->allowedEndpoints()->create(['endpoint' => $endpoint]);
        }

        return response()->json([
            'message' => __('api.permissions_updated'),
            'allowed_category_ids' => $partner->allowedCategories()->pluck('categories_1688.category_id')->map(fn($v) => (int) $v)->values(),
            'allowed_endpoints' => $partner->allowedEndpoints()->pluck('endpoint')->values(),
        ]);
    }

    /**
     * Partner sifariş verə bilirmi yoxla
     */
    public function checkOrderAbility(string $id)
    {
        $partner = Partner::findOrFail($id);

        return response()->json([
            'can_place_order' => $partner->canPlaceOrder(),
            'payment_model' => $partner->payment_model,
            'deposit_balance' => $partner->deposit_balance,
            'debit_limit' => $partner->debit_limit,
            'debit_used' => $partner->debit_used,
            'allow_negative' => $partner->allow_negative,
        ]);
    }

    /**
     * Partner sifarişləri
     */
    public function partnerOrders(Request $request, string $id)
    {
        $query = \App\Models\Order::where('partner_id', $id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_id', 'like', "%{$s}%")
                  ->orWhere('out_order_id', 'like', "%{$s}%");
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Partner öz sifarişləri (portal)
     */
    public function myOrders(Request $request)
    {
        $partner = $request->user();
        $query = \App\Models\Order::where('partner_id', $partner->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_id', 'like', "%{$s}%")
                  ->orWhere('out_order_id', 'like', "%{$s}%");
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Bütün sifarişlər (admin)
     */
    public function allOrders(Request $request)
    {
        $query = \App\Models\Order::with('partner:id,company_name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_id', 'like', "%{$s}%")
                  ->orWhere('out_order_id', 'like', "%{$s}%");
            });
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }
}
