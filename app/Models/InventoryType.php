<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class InventoryType extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'accessory', 'prefix'];

    protected $casts = [
        'accessory' => 'boolean', // Update the cast as well
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
