<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * Bütün tokenlərin siyahısı (admin)
     * GET /api/tokens
     */
    public function index(Request $request): JsonResponse
    {
        $query = PartnerToken::with('partner:id,company_name');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('token_key', 'like', "%{$search}%")
                  ->orWhereHas('partner', fn($pq) => $pq->where('company_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        $tokens = $query->orderBy('created_at', 'desc')->get();

        return response()->json($tokens);
    }

    /**
     * Yeni token yarat
     * POST /api/tokens
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'token_type' => 'required|in:live,sandbox',
            'expires_at' => 'nullable|date|after:today',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
        ]);

        $result = PartnerToken::generate(
            $request->partner_id,
            $request->token_type,
            $request->expires_at,
            $request->ip_whitelist
        );

        return response()->json([
            'message' => __('api.token_created'),
            'token' => $result['token']->load('partner:id,company_name'),
            'plain_token' => $result['plain_token'],
        ], 201);
    }

    /**
     * Token ləğv et
     * PUT /api/tokens/{id}/revoke
     */
    public function revoke($id): JsonResponse
    {
        $token = PartnerToken::findOrFail($id);
        $token->update(['status' => 'revoked']);

        return response()->json([
            'message' => __('api.token_revoked'),
            'token' => $token,
        ]);
    }

    /**
     * Token yenilə (rotate) — köhnəni ləğv et, yenisini yarat
     * POST /api/tokens/{id}/rotate
     */
    public function rotate($id): JsonResponse
    {
        $oldToken = PartnerToken::findOrFail($id);
        $oldToken->update(['status' => 'revoked']);

        $result = PartnerToken::generate(
            $oldToken->partner_id,
            $oldToken->token_type,
            $oldToken->expires_at?->toDateString(),
            $oldToken->ip_whitelist
        );

        return response()->json([
            'message' => __('api.token_rotated'),
            'old_token_id' => $oldToken->id,
            'token' => $result['token']->load('partner:id,company_name'),
            'plain_token' => $result['plain_token'],
        ]);
    }

    /**
     * Toplu ləğv
     * POST /api/tokens/batch-revoke
     */
    public function batchRevoke(Request $request): JsonResponse
    {
        $request->validate([
            'token_ids' => 'required|array|min:1',
            'token_ids.*' => 'integer|exists:partner_tokens,id',
        ]);

        PartnerToken::whereIn('id', $request->token_ids)
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        return response()->json([
            'message' => __('api.tokens_batch_revoked', ['count' => count($request->token_ids)]),
        ]);
    }

    /**
     * Partnerin öz tokenləri
     * GET /api/partner/tokens
     */
    public function myTokens(Request $request): JsonResponse
    {
        $tokens = PartnerToken::where('partner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tokens);
    }
}
