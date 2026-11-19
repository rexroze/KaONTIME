<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_dashboard_or_customers(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/customers')->assertForbidden();
    }

    public function test_dashboard_returns_expected_shape_and_totals(): void
    {
        [$admin, $token] = $this->admin();
        foreach (range(1, 3) as $index) {
            User::factory()->create(['email' => "customer{$index}@example.test"]);
        }
        Product::create($this->productAttributes(['name' => 'Low Stock Dish', 'stock' => 2]));
        foreach (range(1, 6) as $index) {
            $this->createOrder($admin, "KAON-DASH{$index}", $index === 1 ? 'Pending' : 'Delivered', 100);
        }
        $this->createOrder($admin, 'KAON-CANCELLED', 'Cancelled', 500);

        $response = $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk();
        $this->assertSame(
            ['todayOrders', 'todayRevenue', 'pendingOrders', 'preparingOrders', 'totalCustomers', 'lowStockCount', 'recentOrders'],
            array_keys($response->json('data')),
        );
        $response->assertJsonPath('data.todayOrders', 7)
            ->assertJsonPath('data.todayRevenue', 600)
            ->assertJsonPath('data.pendingOrders', 1)
            ->assertJsonPath('data.preparingOrders', 0)
            ->assertJsonPath('data.totalCustomers', 3)
            ->assertJsonPath('data.lowStockCount', 1)
            ->assertJsonCount(5, 'data.recentOrders');
    }

    public function test_admin_orders_support_status_search_and_pagination(): void
    {
        [$admin, $token] = $this->admin();
        $this->createOrder($admin, 'KAON-AAA', 'Pending', 10, 'Alice');
        $this->createOrder($admin, 'KAON-BBB', 'Delivered', 20, 'Bob');
        $this->createOrder($admin, 'KAON-CCC', 'Pending', 30, 'Alicia');

        $this->withToken($token)->getJson('/api/admin/orders?status=Pending&search=ali&per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.perPage', 1);

        $this->withToken($token)->getJson('/api/admin/orders?status=Delivered')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.orderNumber', 'KAON-BBB')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_promo_crud_normalises_codes_and_validates_uniqueness(): void
    {
        [, $token] = $this->admin();
        $created = $this->withToken($token)->postJson('/api/admin/promos', [
            'code' => ' flash ',
            'discountPercent' => 25,
            'description' => 'Flash sale',
            'minSpend' => 100,
            'active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'FLASH')->assertJsonPath('data.discountPercent', 25);
        $id = $created->json('data.id');

        $this->withToken($token)->postJson('/api/admin/promos', [
            'code' => 'flash', 'discountPercent' => 10, 'minSpend' => 0,
        ])->assertUnprocessable();

        $this->withToken($token)->putJson("/api/admin/promos/{$id}", [
            'code' => 'FLASH', 'discountPercent' => 30, 'description' => null, 'minSpend' => 50, 'active' => false,
        ])->assertOk()->assertJsonPath('data.discountPercent', 30)->assertJsonPath('data.active', false);

        $this->withToken($token)->getJson('/api/admin/promos')->assertOk()->assertJsonPath('data.0.code', 'FLASH');
        $this->withToken($token)->deleteJson("/api/admin/promos/{$id}")->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('promo_codes', ['id' => $id]);
    }

    public function test_settings_update_changes_checkout_delivery_fee(): void
    {
        [, $adminToken] = $this->admin();
        $this->withToken($adminToken)->putJson('/api/admin/settings', [
            'deliveryFee' => 75,
            'storeOpen' => true,
            'storeMessage' => 'Open for lunch',
        ])->assertOk()
            ->assertJsonPath('data.deliveryFee', 75)
            ->assertJsonPath('data.storeOpen', true)
            ->assertJsonPath('data.storeMessage', 'Open for lunch');

        $this->withToken($adminToken)->getJson('/api/admin/settings')
            ->assertOk()->assertJsonPath('data.deliveryFee', 75);

        $this->getJson('/api/settings')->assertOk()->assertJsonPath('data.deliveryFee', 75);

        $customer = User::factory()->create();
        $product = Product::create($this->productAttributes());
        Auth::forgetGuards();
        $this->withToken($customer->createToken('test')->plainTextToken)
            ->withHeader('Idempotency-Key', '4b0e2f83-0e8d-4a44-9b3f-b8f30a1f6d21')
            ->postJson('/api/orders', [
                'customerName' => 'Customer',
                'customerPhone' => '0912345678',
                'deliveryAddress' => 'Campus',
                'paymentMethod' => 'COD',
                'items' => [['productId' => $product->id, 'quantity' => 1]],
            ])->assertCreated()
            ->assertJsonPath('data.deliveryFee', 75)
            ->assertJsonPath('data.totalAmount', 85);
    }

    public function test_customer_can_be_promoted_then_demoted(): void
    {
        [, $token] = $this->admin();
        $customer = User::factory()->create();

        $this->withToken($token)->patchJson("/api/admin/customers/{$customer->id}/admin", ['isAdmin' => true])
            ->assertOk()->assertJsonPath('data.isAdmin', true);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_admin' => true]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'customer.admin_toggled', 'subject_id' => $customer->id]);

        $this->withToken($token)->patchJson("/api/admin/customers/{$customer->id}/admin", ['isAdmin' => false])
            ->assertOk()->assertJsonPath('data.isAdmin', false);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_admin' => false]);
    }

    public function test_last_admin_protection_returns_409(): void
    {
        [$admin, $token] = $this->admin();

        $this->withToken($token)->patchJson("/api/admin/customers/{$admin->id}/admin", ['isAdmin' => false])
            ->assertStatus(409)
            ->assertJsonPath('message', 'You cannot remove your own admin access.');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
    }

    public function test_product_image_accepts_https_url_and_rejects_javascript_url(): void
    {
        [, $token] = $this->admin();
        $payload = $this->apiProductPayload(['name' => 'Remote Image Dish', 'image' => ' https://cdn.example.com/dishes/dish.jpg ']);

        $this->withToken($token)->postJson('/api/admin/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.image', 'https://cdn.example.com/dishes/dish.jpg');

        $payload['name'] = 'Unsafe Image Dish';
        $payload['image'] = 'javascript:alert(1)';
        $this->withToken($token)->postJson('/api/admin/products', $payload)->assertUnprocessable();
    }

    public function test_checkout_is_rejected_when_the_store_is_closed(): void
    {
        [, $adminToken] = $this->admin();
        $this->withToken($adminToken)->putJson('/api/admin/settings', [
            'deliveryFee' => 50,
            'storeOpen' => false,
            'storeMessage' => 'Closed for the holiday',
        ])->assertOk()->assertJsonPath('data.storeOpen', false);

        $customer = User::factory()->create();
        $product = Product::create($this->productAttributes());
        Auth::forgetGuards();

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->withHeader('Idempotency-Key', '5b0e2f83-0e8d-4a44-9b3f-b8f30a1f6d22')
            ->postJson('/api/orders', [
                'customerName' => 'Customer',
                'customerPhone' => '0912345678',
                'deliveryAddress' => 'Campus',
                'paymentMethod' => 'COD',
                'items' => [['productId' => $product->id, 'quantity' => 1]],
            ])->assertStatus(409)
            ->assertJsonPath('message', 'The store is currently closed. Please try again later.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_reports_respect_the_requested_date_range(): void
    {
        [$admin, $token] = $this->admin();
        $this->createOrder($admin, 'KAON-TODAY', 'Delivered', 100);
        $old = $this->createOrder($admin, 'KAON-OLD', 'Delivered', 900);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->withToken($token)
            ->getJson('/api/admin/reports?from='.now()->subDay()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.totalOrders', 1)
            ->assertJsonPath('data.demoRevenue', 100);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function admin(): array
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return [$admin, $admin->createToken('test')->plainTextToken];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function apiProductPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'API Dish '.uniqid(),
            'category' => 'Rice',
            'price' => 10,
            'prepTime' => '10 min',
            'description' => 'Test',
            'image' => 'dish.jpg',
            'stock' => 10,
            'available' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Dish '.uniqid(),
            'category' => 'Rice',
            'price' => 10,
            'rating' => 5,
            'reviews_count' => 1,
            'prep_time' => '10 min',
            'description' => 'Test',
            'image' => 'dish.jpg',
            'available' => true,
            'stock' => 10,
        ], $overrides);
    }

    private function createOrder(User $user, string $number, string $status, float $total, string $customerName = 'Customer'): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'customer_name' => $customerName,
            'customer_phone' => '0912345678',
            'delivery_address' => 'Campus',
            'payment_method' => 'COD',
            'subtotal' => $total,
            'discount' => 0,
            'delivery_fee' => 0,
            'total_amount' => $total,
            'status' => $status,
        ]);
    }
}
