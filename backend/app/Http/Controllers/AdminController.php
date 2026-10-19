<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function products(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Product::query()->orderBy('id')->get()->map(fn (Product $product) => $this->foodData($product))]);
    }

    public function productCreate(Request $request): JsonResponse
    {
        $product = Product::create($this->validateProduct($request));

        return response()->json(['success' => true, 'data' => $this->foodData($product)], 201);
    }

    public function productUpdate(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($this->validateProduct($request));

        return response()->json(['success' => true, 'data' => $this->foodData($product->refresh())]);
    }

    public function productDelete(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['available' => false]);
        $product->delete();

        return response()->json(['success' => true]);
    }

    public function orders(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Order::with('items')->latest()->get()->map(fn (Order $order) => $this->orderData($order))]);
    }

    public function status(Request $request, string $orderNumber): JsonResponse
    {
        if (! (bool) DB::table('users')->where('id', $request->user()->getAuthIdentifier())->value('is_admin')) {
            abort(403, 'Administrator access required.');
        }
        $input = $request->validate(['status' => ['required', Rule::in(['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'])]]);
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $order = $this->orders->transition($order, $input['status']);

        return response()->json(['success' => true, 'data' => $this->orderData($order->fresh()->load('items'))]);
    }

    public function reports(): JsonResponse
    {
        $statusCounts = Order::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $top = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', '!=', 'Cancelled')->select('order_items.name')->selectRaw('sum(order_items.quantity) as quantity')->groupBy('order_items.name')->orderByDesc('quantity')->limit(5)->get();
        $orders = Order::query();

        return response()->json(['success' => true, 'data' => [
            'totalOrders' => (clone $orders)->count(), 'pendingOrders' => (clone $orders)->where('status', 'Pending')->count(),
            'demoRevenue' => (float) (clone $orders)->whereNotIn('status', ['Cancelled'])->sum('total_amount'),
            'topDishes' => $top->map(fn ($row) => ['name' => $row->name, 'quantity' => (int) $row->quantity])->values(),
            'lowStock' => Product::where('stock', '<=', 5)->get()->map(fn (Product $product) => $this->foodData($product)),
            'statusCounts' => $statusCounts,
        ]]);
    }

    private function validateProduct(Request $request): array
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($request->route('id'))],
            'category' => ['required', 'string', 'max:100'], 'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'], 'reviewsCount' => ['sometimes', 'integer', 'min:0'],
            'prepTime' => ['required', 'string', 'max:80'], 'tag' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'], 'image' => ['required', 'string', 'max:255', 'regex:/\A(?:images\/)?[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/i'],
            'available' => ['sometimes', 'boolean'], 'stock' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'name' => $input['name'], 'category' => $input['category'], 'price' => $input['price'],
            'rating' => $input['rating'] ?? 5, 'reviews_count' => $input['reviewsCount'] ?? 0,
            'prep_time' => $input['prepTime'], 'tag' => $input['tag'] ?? null,
            'description' => $input['description'], 'image' => $input['image'],
            'available' => $input['available'] ?? true, 'stock' => $input['stock'],
        ];
    }

    private function foodData(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category, 'price' => (float) $product->price, 'rating' => (float) $product->rating, 'reviewsCount' => $product->reviews_count, 'prepTime' => $product->prep_time, 'tag' => $product->tag, 'description' => $product->description, 'image' => $product->image, 'available' => (bool) $product->available, 'stock' => $product->stock];
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
}
