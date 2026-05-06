<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\Brand;
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
            'ref' => 'Product Added',
            'accessory' => $product->inventoryTypes->accessory ?? 0,
            'supplier_price' => $product->supplier_price,
            'price' => $product->price,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'supplier_price' => 'required|numeric',
            'brand_id' => 'required|exists:brands,id',
            'inventory_type_id' => 'required|exists:inventory_types,id',
            'created_by' => 'integer',
        ]);

        // 1. Human-friendly labels for your columns
        $labels = [
            'name' => 'Product Name',
            'stock' => 'Stock Quantity',
            'price' => 'Selling Price',
            'supplier_price' => 'Cost Price',
            'brand_id' => 'Brand',
            'inventory_type_id' => 'Category Type'
        ];

        // 2. Fill the model to detect changes
        $product->fill($data);
        $changes = $product->getDirty();

        if (!empty($changes)) {
            $summary = [];

            foreach ($changes as $field => $newValue) {
                $oldValue = $product->getOriginal($field);
                $fieldName = $labels[$field] ?? $field;

                if ($field === 'brand_id') {
                    $oldBrand = Brand::find($oldValue)->name ?? 'None';
                    $newBrand = Brand::find($newValue)->name ?? 'None';
                    $summary[] = "$fieldName: $oldBrand → $newBrand";
                    continue; // Skip the rest of the loop for this field
                }

                if ($field === 'inventory_type_id') {
                    $oldType = InventoryType::find($oldValue)->name ?? 'None';
                    $newType = InventoryType::find($newValue)->name ?? 'None';
                    $summary[] = "$fieldName: $oldType → $newType";
                    continue;
                }

                if (str_contains($field, 'price')) {
                    $oldValue = 'RM' . number_format($oldValue, 2);
                    $newValue = 'RM' . number_format($newValue, 2);
                }

                $summary[] = "$fieldName: $oldValue → $newValue";
            }

            $reference = "Modified: " . implode(' | ', $summary);

            // 3. Create the Log
            InventoryLog::create([
                'product_name' => $product->name,
                'type' => 'EDIT',
                'qty' => $product->stock,
                'ref' => $reference,
                'created_by' => $data['created_by'],
                'accessory' => $product->inventoryTypes->accessory ?? 0,
            ]);

            $product->save();
        }

        return response()->json($product->load(['brand', 'inventoryTypes']));
    }

    public function destroy(Request $request, $id)
    {
        // 1. Find the product and its relationship
        $product = Product::with('inventoryTypes')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $userId = $request->input('created_by');

        try {
            // 2. Prepare values safely before deletion
            $pName = $product->name;
            $isAccessory = optional($product->inventoryTypes)->accessory ?? 0;

            // 3. Create the Log using product_name
            InventoryLog::create([
                'product_name' => $pName, // Storing name as a string
                'type'         => 'DELETE',
                'ref'          => "Permanent Deletion: " . $pName,
                'qty'          => $product->stock,
                'supplier_price' => $product->supplier_price,
                'price'        => $product->price,
                'created_by'   => $userId,
                'qty'          => 0,
                'accessory'    => $isAccessory,
            ]);

            // 4. Delete the product
            $product->delete();

            return response()->json(['message' => 'Product deleted successfully']);

        } catch (\Exception $e) {
            // Return the exact error if it fails again
            return response()->json([
                'message' => 'Log creation or deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
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

        InventoryLog::create([
            'product_name' => $product->name,
            'type' => 'IN',
            'qty' => $request->added_stock,
            'ref' => "Product Restock: $product->name",
            'attachment' => $fileName,
            'created_by' => $request->created_by,
            'supplier_price' => $request->supplier_price,
            'accessory' => $product->inventoryTypes->accessory ?? 0,
        ]);

        $product->update([
            'stock' => $product->stock + $request->added_stock,
            'supplier_price' => $request->supplier_price,
        ]);

        return response()->json($product->load(['brand', 'inventoryTypes']));
    }
}