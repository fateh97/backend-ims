<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class InventoryLog extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['product_id', 'type', 'qty', 'ref', 'attachment'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
