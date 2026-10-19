<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'category', 'price', 'rating', 'reviews_count', 'prep_time', 'tag', 'description', 'image', 'available', 'stock'];

    protected $casts = ['available' => 'boolean', 'price' => 'decimal:2', 'rating' => 'decimal:1'];
}
