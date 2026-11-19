<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\OrderService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $this->recordAudit($request, 'product.created', 'product', $product->id, ['name' => $product->name]);

        return response()->json(['success' => true, 'data' => $this->foodData($product)], 201);
    }

    public function productUpdate(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $before = collect($product->getAttributes())->only(['name', 'price', 'available', 'stock', 'image'])->all();
        $product->update($this->validateProduct($request));
        $changes = collect($product->getChanges())->except('updated_at')->all();
        $this->recordAudit($request, 'product.updated', 'product', $product->id, [
            'changes' => $changes,
            'before' => array_intersect_key($before, $changes),
        ]);

        return response()->json(['success' => true, 'data' => $this->foodData($product->refresh())]);
    }

    public function productDelete(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['available' => false]);
        $product->delete();
        $this->recordAudit($request, 'product.deleted', 'product', $product->id, ['name' => $product->name]);

        return response()->json(['success' => true]);
    }

    public function productStock(Request $request, int $id): JsonResponse
    {
        $input = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ]);
        $product = Product::findOrFail($id);
        $product->update($input);
        $this->recordAudit($request, 'product.stock_updated', 'product', $product->id, $input);

        return response()->json(['success' => true, 'data' => $this->foodData($product->refresh())]);
    }

    public function dashboard(): JsonResponse
    {
        $today = [now()->startOfDay(), now()->endOfDay()];
        $todayOrders = Order::query()->whereBetween('created_at', $today);
        $todayRevenue = Order::query()
            ->whereBetween('created_at', $today)
            ->whereNotIn('status', ['Cancelled'])
            ->sum('total_amount');

        return response()->json(['success' => true, 'data' => [
            'todayOrders' => (clone $todayOrders)->count(),
            'todayRevenue' => (float) $todayRevenue,
            'pendingOrders' => Order::query()->where('status', 'Pending')->count(),
            'preparingOrders' => Order::query()->where('status', 'Preparing')->count(),
            'totalCustomers' => User::query()->where('is_admin', false)->count(),
            'lowStockCount' => Product::query()->where('stock', '<=', 5)->count(),
            'recentOrders' => Order::query()->with('items')->latest()->limit(5)->get()->map(fn (Order $order) => $this->orderData($order)),
        ]]);
    }

    public function orders(Request $request): JsonResponse
    {
        $input = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled', 'All'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = Order::query()->with('items');
        if (! empty($input['status']) && $input['status'] !== 'All') {
            $query->where('status', $input['status']);
        }
        if (($search = trim((string) ($input['search'] ?? ''))) !== '') {
            $term = '%'.Str::lower($search).'%';
            $query->whereRaw(
                '(lower(order_number) like ? or lower(customer_name) like ? or lower(customer_phone) like ?)',
                [$term, $term, $term],
            );
        }
        if (! empty($input['from'])) {
            $query->whereDate('created_at', '>=', $input['from']);
        }
        if (! empty($input['to'])) {
            $query->whereDate('created_at', '<=', $input['to']);
        }

        $paginator = $query->latest()->paginate((int) ($input['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $this->orderPage($paginator),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function status(Request $request, string $orderNumber): JsonResponse
    {
        $input = $request->validate(['status' => ['required', Rule::in(['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'])]]);
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $previousStatus = $order->status;
        $order = $this->orders->transition($order, $input['status']);
        $this->recordAudit($request, 'order.status_changed', 'order', $order->id, [
            'orderNumber' => $order->order_number,
            'from' => $previousStatus,
            'to' => $order->status,
        ]);

        return response()->json(['success' => true, 'data' => $this->orderData($order->fresh()->load('items'))]);
    }

    public function reports(Request $request): JsonResponse
    {
        $input = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $from = $input['from'] ?? null;
        $to = $input['to'] ?? null;
        $orders = Order::query()
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));
        $statusCounts = (clone $orders)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $top = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'Cancelled')
            ->when($from, fn ($query) => $query->whereDate('orders.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('orders.created_at', '<=', $to))
            ->select('order_items.name')->selectRaw('sum(order_items.quantity) as quantity')->groupBy('order_items.name')->orderByDesc('quantity')->limit(5)->get();

        return response()->json(['success' => true, 'data' => [
            'totalOrders' => (clone $orders)->count(), 'pendingOrders' => (clone $orders)->where('status', 'Pending')->count(),
            'demoRevenue' => (float) (clone $orders)->whereNotIn('status', ['Cancelled'])->sum('total_amount'),
            'topDishes' => $top->map(fn ($row) => ['name' => $row->name, 'quantity' => (int) $row->quantity])->values(),
            'lowStock' => Product::where('stock', '<=', 5)->get()->map(fn (Product $product) => $this->foodData($product)),
            'statusCounts' => $statusCounts,
        ]]);
    }

    public function promos(): JsonResponse
    {
        $promos = PromoCode::query()->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn (PromoCode $promo) => $this->promoData($promo));

        return response()->json(['success' => true, 'data' => $promos]);
    }

    public function promoCreate(Request $request): JsonResponse
    {
        $input = $this->validatePromo($request);
        $promo = PromoCode::create($input);
        $this->recordAudit($request, 'promo.created', 'promo', $promo->id, ['code' => $promo->code]);

        return response()->json(['success' => true, 'data' => $this->promoData($promo)], 201);
    }

    public function promoUpdate(Request $request, int $id): JsonResponse
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update($this->validatePromo($request, $promo->id, (bool) $promo->active));
        $this->recordAudit($request, 'promo.updated', 'promo', $promo->id, ['code' => $promo->code]);

        return response()->json(['success' => true, 'data' => $this->promoData($promo->refresh())]);
    }

    public function promoDelete(Request $request, int $id): JsonResponse
    {
        $promo = PromoCode::findOrFail($id);
        $promo->delete();
        $this->recordAudit($request, 'promo.deleted', 'promo', $promo->id, ['code' => $promo->code]);

        return response()->json(['success' => true]);
    }

    public function customers(Request $request): JsonResponse
    {
        $input = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = User::query()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($orders) => $orders->where('status', '!=', 'Cancelled')], 'total_amount')
            ->withMax(['orders as last_order_at' => fn ($orders) => $orders], 'created_at');
        if (($search = trim((string) ($input['search'] ?? ''))) !== '') {
            $term = '%'.Str::lower($search).'%';
            $query->whereRaw('(lower(name) like ? or lower(email) like ?)', [$term, $term]);
        }
        $paginator = $query->orderBy('name')->paginate((int) ($input['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn (User $user) => $this->customerData($user))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function customerAdmin(Request $request, int $id): JsonResponse
    {
        $input = $request->validate(['isAdmin' => ['required', 'boolean']]);
        $target = User::findOrFail($id);
        if (! $input['isAdmin'] && (int) $target->id === (int) $request->user()->getAuthIdentifier()) {
            abort(409, 'You cannot remove your own admin access.');
        }
        if (! $input['isAdmin'] && $target->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            abort(409, 'At least one administrator must remain.');
        }
        $target->forceFill(['is_admin' => (bool) $input['isAdmin']])->save();
        $this->recordAudit($request, 'customer.admin_toggled', 'user', $target->id, [
            'email' => $target->email,
            'isAdmin' => (bool) $target->is_admin,
        ]);

        return response()->json(['success' => true, 'data' => $this->customerData($target->refresh())]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->settingsData()]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'deliveryFee' => ['required', 'numeric', 'min:0', 'max:9999'],
            'storeOpen' => ['required', 'boolean'],
            'storeMessage' => ['nullable', 'string', 'max:160'],
        ]);
        StoreSetting::putValue('delivery_fee', $input['deliveryFee']);
        StoreSetting::putValue('store_open', (bool) $input['storeOpen']);
        StoreSetting::putValue('store_message', $input['storeMessage'] ?? '');
        $this->recordAudit($request, 'settings.updated', 'settings', null, [
            'deliveryFee' => (float) $input['deliveryFee'],
            'storeOpen' => (bool) $input['storeOpen'],
            'storeMessage' => $input['storeMessage'] ?? '',
        ]);

        return response()->json(['success' => true, 'data' => $this->settingsData()]);
    }

    public function categories(): JsonResponse
    {
        $categories = Product::query()
            ->select('category')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => ['name' => $row->category, 'productCount' => (int) $row->aggregate]);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function auditLogs(): JsonResponse
    {
        $logs = AdminAuditLog::query()->with('user')->latest('created_at')->limit(100)->get()->map(fn (AdminAuditLog $log) => [
            'id' => $log->id,
            'actor' => $log->user?->name ?? 'System',
            'action' => $log->action,
            'subjectType' => $log->subject_type,
            'subjectId' => $log->subject_id,
            'meta' => $log->meta,
            'createdAt' => $log->created_at?->toISOString(),
        ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request): array
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($request->route('id'))],
            'category' => ['required', 'string', 'max:100'], 'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'], 'reviewsCount' => ['sometimes', 'integer', 'min:0'],
            'prepTime' => ['required', 'string', 'max:80'], 'tag' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'image' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                $image = trim((string) $value);
                if (preg_match('/\A(?:images\/)?[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/i', $image) === 1) {
                    return;
                }
                $scheme = parse_url($image, PHP_URL_SCHEME);
                if (is_string($scheme) && strtolower($scheme) === 'https' && filter_var($image, FILTER_VALIDATE_URL) !== false && is_string(parse_url($image, PHP_URL_HOST))) {
                    return;
                }
                $fail('The image must be a safe filename or an absolute https URL.');
            }],
            'available' => ['sometimes', 'boolean'], 'stock' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'name' => $input['name'], 'category' => $input['category'], 'price' => $input['price'],
            'rating' => $input['rating'] ?? 5, 'reviews_count' => $input['reviewsCount'] ?? 0,
            'prep_time' => $input['prepTime'], 'tag' => $input['tag'] ?? null,
            'description' => $input['description'], 'image' => trim($input['image']),
            'available' => $input['available'] ?? true, 'stock' => $input['stock'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePromo(Request $request, ?int $ignoreId = null, bool $defaultActive = true): array
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $input = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('promo_codes', 'code')->ignore($ignoreId)],
            'discountPercent' => ['required', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'minSpend' => ['required', 'numeric', 'min:0', 'max:999999'],
            'active' => ['sometimes', 'boolean'],
        ]);

        return [
            'code' => $input['code'], 'discount_percent' => $input['discountPercent'],
            'description' => $input['description'] ?? null, 'min_spend' => $input['minSpend'],
            'active' => $input['active'] ?? $defaultActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordAudit(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null, array $meta = []): void
    {
        AdminAuditLog::create([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsData(): array
    {
        return [
            'deliveryFee' => (float) StoreSetting::getValue('delivery_fee', config('app.order_delivery_fee', 50)),
            'storeOpen' => (bool) StoreSetting::getValue('store_open', '1'),
            'storeMessage' => (string) StoreSetting::getValue('store_message', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isAdmin' => (bool) $user->is_admin,
            'ordersCount' => (int) ($user->orders_count ?? 0),
            'totalSpent' => (float) ($user->total_spent ?? 0),
            'lastOrderAt' => $user->last_order_at ? Carbon::parse($user->last_order_at)->toISOString() : null,
            'createdAt' => $user->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promoData(PromoCode $promo): array
    {
        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'discountPercent' => (int) $promo->discount_percent,
            'description' => $promo->description,
            'minSpend' => (float) $promo->min_spend,
            'active' => (bool) $promo->active,
            'createdAt' => $promo->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderPage(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->map(fn (Order $order) => $this->orderData($order))->values()->all();
    }

    /**
     * @return array{currentPage: int, lastPage: int, total: int, perPage: int}
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'perPage' => $paginator->perPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function foodData(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category, 'price' => (float) $product->price, 'rating' => (float) $product->rating, 'reviewsCount' => $product->reviews_count, 'prepTime' => $product->prep_time, 'tag' => $product->tag, 'description' => $product->description, 'image' => $product->image, 'available' => (bool) $product->available, 'stock' => $product->stock];
    }

    /**
     * @return array<string, mixed>
     */
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
