<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Admin login
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.invalid_credentials')],
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'role' => 'admin',
        ]);
    }

    // Partner login
    public function partnerLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $partner = Partner::where('email', $request->email)->first();

        if (!$partner || !Hash::check($request->password, $partner->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.invalid_credentials')],
            ]);
        }

        if ($partner->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => [__('api.account_inactive')],
            ]);
        }

        $token = $partner->createToken('partner-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $partner->id,
                'company_name' => $partner->company_name,
                'contact_name' => $partner->contact_name,
                'email' => $partner->email,
                'plan' => $partner->plan,
                'status' => $partner->status,
            ],
            'token' => $token,
            'role' => 'partner',
        ]);
    }

    // Admin me
    public function adminMe(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
        ]);
    }

    // Partner me
    public function partnerMe(Request $request)
    {
        $partner = $request->user();

        // İcazəli kateqoriyalar (translated_name ilə)
        $allowedCategories = $partner->allowedCategories()
            ->select('categories_1688.id', 'categories_1688.category_id', 'categories_1688.chinese_name', 'categories_1688.translated_name', 'categories_1688.parent_category_id', 'categories_1688.leaf')
            ->get();

        // İcazəli endpoint-lər
        $allowedEndpoints = $partner->allowedEndpoints()->pluck('endpoint')->values();

        return response()->json([
            'id' => $partner->id,
            'company_name' => $partner->company_name,
            'contact_name' => $partner->contact_name,
            'email' => $partner->email,
            'plan' => $partner->plan,
            'status' => $partner->status,
            'payment_model' => $partner->payment_model,
            'deposit_balance' => $partner->deposit_balance,
            'debit_limit' => $partner->debit_limit,
            'debit_used' => $partner->debit_used,
            'outstanding_balance' => $partner->outstanding_balance,
            'request_limit_monthly' => $partner->request_limit_monthly,
            'allow_negative' => $partner->allow_negative,
            'can_place_order' => $partner->canPlaceOrder(),
            'allowed_categories' => $allowedCategories,
            'allowed_endpoints' => $allowedEndpoints,
            'role' => 'partner',
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('api.logged_out')]);
    }
}
