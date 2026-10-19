<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function orderIndex(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Order::where('user_id', $request->user()->getAuthIdentifier())->with('items')->latest()->get()->map(fn (Order $order) => $this->orderData($order))]);
    }

    public function orderShow(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->where('order_number', $orderNumber)->with('items')->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->orderData($order)]);
    }

    public function orderCreate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'customerName' => ['required', 'string', 'max:120'], 'customerPhone' => ['required', 'string', 'max:40'],
            'deliveryAddress' => ['required', 'string', 'max:1000'], 'paymentMethod' => ['required', 'in:COD'],
            'orderNotes' => ['nullable', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.productId' => ['required', 'integer', 'min:1'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'promoCode' => ['nullable', 'string', 'max:64'],
        ]);
        $key = $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        [$order, $replay] = $this->orders->place($request->user(), $input, $key);

        return response()->json(['success' => true, 'data' => $this->orderData($order)], $replay ? 200 : 201);
    }

    public function orderCancel(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->where('order_number', $orderNumber)->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->orderData($this->orders->transition($order, 'Cancelled', true))]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $products = $request->user()->favoriteProducts()->get();

        return response()->json(['success' => true, 'data' => $products->map(fn (Product $product) => $this->foodData($product))]);
    }

    public function favoriteAdd(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $request->user()->favoriteProducts()->syncWithoutDetaching([$product->id]);

        return response()->json(['success' => true, 'data' => $this->foodData($product)]);
    }

    public function favoriteRemove(Request $request, int $productId): JsonResponse
    {
        $request->user()->favoriteProducts()->detach($productId);

        return response()->json(['success' => true]);
    }

    public function addresses(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get()->map(fn (Address $address) => $this->addressData($address))]);
    }

    public function addressCreate(Request $request): JsonResponse
    {
        $input = $this->validateAddress($request);
        $address = DB::transaction(function () use ($request, $input): Address {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($input['is_default']) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create($input);
        });

        return response()->json(['success' => true, 'data' => $this->addressData($address)], 201);
    }

    public function addressUpdate(Request $request, int $id): JsonResponse
    {
        $input = $this->validateAddress($request);
        $address = DB::transaction(function () use ($request, $id, $input): Address {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $address = Address::where('user_id', $user->id)->where('id', $id)->firstOrFail();
            if ($input['is_default']) {
                $user->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
            }
            $address->update($input);

            return $address;
        });

        return response()->json(['success' => true, 'data' => $this->addressData($address->refresh())]);
    }

    public function addressDelete(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id): void {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Address::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail()->delete();
        });

        return response()->json(['success' => true]);
    }

    private function validateAddress(Request $request): array
    {
        $input = $request->validate(['label' => ['required', 'string', 'max:80'], 'recipientName' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:40'], 'address' => ['required', 'string', 'max:1000'], 'isDefault' => ['sometimes', 'boolean']]);

        return ['label' => $input['label'], 'recipient_name' => $input['recipientName'], 'phone' => $input['phone'], 'address' => $input['address'], 'is_default' => $input['isDefault'] ?? false];
    }

    private function addressData(Address $address): array
    {
        return ['id' => $address->id, 'label' => $address->label, 'recipientName' => $address->recipient_name, 'phone' => $address->phone, 'address' => $address->address, 'isDefault' => (bool) $address->is_default];
    }

    private function orderData(Order $order): array
    {
        return [
            'orderNumber' => $order->order_number, 'status' => $order->status, 'createdAt' => $order->created_at?->toISOString(),
            'customerName' => $order->customer_name, 'deliveryAddress' => $order->delivery_address, 'customerPhone' => $order->customer_phone,
            'paymentMethod' => $order->payment_method, 'orderNotes' => $order->order_notes, 'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount, 'deliveryFee' => (float) $order->delivery_fee, 'totalAmount' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($item) => ['productId' => $item->product_id, 'name' => $item->name, 'price' => (float) $item->price, 'quantity' => $item->quantity, 'subtotal' => (float) $item->subtotal])->values(),
        ];
    }

    private function foodData(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category, 'price' => (float) $product->price, 'rating' => (float) $product->rating, 'reviewsCount' => $product->reviews_count, 'prepTime' => $product->prep_time, 'tag' => $product->tag, 'description' => $product->description, 'image' => $product->image, 'available' => (bool) $product->available, 'stock' => $product->stock];
    }
}
