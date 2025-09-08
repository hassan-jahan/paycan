<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['prices' => function($query) {
            $query->active();
        }]);

        if ($request->has('active')) {
            $query->active();
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'products' => $products,
            'available_types' => Product::getTypes()
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['prices.orders', 'prices.subscriptions']);
        
        return response()->json([
            'product' => $product,
            'stats' => [
                'total_orders' => $product->prices->sum(fn($price) => $price->orders->count()),
                'total_subscriptions' => $product->prices->sum(fn($price) => $price->subscriptions->count()),
                'active_prices_count' => $product->activePrices()->count()
            ]
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product = Product::create($data);
        
        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('prices')
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        
        if (isset($data['name']) && (!isset($data['slug']) || $data['slug'] === $product->slug)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);
        
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('prices')
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->prices()->whereHas('orders')->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing orders'
            ], 422);
        }

        $product->delete();
        
        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        
        return response()->json([
            'message' => 'Product restored successfully',
            'product' => $product->load('prices')
        ]);
    }
}
