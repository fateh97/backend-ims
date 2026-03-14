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
        $log = InventoryLog::create([
            'product_id' => $request->product_id,
            'type'       => $request->type,
            'qty'        => $request->qty,
            'ref'        => $request->ref,
        ]);

        $product = Product::find($request->product_id);

        if($product){
            if ($request->type === 'OUT') {
                $product->decrement('stock', $request->qty);
            } else {
                $product->increment('stock', $request->qty);
            }
        }

        return response()->json($log->load('product'), 201);
    }
}
