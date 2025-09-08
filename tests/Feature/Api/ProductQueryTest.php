<?php

use App\Models\Product;

it('can list only active products with pagination', function () {
    Product::factory()->count(2)->create(['is_active' => true]);
    Product::factory()->count(1)->create(['is_active' => false]); // Should not appear

    $response = $this->getJson('/api/query/products');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'type',
                    'is_active',
                    'created_at',
                ],
            ],
            'current_page',
            'per_page',
            'total',
        ]);

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBe(2);
});

it('can filter products by type', function () {
    Product::factory()->create(['type' => 'physical', 'is_active' => true]);
    Product::factory()->create(['type' => 'digital', 'is_active' => true]);

    $response = $this->getJson('/api/query/products?filter[type]=physical');

    $response->assertSuccessful();
    $products = $response->json('data');
    expect($products)->toHaveCount(1);
    expect($products[0]['type'])->toBe('physical');
});

it('can filter active products', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/query/products?filter[is_active]=1');

    $response->assertSuccessful();
    $products = $response->json('data');
    expect($products)->toHaveCount(1);
    expect($products[0]['is_active'])->toBeTrue();
});

it('can include prices relationship', function () {
    $product = Product::factory()->create();

    $response = $this->getJson('/api/query/products?include=prices');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'prices',
                ],
            ],
        ]);
});

it('can sort products by title', function () {
    Product::factory()->create(['title' => 'Z Product']);
    Product::factory()->create(['title' => 'A Product']);

    $response = $this->getJson('/api/query/products?sort=title');

    $response->assertSuccessful();
    $products = $response->json('data');
    expect($products[0]['title'])->toBe('A Product');
});

it('can show single active product with includes', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/query/products/{$product->id}?include=prices");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'id',
            'title',
            'prices',
        ]);
});

it('cannot show inactive products', function () {
    $product = Product::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/query/products/{$product->id}");

    $response->assertNotFound();
});
