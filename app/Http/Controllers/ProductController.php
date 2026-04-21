<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {

        $products = Product::with(['brand', 'inventoryTypes'])->get();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'brand_id' => 'required|exists:brands,id',
            'inventory_type_id' => 'required|exists:inventory_types,id',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'supplier_price' => 'required|numeric',
        ]);

        $product = Product::create($data);
        return response()->json($product->load(['brand', 'inventoryTypes']), 201);;
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Not found'], 404);

        $data = $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'supplier_price' => 'required|numeric',
            'brand_id' => 'required|exists:brands,id',
            'inventory_type_id' => 'required|exists:inventory_types,id',
        ]);

        $product->update($data);
        return response()->json($product->load(['brand', 'inventoryTypes']));
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

    public function restock(Request $request, $id)
    {
        $request->validate([
            'added_stock' => 'required|integer|min:1',
            'supplier_price' => 'required|numeric',
            'attachment' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $product = Product::findOrFail($id);

        $fileName = $request->file('attachment')->getClientOriginalName();
        $request->file('attachment')->move(public_path('uploads'), $fileName);

        $product->update([
            'stock' => $product->stock + $request->added_stock,
            'supplier_price' => $request->supplier_price,
        ]);
        // 3. Create Inventory Log
        InventoryLog::create([
            'product_id' => $id,
            'type'       => 'IN',
            'qty'        => $request->added_stock,
            'ref'        => 'Supplier Restock',
            'supplier_price' => $request->supplier_price, // Store price at the time of restock
            'attachment' => $fileName,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']));
    }
}
