<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Prodotti attivi con categoria
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $favoriteIds = [];
        if ($user) {
            $favoriteIds = $user->productFavorites()->pluck('product_id')->toArray();
        }

        $products = Product::with(['category' => function ($q) {
            $q->where('is_active', true);
        }])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => $p->category) // filtra prodotti senza categoria attiva
            ->values()
            ->map(function ($product) use ($favoriteIds) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category->name ?? null,
                    'category_id' => $product->category_id,
                    'image' => $product->image ? Storage::url($product->image) : null,
                    'is_favorite' => in_array($product->id, $favoriteIds),
                ];
            });

        return response()->json([
            'status' => true,
            'products' => $products,
        ]);
    }
}
