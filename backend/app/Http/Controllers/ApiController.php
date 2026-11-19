<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PromoCode;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function hello(): JsonResponse
    {
        return response()->json(['message' => config('app.api_hello_message', 'Hello World')]);
    }

    public function health(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json(['status' => 'ok', 'database' => 'connected']);
        } catch (\Throwable) {
            return response()->json(['status' => 'error', 'database' => 'unavailable'], 503);
        }
    }

    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'deliveryFee' => (float) StoreSetting::getValue('delivery_fee', config('app.order_delivery_fee', 50)),
            'storeOpen' => (bool) StoreSetting::getValue('store_open', '1'),
            'storeMessage' => (string) StoreSetting::getValue('store_message', ''),
        ]]);
    }

    public function menu(Request $request): JsonResponse
    {
        $query = Product::query()->where('available', true);
        if (($category = $request->query('category')) && $category !== 'All') {
            $query->where('category', $category);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%"));
        }
        match ($request->query('sort')) {
            'rating' => $query->orderByDesc('rating'), 'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'), 'popular' => $query->orderByDesc('reviews_count'), default => $query->orderBy('id'),
        };
        $products = $query->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'price' => (float) $product->price,
            'rating' => (float) $product->rating,
            'reviewsCount' => $product->reviews_count,
            'prepTime' => $product->prep_time,
            'tag' => $product->tag,
            'description' => $product->description,
            'image' => $product->image,
            'available' => (bool) $product->available,
            'stock' => $product->stock,
        ]);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function validatePromo(Request $request): JsonResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:64'], 'subtotal' => ['required', 'numeric', 'min:0']]);
        $promo = PromoCode::where('code', Str::upper(trim($input['code'])))->where('active', true)->first();
        if (! $promo) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired promo code'], 404);
        }
        if ((float) $input['subtotal'] < (float) $promo->min_spend) {
            return response()->json(['success' => false, 'message' => 'Minimum spend requirement not met'], 422);
        }

        return response()->json(['success' => true, 'data' => ['code' => $promo->code, 'discountPercent' => (int) $promo->discount_percent]]);
    }
}
