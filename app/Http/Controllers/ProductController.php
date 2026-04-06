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

    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Not found'], 404);

        $data = $request->validate([
            'name' => 'required',
            'sku' => 'required|unique:products,sku,' . $id,
            'stock' => 'required|integer',
            'price' => 'required|numeric',
        ]);

        $product->update($data);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::find($id);
    
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->delete();
        
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
