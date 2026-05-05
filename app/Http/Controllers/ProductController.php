<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\Product;
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
            'created_by' => 'integer',
        ]);

        $product = Product::create($data);

        $product->load(['brand', 'inventoryTypes']);

        InventoryLog::create([
            'product_name' => $product->name,
            'type' => 'IN',
            'qty' => $product->stock,
            'created_by' => $data['created_by'],
            'ref' => 'Initial Product',
            'accessory' => $product->inventoryTypes->accessory ?? 0,
            'supplier_price' => $product->supplier_price,
            'price' => $product->price,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (! $product) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'supplier_price' => 'required|numeric',
            'brand_id' => 'required|exists:brands,id',
            'inventory_type_id' => 'required|exists:inventory_types,id',
            'created_by' => 'integer',
        ]);

        $product->update($data);

        $product->load(['brand', 'inventoryTypes']);

        InventoryLog::create([
            'product_name' => $data['name'],
            'type' => 'EDIT',
            'qty' => $data['stock'],
            'ref' => 'Product Update',
            'created_by' => $data['created_by'],
            'accessory' => $product->inventoryTypes->accessory ?? 0,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']));
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        $product->load(['brand', 'inventoryTypes']);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        InventoryLog::create([
            'product_name' => $product->name,
            'type' => 'DELETE',
            'ref' => 'Product Deletion',
            'accessory' => $product->inventoryTypes->accessory ?? 0,
        ]);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function restock(Request $request, $id)
    {
        $request->validate([
            'added_stock' => 'required|integer|min:1',
            'supplier_price' => 'required|numeric',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'created_by' => 'integer',
        ]);

        $product = Product::findOrFail($id);
        $product->load(['brand', 'inventoryTypes']);
        $fileName = null;

        if ($request->hasFile('attachment')) {
            $fileName = $request->file('attachment')->getClientOriginalName();
            $request->file('attachment')->move(public_path('uploads'), $fileName);
        }

        $product->update([
            'stock' => $product->stock + $request->added_stock,
            'supplier_price' => $request->supplier_price,
        ]);

        InventoryLog::create([
            'product_name' => $product->name,
            'type' => 'IN',
            'qty' => $request->added_stock,
            'ref' => 'Product Restock',
            'attachment' => $fileName,
            'created_by' => $request->created_by,
            'accessory' => $product->inventoryTypes->accessory ?? 0,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']));
    }
}
