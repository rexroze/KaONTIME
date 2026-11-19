<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function place(User $user, array $input, string $key): array
    {
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($user, $input, $key, $hash): array {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->request_hash, $hash)) {
                        abort(409, 'Idempotency key already used with a different request.');
                    }

                    return [$existing->load('items'), true];
                }

                if (! filter_var(StoreSetting::getValue('store_open', '1'), FILTER_VALIDATE_BOOLEAN)) {
                    abort(409, 'The store is currently closed. Please try again later.');
                }

                $quantities = collect($input['items'])->groupBy('productId')->map(fn (Collection $rows) => $rows->sum('quantity'));
                $products = Product::whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($products->count() !== $quantities->count()) {
                    throw ValidationException::withMessages(['items' => ['One or more products do not exist.']]);
                }

                $subtotal = 0.0;
                foreach ($quantities as $id => $quantity) {
                    $product = $products[$id];
                    if (! $product->available || $product->stock < $quantity) {
                        throw ValidationException::withMessages(['items' => ["Insufficient stock for {$product->name}."]]);
                    }
                    $subtotal += (float) $product->price * $quantity;
                }

                $promo = null;
                if (! empty($input['promoCode'])) {
                    $promo = PromoCode::where('code', Str::upper(trim($input['promoCode'])))->where('active', true)->lockForUpdate()->first();
                    if (! $promo || $subtotal < (float) $promo->min_spend) {
                        throw ValidationException::withMessages(['promoCode' => ['Invalid promo code or minimum spend requirement not met.']]);
                    }
                }

                $discount = round($subtotal * (($promo?->discount_percent ?? 0) / 100), 2);
                $fee = (float) StoreSetting::getValue('delivery_fee', config('app.order_delivery_fee', 50));
                $order = Order::create([
                    'user_id' => $user->id, 'idempotency_key' => $key, 'request_hash' => $hash,
                    'order_number' => 'KAON-'.strtoupper(Str::random(10)), 'customer_name' => $input['customerName'],
                    'customer_phone' => $input['customerPhone'], 'delivery_address' => $input['deliveryAddress'],
                    'payment_method' => 'COD', 'order_notes' => $input['orderNotes'] ?? null, 'subtotal' => $subtotal,
                    'discount' => $discount, 'delivery_fee' => $fee, 'total_amount' => max(0, $subtotal - $discount) + $fee,
                    'status' => 'Pending', 'promo_code' => $promo?->code,
                ]);
                foreach ($quantities as $id => $quantity) {
                    $product = $products[$id];
                    if (Product::whereKey($id)->where('available', true)->where('stock', '>=', $quantity)->decrement('stock', $quantity) !== 1) {
                        throw ValidationException::withMessages(['items' => ["Insufficient stock for {$product->name}."]]);
                    }
                }
                foreach ($input['items'] as $item) {
                    $product = $products[$item['productId']];
                    $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => $item['quantity'], 'subtotal' => (float) $product->price * $item['quantity']]);
                }

                return [$order->load('items'), false];
            }, 3);
        } catch (QueryException $exception) {
            $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    abort(409, 'Idempotency key already used with a different request.');
                }

                return [$existing->load('items'), true];
            }
            Log::warning('Order persistence failure', [
                'sqlstate' => $exception->errorInfo[0] ?? 'unknown',
                'stage' => 'checkout_transaction',
            ]);
            throw $exception;
        }
    }

    public function transition(Order $order, string $next, bool $customerCancellation = false): Order
    {
        return DB::transaction(function () use ($order, $next, $customerCancellation): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($customerCancellation) {
                abort_unless($next === 'Cancelled' && $order->status === 'Pending', 409, 'Only pending orders can be cancelled by the customer.');
            }
            if (in_array($order->status, ['Delivered', 'Cancelled'], true)) {
                abort(409, 'Terminal order status cannot be changed.');
            }
            if ($next === 'Cancelled') {
                if (! in_array($order->status, ['Pending', 'Preparing'], true)) {
                    abort(409, 'This order can no longer be cancelled.');
                }
                if ($order->getAttribute('stock_restored_at')) {
                    abort(409, 'Stock for this order has already been restored.');
                }
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        DB::table('products')->where('id', $item->product_id)->increment('stock', $item->quantity);
                    }
                }
                Order::whereKey($order->id)->update(['status' => 'Cancelled', 'stock_restored_at' => now()]);
            } else {
                $allowed = ['Pending' => ['Preparing'], 'Preparing' => ['Out for Delivery'], 'Out for Delivery' => ['Delivered']];
                abort_unless(in_array($next, $allowed[$order->status] ?? [], true), 409, 'Invalid order status transition.');
                Order::whereKey($order->id)->update(['status' => $next]);
            }

            return $order->fresh()->load('items');
        }, 3);
    }
}
