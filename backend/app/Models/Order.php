<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'idempotency_key', 'request_hash', 'order_number', 'customer_name', 'customer_phone', 'delivery_address', 'payment_method', 'order_notes', 'subtotal', 'discount', 'delivery_fee', 'total_amount', 'status', 'promo_code', 'stock_restored_at'];

    protected $casts = ['stock_restored_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
