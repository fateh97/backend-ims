<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Exports\FinancialReportExport;
use Maatwebsite\Excel\Facades\Excel;

class InventoryLogController extends Controller
{
    public function index()
    {
        return InventoryLog::with('product')->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'type' => 'required|in:OUT'
        ]);

        $items = $request->items;
        $reference = "";

        // 1. Determine Reference Number
        if (count($items) === 1) {
            $product = Product::with('inventoryTypes')->find($items[0]['product_id']);
            $prefix = strtoupper($product->inventoryTypes->prefix ?? 'ITEM');
        } else {
            $prefix = 'MUL';
        }

        $count = InventoryLog::where('ref', 'like', $prefix . '-%')->count();
        $reference = $prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // 2. Process each item in the transaction
        foreach ($items as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);

            InventoryLog::create([
                'product_id' => $product->id,
                'type'       => 'OUT',
                'qty'        => $itemData['qty'],
                'ref'        => $reference,
                'attachment' => null,
            ]);

            $product->decrement('stock', $itemData['qty']);
        }

        return response()->json([
            'message' => 'Transaction successful',
            'ref' => $reference
        ], 201);
    }

    public function exportFinancialReport()
    {
        return Excel::download(new FinancialReportExport, 'financial_report.xlsx');
    }
}
