<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = ['code', 'discount_percent', 'description', 'min_spend', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'min_spend' => 'decimal:2'];
    }
}
