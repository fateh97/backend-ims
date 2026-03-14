<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json([
            Product::all()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'sku' => 'required|unique:products',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
        ]);

        $product = Product::create($data);
        return response()->json($product, 201);
    }
}
