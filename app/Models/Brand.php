<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Brand extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name'];
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
