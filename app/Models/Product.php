<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Product extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'brand_id',
        'inventory_type_id',
        'sku',
        'stock',
        'price',
        'supplier_price'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    public function inventoryTypes()
    {
        return $this->belongsTo(InventoryType::class, 'inventory_type_id', 'id');
    }
}
