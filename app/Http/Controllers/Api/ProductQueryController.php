<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductQueryController extends Controller
{
    public function index(): JsonResponse
    {
        $products = QueryBuilder::for(Product::class)
            ->select(['id', 'title', 'slug', 'description', 'type', 'image', 'is_active', 'created_at', 'updated_at'])
            ->where('is_active', true)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                'title',
                'slug',
            ])
            ->allowedIncludes([
                'prices',
            ])
            ->allowedSorts([
                AllowedSort::field('title'),
                AllowedSort::field('created_at'),
                AllowedSort::field('type'),
            ])
            ->defaultSort('-created_at')
            ->paginate(request('per_page', 10))
            ->appends(request()->query());

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product = QueryBuilder::for(Product::where('id', $product->id))
            ->select(['id', 'title', 'slug', 'description', 'type', 'image', 'is_active', 'created_at', 'updated_at'])
            ->allowedIncludes([
                'prices',
            ])
            ->first();

        return response()->json($product);
    }
}
