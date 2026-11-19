<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $products = require database_path('seeders/data/products.php');
        $now = now();

        DB::table('products')->insertOrIgnore(array_map(fn ($product) => [...$product, 'updated_at' => $now, 'created_at' => $now], $products));
        DB::table('promo_codes')->insertOrIgnore(array_map(fn ($promo) => [...$promo, 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['code' => 'KAONTIME', 'discount_percent' => 20, 'description' => 'Flash promo 20% discount on entire order', 'min_spend' => 0],
            ['code' => 'WELCOME10', 'discount_percent' => 10, 'description' => '10% OFF for new KaONTIME customers', 'min_spend' => 200],
            ['code' => 'SEMI50', 'discount_percent' => 50, 'description' => 'Semifinal special 50% discount', 'min_spend' => 300],
        ]));

        StoreSetting::putValue('delivery_fee', 50);
        StoreSetting::putValue('store_open', true);
        StoreSetting::putValue('store_message', '');
    }
}
