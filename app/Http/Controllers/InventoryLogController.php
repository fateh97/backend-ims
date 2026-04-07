<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryLog;
use App\Models\Product;

class InventoryLogController extends Controller
{
    public function index()
    {
        return InventoryLog::with('product')->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        // 1. Validate - make sure product_id and qty are actually there
        $request->validate([
            'qty' => 'required|integer',
            'type' => 'required|in:IN,OUT',
        ]);

        // Handle Product (FirstOrCreate logic if you are using the Name approach)
        // Or just find it if you are using product_id
        if ($request->filled('product_name')) {
            $product = Product::firstOrCreate(
                ['name' => $request->product_name],
                ['sku' => 'AUTO-' . time(), 'price' => 0, 'stock' => $request->qty]
            );
            $productId = $product->id;
        } else {
            $productId = $request->product_id;
            $product = Product::findOrFail($productId);
        }

        // 2. THE FIX: Only try to store if a file is present and valid
        $fileName = null;
        
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            
            if ($file->isValid()) {
                $fileName = $file->getClientOriginalName();
                
                $file->move(public_path('uploads'), $fileName);
            }
        }

        // 3. Create Log
        $log = InventoryLog::create([
            'product_id' => $productId,
            'type'       => $request->type,
            'qty'        => $request->qty,
            'ref'        => $request->ref,
            'attachment' => $fileName, // This will now safely be null if no file was uploaded
        ]);

        // 4. Update Stock
        if ($request->type === 'OUT') {
            $product->decrement('stock', $request->qty);
        } else {
            $product->increment('stock', $request->qty);
        }

        return response()->json($log->load('product'), 201);
    }
}
