<?php

namespace App\Exports;
use App\Models\InventoryLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinancialReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        // Get all logs with product prices
        return InventoryLog::with('product')->get();
    }

    public function headings(): array
    {
        return ["Date", "Ref", "Product", "Type", "Qty", "Unit Price", "Total Amount"];
    }

    public function map($log): array
    {
        return [
            $log->created_at->format('Y-m-d H:i'),
            $log->ref,
            $log->product->name,
            $log->type, // IN or OUT
            $log->qty,
            $log->product->price,
            $log->qty * $log->product->price
        ];
    }
}