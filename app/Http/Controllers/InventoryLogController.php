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
        return InventoryLog::with('product.inventoryTypes')->orderBy('created_at', 'desc')->get();
    }

    public function customerInvoice(Request $request)
    {
        $request->validate([
            'items' => 'array', // Both Products and Accessories come in here
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'maintenance' => 'array',
            'maintenance.*.desc' => 'required|string',
            'maintenance.*.price' => 'required|numeric',
            'type' => 'required|in:OUT'
        ]);

        $items = $request->items ?? [];
        $maintenance = $request->maintenance ?? [];
        
        if (count($items) === 1 && count($maintenance) === 0) {
            $product = Product::with('inventoryType')->find($items[0]['product_id']);
            $prefix = strtoupper($product->inventoryType->prefix ?? 'ITEM');
        } else {
            $prefix = 'MULTI';
        }

        $count = InventoryLog::where('ref', 'like', $prefix . '-%')->count();
        $reference = $prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        foreach ($items as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            InventoryLog::create([
                'product_id' => $product->id,
                'type'       => 'OUT',
                'qty'        => $itemData['qty'],
                'ref'        => $reference,
            ]);
            $product->decrement('stock', $itemData['qty']);
        }

        // Process Maintenance (Services)
        foreach ($maintenance as $service) {
            InventoryLog::create([
                'product_id'    => null,
                'type'          => 'OUT',
                'qty'           => 1,
                'ref'           => $reference,
                'service_name'  => $service['desc'],
                'service_price' => $service['price'],
            ]);
        }

        return response()->json(['ref' => $reference], 201);
    }

    public function store(Request $request)
    {
        $request->validate([
            'qty' => 'required|integer',
            'type' => 'required|in:IN,OUT',
        ]);

        if ($request->filled('product_name')) {
            $product = Product::firstOrCreate(
                ['name' => $request->product_name, 'brand_id' => $request->brand_id ?? null, 'inventory_type_id' => $request->inventory_type_id ?? null],
                ['price' => 0, 'stock' => 0, 'supplier_price' => $request->unit_price ?? 0]
            );
            $productId = $product->id;
        } else {
            $productId = $request->product_id;
            $product = Product::findOrFail($productId);
        }

        $fileName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            if ($file->isValid()) {
                $fileName = $file->getClientOriginalName();

                $file->move(public_path('uploads'), $fileName);
            }
        }

        $log = InventoryLog::create([
            'product_id' => $productId,
            'type'       => $request->type,
            'qty'        => $request->qty,
            'ref'        => $request->ref,
            'attachment' => $fileName, 
        ]);

        // 4. Update Stock
        if ($request->type === 'OUT') {
            $product->decrement('stock', $request->qty);
        } else {
            $product->increment('stock', $request->qty);
        }

        return response()->json($log->load('product'), 201);
    }

    public function exportFinancialReport()
    {
        return Excel::download(new FinancialReportExport, 'WBM_Financial_Reports.xlsx');
    }
}
