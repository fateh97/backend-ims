<?php

namespace App\Http\Controllers;

use App\Exports\FinancialReportExport;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InventoryLogController extends Controller
{
    public function index()
    {
        return InventoryLog::with('users')->orderBy('created_at', 'desc')->get();
    }

    public function customerInvoice(Request $request)
    {
        $request->validate([
            'items' => 'array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'maintenance' => 'array',
            'maintenance.*.desc' => 'required|string',
            'maintenance.*.price' => 'required|numeric',
            'type' => 'required|in:OUT',
        ]);

        $items = $request->items ?? [];
        $maintenance = $request->maintenance ?? [];
        $user = $request->created_by;

        foreach ($items as $itemData) {
            $product = Product::find($itemData['product_id']);
            if ($product->stock < $itemData['qty']) {
                return response()->json([
                    'message' => "Insufficient stock for {$product->name}. Current stock: {$product->stock}",
                ], 422);
            }
        }

        return DB::transaction(function () use ($items, $maintenance, $user) {

            if (count($items) === 1 && count($maintenance) === 0) {
                $product = Product::with('inventoryTypes')->find($items[0]['product_id']);
                $prefix = strtoupper($product->inventoryTypes->prefix ?? 'ITEM');
            } else {
                $prefix = 'MULTI';
            }

            $count = InventoryLog::where('ref', 'like', $prefix.'-%')->count();
            $reference = $prefix.'-'.str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            // Process Physical Items
            foreach ($items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                InventoryLog::create([
                    'product_name' => $product->name,
                    'type' => 'OUT',
                    'qty' => $itemData['qty'],
                    'ref' => "Customer Invoice: $reference",
                    'price' => $product->price,
                    'created_by' => $user, 
                    'accessory' => $product->inventoryTypes->accessory ?? 0
                ]);

                $product->decrement('stock', $itemData['qty']);
            }

            foreach ($maintenance as $service) {
                InventoryLog::create([
                    'product_name' => null,
                    'type' => 'OUT',
                    'qty' => 1,
                    'ref' => "Service: $reference",
                    'service_name' => $service['desc'],
                    'service_price' => $service['price'],
                    'created_by' => $user,
                ]);
            }

            return response()->json(['ref' => $reference], 201);
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'qty' => 'required|integer',
            'type' => 'required|in:IN,OUT',
        ]);

        $fileName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            if ($file->isValid()) {
                $fileName = $file->getClientOriginalName();

                $file->move(public_path('uploads'), $fileName);
            }
        }

        if ($request->filled('product_name')) {
            $product = Product::firstOrCreate(
                ['name' => $request->product_name, 'brand_id' => $request->brand_id ?? null, 'inventory_type_id' => $request->inventory_type_id ?? null, 'attachment' => $fileName],
                ['price' => 0, 'stock' => 0, 'supplier_price' => $request->unit_price ?? 0]
            );
            $productId = $product->id;
        } 

        $log = InventoryLog::create([
            'product_name' => $request->product_name,
            'type' => $request->type,
            'qty' => $request->qty,
            'ref' => 'Supplier Stock',
            'attachment' => $fileName,
            'created_by' => $request->created_by,
            'supplier_price' => $request->unit_price ?? 0,
            'accessory' => $product->inventoryTypes->accessory ?? 0,
        ]);

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