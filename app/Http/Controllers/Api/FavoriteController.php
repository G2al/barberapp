<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * GET /api/favorites
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $favorites = $user->productFavorites()
            ->with('category')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'category' => $p->category->name ?? null,
                    'image' => $p->image ? \Illuminate\Support\Facades\Storage::url($p->image) : null,
                ];
            });

        return response()->json([
            'status' => true,
            'favorites' => $favorites,
        ]);
    }

    /**
     * POST /api/favorites/{product}
     */
    public function store(Request $request, Product $product)
    {
        $user = $request->user();
        $user->productFavorites()->syncWithoutDetaching([$product->id]);

        return response()->json(['status' => true]);
    }

    /**
     * DELETE /api/favorites/{product}
     */
    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();
        $user->productFavorites()->detach($product->id);

        return response()->json(['status' => true]);
    }
}
