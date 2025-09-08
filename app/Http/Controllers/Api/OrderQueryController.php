<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OrderQueryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $orders = QueryBuilder::for(Order::class)
            ->where('user_id', auth()->id())
            ->select(['id', 'user_id', 'order_number', 'status', 'total', 'currency', 'created_at', 'updated_at', 'product_price_id'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::scope('created_after'),
                AllowedFilter::scope('created_before'),
                'order_number',
            ])
            ->allowedIncludes([
                'user',
                'productPrice',
                'productPrice.product',
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('total'),
                AllowedSort::field('status'),
            ])
            ->defaultSort('-created_at')
            ->paginate(request('per_page', 10))
            ->appends(request()->query());

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            abort(403, 'Access denied');
        }

        $order = QueryBuilder::for(Order::where('id', $order->id))
            ->select(['id', 'user_id', 'order_number', 'status', 'total', 'currency', 'created_at', 'updated_at', 'product_price_id'])
            ->allowedIncludes([
                'user',
                'productPrice',
                'productPrice.product',
            ])
            ->first();

        return response()->json($order);
    }
}
