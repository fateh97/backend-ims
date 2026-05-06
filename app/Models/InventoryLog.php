<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class InventoryLog extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['product_name', 'type', 'qty', 'ref', 'attachment', 'created_by', 'service_name', 'service_price', 'accessory', 'supplier_price', 'price'];

     public function product(){
        return $this->belongsTo(Product::class);
     }

    public function users()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}