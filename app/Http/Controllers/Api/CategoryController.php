<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category1688;
use App\Services\Ali1688Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Kateqoriya siyahisi (agac strukturu)
     * GET /api/categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category1688::roots()
            ->withCount('partners')
            ->orderBy('category_id');

        if ($request->has('status')) {
            $status = $request->input('status');
            $query->where('status', $status)
                  ->with(['children' => fn($q) => $q->where('status', $status)->withCount('partners')]);
        } else {
            $query->with(['children' => fn($q) => $q->withCount('partners')]);
        }

        $categories = $query->get()->map(function ($cat) {
            $cat->partner_count = $cat->partners_count;
            if ($cat->children) {
                $cat->children->each(fn($child) => $child->partner_count = $child->partners_count);
            }
            return $cat;
        });

        return response()->json($categories);
    }

    /**
     * Tek kateqoriya
     * GET /api/categories/{id}
     */
    public function show($id): JsonResponse
    {
        $category = Category1688::with('children')->findOrFail($id);
        return response()->json($category);
    }

    /**
     * Kateqoriya statusunu deyis (active/inactive)
     * PUT /api/categories/{id}/status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $category = Category1688::findOrFail($id);
        $category->update(['status' => $request->status]);

        return response()->json([
            'message' => __('api.category_status_updated'),
            'category' => $category,
        ]);
    }

    /**
     * 1688 API-den kateqoriyalari sinxronizasiya et
     * POST /api/categories/sync
     */
    public function sync(Ali1688Service $ali1688): JsonResponse
    {
        try {
            $result = $ali1688->getCategoryList('en', '0', '0');

            // Response strukturunu yoxla
            $children = $result['result']['result']['children'] ?? null;

            if (!$children) {
                Log::error('1688 category sync: unexpected response', $result);
                return response()->json([
                    'status' => 400,
                    'message' => __('api.sync_bad_response'),
                    'raw' => $result,
                ], 400);
            }

            $synced = 0;
            $errors = 0;

            foreach ($children as $cat) {
                try {
                    Category1688::updateOrCreate(
                        ['category_id' => $cat['categoryId']],
                        [
                            'chinese_name' => $cat['chineseName'] ?? null,
                            'translated_name' => $cat['translatedName'] ?? null,
                            'parent_category_id' => $cat['parentCateId'] ?? 0,
                            'leaf' => $cat['leaf'] ?? false,
                            'level' => $cat['level'] ?? null,
                        ]
                    );
                    $synced++;

                    // Alt kateqoriyalari da cek (leaf deyilse)
                    if (!($cat['leaf'] ?? false)) {
                        $subResult = $ali1688->getCategoryList('en', (string) $cat['categoryId'], (string) $cat['categoryId']);
                        $subChildren = $subResult['result']['result']['children'] ?? [];

                        foreach ($subChildren as $subCat) {
                            try {
                                Category1688::updateOrCreate(
                                    ['category_id' => $subCat['categoryId']],
                                    [
                                        'chinese_name' => $subCat['chineseName'] ?? null,
                                        'translated_name' => $subCat['translatedName'] ?? null,
                                        'parent_category_id' => $subCat['parentCateId'] ?? $cat['categoryId'],
                                        'leaf' => $subCat['leaf'] ?? false,
                                        'level' => $subCat['level'] ?? null,
                                    ]
                                );
                                $synced++;
                            } catch (\Exception $e) {
                                Log::warning('Sub-category sync error', [
                                    'categoryId' => $subCat['categoryId'] ?? 'unknown',
                                    'error' => $e->getMessage(),
                                ]);
                                $errors++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Category sync error', [
                        'categoryId' => $cat['categoryId'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            return response()->json([
                'message' => __('api.sync_completed'),
                'synced' => $synced,
                'errors' => $errors,
                'total_in_db' => Category1688::count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Category sync failed: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => __('api.sync_error', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Sinxronizasiya statistikasi
     * GET /api/categories/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Category1688::count(),
            'active' => Category1688::where('status', 'active')->count(),
            'inactive' => Category1688::where('status', 'inactive')->count(),
            'archived' => Category1688::where('status', 'archived')->count(),
            'last_synced_at' => Category1688::max('updated_at'),
        ]);
    }
}
