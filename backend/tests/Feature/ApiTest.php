<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::create(['name' => 'Test', 'email' => 'test@example.test', 'password' => bcrypt('password123')]), 'sanctum');
    }

    public function test_hello_message_uses_configured_default(): void
    {
        config(['app.api_hello_message' => 'Hello World']);
        $this->getJson('/api/hello')->assertOk()->assertExactJson(['message' => 'Hello World']);
    }

    public function test_menu_filters_and_preserves_image_names(): void
    {
        $response = $this->getJson('/api/menu?category=Burgers&sort=price-asc')->assertOk()->assertJsonPath('success', true)->assertJsonCount(2, 'data');
        $expectedKeys = ['id', 'name', 'category', 'price', 'rating', 'reviewsCount', 'prepTime', 'tag', 'description', 'image', 'available', 'stock'];
        $this->assertSame($expectedKeys, array_keys($response->json('data.0')));
        $this->assertTrue(is_int($response->json('data.0.price')) || is_float($response->json('data.0.price')));
        $this->assertIsFloat($response->json('data.0.rating'));
        $response->assertJsonPath('data.0.image', 'images/burger.jpg')->assertJsonPath('data.0.price', 249)
            ->assertJsonPath('data.0.rating', 4.9)->assertJsonPath('data.0.reviewsCount', 142)->assertJsonPath('data.0.prepTime', '15-20 min');
    }

    public function test_promo_validation_reports_discount_and_minimum(): void
    {
        $this->postJson('/api/promo/validate', ['code' => ' welcome10 ', 'subtotal' => 250])->assertOk()
            ->assertJsonPath('data.code', 'WELCOME10')->assertJsonPath('data.discountPercent', 10);
        $this->postJson('/api/promo/validate', ['code' => 'WELCOME10', 'subtotal' => 10])->assertUnprocessable();
    }

    public function test_order_totals_are_server_calculated_and_client_tampering_ignored(): void
    {
        $product = Product::where('name', 'Signature Angus Cheeseburger')->firstOrFail();
        $response = $this->withHeader('Idempotency-Key', 'da66266c-b7a5-4d3b-a966-0c11aefc9e65')->postJson('/api/orders', $this->validOrder($product->id, 2) + [
            'subtotal' => 0, 'discount' => 99999, 'totalAmount' => 1, 'deliveryFee' => 0,
        ])->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.subtotal', 498)
            ->assertJsonPath('data.discount', 99.6)->assertJsonPath('data.deliveryFee', 50)->assertJsonPath('data.totalAmount', 448.4);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 23]);
        $this->assertDatabaseHas('orders', ['order_number' => $response->json('data.orderNumber')]);
    }

    public function test_validation_and_stock_errors_do_not_create_partial_orders_or_decrement_stock(): void
    {
        $product = Product::where('name', 'Signature Angus Cheeseburger')->firstOrFail();
        $another = Product::where('name', 'Artisan Truffle Pepperoni Pizza')->firstOrFail();
        $order = $this->validOrder($product->id, 1);
        $order['items'][] = ['productId' => $another->id, 'quantity' => 19];
        $this->withHeader('Idempotency-Key', '21d3bbac-92f3-4db4-b522-7d66bb71f02a')->postJson('/api/orders', $order)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 25]);
        $this->assertDatabaseHas('products', ['id' => $another->id, 'stock' => 18]);

        // Repeated product rows must be aggregated for stock validation, not checked independently.
        $duplicateItems = $this->validOrder($another->id, 10);
        $duplicateItems['items'][] = ['productId' => $another->id, 'quantity' => 9];
        $this->withHeader('Idempotency-Key', '10d5e6cb-bbb6-49b0-ae4f-e5beb07d9822')->postJson('/api/orders', $duplicateItems)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $another->id, 'stock' => 18]);
        $this->withHeader('Idempotency-Key', '7b398842-9705-491b-a8a8-0e4c3da14c96')->postJson('/api/orders', ['customerName' => 'X', 'items' => []])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    private function validOrder(int $productId, int $quantity): array
    {
        return ['customerName' => 'Test Customer', 'customerPhone' => '09123456789', 'deliveryAddress' => '1 Test Street',
            'paymentMethod' => 'COD', 'orderNotes' => null, 'items' => [['productId' => $productId, 'quantity' => $quantity]], 'promoCode' => 'KAONTIME'];
    }
}
