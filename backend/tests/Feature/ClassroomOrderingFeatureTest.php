<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassroomOrderingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_registration_and_admin_protection(): void
    {
        $payload = ['name' => 'Student', 'email' => 'student@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => true];
        $this->postJson('/api/auth/register', $payload)->assertCreated()->assertJsonPath('data.user.isAdmin', false);
        $this->postJson('/api/auth/register', $payload)->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $payload['email'], 'password' => $payload['password']])->assertOk();
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $customer = User::where('email', $payload['email'])->firstOrFail();
        $this->actingAs($customer, 'sanctum')->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_favorites_addresses_and_order_privacy_are_owned(): void
    {
        [$user, $token] = $this->customer();
        [$other] = $this->customer('other@example.test');
        $product = $this->product();
        $this->withToken($token)->putJson("/api/favorites/{$product->id}")->assertOk();
        $this->withToken($token)->getJson('/api/favorites')->assertJsonCount(1, 'data');
        $address = ['label' => 'Home', 'recipientName' => 'Student', 'phone' => '0912345678', 'address' => 'Campus', 'isDefault' => true];
        $response = $this->withToken($token)->postJson('/api/addresses', $address)->assertCreated();
        $id = $response->json('data.id');
        $this->withToken($token)->getJson('/api/addresses')->assertJsonPath('data.0.isDefault', true);
        $otherToken = $other->createToken('test')->plainTextToken;
        Auth::forgetGuards();
        $foreignAddress = Address::findOrFail($id);
        $this->assertNotSame((int) $foreignAddress->user_id, (int) $other->id);
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'other@example.test');
        Auth::forgetGuards();
        $this->withToken($otherToken)->putJson("/api/addresses/{$id}", $address)->assertNotFound();
        Auth::forgetGuards();
        $this->withToken($token)->deleteJson("/api/favorites/{$product->id}")->assertOk();
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
        $this->assertDatabaseCount('addresses', 1);
    }

    public function test_checkout_replays_and_rejects_reused_key_with_different_body_and_restores_stock_once(): void
    {
        [$user, $token] = $this->customer();
        $product = $this->product();
        $payload = $this->orderPayload($product);
        $key = '57f93e42-0425-4f1a-ae12-73deeb3ec93d';
        $response = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/orders', $payload)->assertCreated();
        $number = $response->json('data.orderNumber');
        $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('data.orderNumber', $number);
        $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/orders', array_merge($payload, ['customerName' => 'Different']))->assertStatus(409);
        $this->assertSame(8, $product->fresh()->stock);
        $this->withToken($token)->patchJson("/api/orders/{$number}/cancel")->assertOk()->assertJsonPath('data.status', 'Cancelled');
        $this->withToken($token)->patchJson("/api/orders/{$number}/cancel")->assertStatus(409);
        $this->assertSame(10, $product->fresh()->stock);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/orders')->assertJsonPath('data.0.orderNumber', $number);
        $third = $this->customer('third@example.test')[0];
        $this->assertDatabaseHas('orders', ['order_number' => $number, 'user_id' => $user->id]);
        Auth::forgetGuards();
        $this->withToken($third->createToken('test')->plainTextToken)->getJson("/api/orders/{$number}")->assertNotFound();
    }

    public function test_checkout_validates_stock_and_admin_transitions_and_reports(): void
    {
        [$customer, $token] = $this->customer();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('password123')]);
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => true]);
        $adminToken = $admin->createToken('test')->plainTextToken;
        Auth::forgetGuards();
        $product = $this->product();
        $this->withToken($token)->withHeader('Idempotency-Key', 'e19b53a3-05c3-4d89-9a8c-12f5cf095438')->postJson('/api/orders', $this->orderPayload($product, 11))->assertUnprocessable();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', '114de8a3-bb91-4cb6-b4b3-5008c1c3bc21')->postJson('/api/orders', $this->orderPayload($product))->assertCreated();
        $number = $created->json('data.orderNumber');
        Auth::forgetGuards();
        $this->withToken($adminToken)->patchJson("/api/admin/orders/{$number}/status", ['status' => 'Preparing'])->assertOk();
        Auth::forgetGuards();
        $this->withToken($token)->patchJson("/api/orders/{$number}/cancel")->assertStatus(409);
        $this->assertDatabaseHas('orders', ['order_number' => $number, 'status' => 'Preparing']);
        Auth::forgetGuards();
        $this->withToken($adminToken)->patchJson("/api/admin/orders/{$number}/status", ['status' => 'Delivered'])->assertStatus(409);
        Auth::forgetGuards();
        $this->withToken($adminToken)->getJson('/api/admin/reports')->assertOk()->assertJsonPath('data.totalOrders', 1)->assertJsonPath('data.pendingOrders', 0);
    }

    public function test_admin_product_crud_maps_fields_validates_images_and_keeps_receipts(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'products-admin@example.test', 'password' => bcrypt('password123')]);
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => true]);
        Auth::forgetGuards();
        $token = $admin->createToken('test')->plainTextToken;
        $payload = ['name' => 'Crud Noodles', 'category' => 'Noodles', 'price' => 42, 'rating' => 4.7, 'reviewsCount' => 14, 'prepTime' => '7 min', 'description' => 'Fresh', 'image' => 'images/noodles.webp', 'available' => true, 'stock' => 9];
        $created = $this->withToken($token)->postJson('/api/admin/products', $payload)->assertCreated()->assertJsonPath('data.prepTime', '7 min')->assertJsonPath('data.reviewsCount', 14);
        $id = $created->json('data.id');
        $product = Product::findOrFail($id);
        $this->assertSame('7 min', $product->prep_time);
        $this->assertSame(14, $product->reviews_count);
        $payload['prepTime'] = '12 min';
        $payload['reviewsCount'] = 28;
        Auth::forgetGuards();
        $this->withToken($token)->putJson("/api/admin/products/{$id}", $payload)->assertOk()->assertJsonPath('data.prepTime', '12 min')->assertJsonPath('data.reviewsCount', 28);
        Auth::forgetGuards();
        $this->withToken($token)->putJson("/api/admin/products/{$id}", array_merge($payload, ['image' => '../secret.jpg']))->assertUnprocessable();

        $customer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => bcrypt('password123')]);
        $orderPayload = $this->orderPayload($product);
        $orderPayload['items'][0]['productId'] = $id;
        Auth::forgetGuards();
        $receipt = $this->withToken($customer->createToken('test')->plainTextToken)->withHeader('Idempotency-Key', 'c7cc0cd0-37d7-447d-b343-46d09120af7e')->postJson('/api/orders', $orderPayload)->assertCreated();
        $number = $receipt->json('data.orderNumber');
        Auth::forgetGuards();
        $this->withToken($token)->deleteJson("/api/admin/products/{$id}")->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id]);
        $this->assertNotNull(Product::withTrashed()->findOrFail($id)->deleted_at);
        Auth::forgetGuards();
        $this->withToken($customer->createToken('second-token')->plainTextToken)->getJson("/api/orders/{$number}")->assertOk()->assertJsonPath('data.items.0.name', 'Crud Noodles');
    }

    public function test_default_address_changes_clear_previous_default(): void
    {
        [$user, $token] = $this->customer();
        $first = ['label' => 'Home', 'recipientName' => 'Student', 'phone' => '0912345678', 'address' => 'One', 'isDefault' => true];
        $second = array_merge($first, ['label' => 'Dorm', 'address' => 'Two']);
        $this->withToken($token)->postJson('/api/addresses', $first)->assertCreated();
        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/addresses', $second)->assertCreated();
        $this->assertSame(1, DB::table('addresses')->where('user_id', $user->id)->where('is_default', true)->count());
        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'label' => 'Home', 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'label' => 'Dorm', 'is_default' => true]);
    }

    private function customer(string $email = 'customer@example.test'): array
    {
        $user = User::create(['name' => 'Customer', 'email' => $email, 'password' => bcrypt('password123')]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function product(): Product
    {
        return Product::create(['name' => 'Test Dish '.uniqid(), 'category' => 'Rice', 'price' => 10, 'rating' => 5, 'reviews_count' => 1, 'prep_time' => '10 min', 'description' => 'Test', 'image' => 'dish.jpg', 'available' => true, 'stock' => 10]);
    }

    private function orderPayload(Product $product, int $quantity = 2): array
    {
        return ['customerName' => 'Customer', 'customerPhone' => '0912345678', 'deliveryAddress' => 'Campus', 'paymentMethod' => 'COD', 'items' => [['productId' => $product->id, 'quantity' => $quantity]]];
    }
}
