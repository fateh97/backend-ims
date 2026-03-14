<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryLogController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/add-products', [ProductController::class, 'store']);
Route::get('/logs', [InventoryLogController::class, 'index']);
Route::post('/add-logs', [InventoryLogController::class, 'store']);
