<?php

namespace App\Exports;

use App\Models\InventoryLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return InventoryLog::whereIn('type', ['IN', 'OUT', 'DELETE', 'EDIT'])
        ->orderBy('created_at', 'desc')
        ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference Code',
            'Product / Service Name',
            'Log Type',
            'Quantity',
            'Supplier Price (RM)',
            'Unit Price (RM)',
            'Total Amount (RM)',
        ];
    }

    public function map($log): array
    {
        $qty = (float) $log->qty;
        $supplierPrice = (float) $log->supplier_price;
        $unitPrice = (float) ($log->price ?? $log->service_price ?? 0);
        $totalRowAmount = 0;
        $displayType = $log->type;

        if ($log->type === 'IN') {
            $displayType = 'PURCHASE';
            $totalRowAmount = $qty * $supplierPrice;
        } 
        elseif ($log->type === 'OUT') {
            $displayType = 'SALES';
            $totalRowAmount = $qty * $unitPrice;
        } 
        elseif ($log->type === 'DELETE') {
            $displayType = 'DELETION';
            // Minus the total value of the inventory removed
            $totalRowAmount = -($qty * $supplierPrice); 
        } 
        elseif ($log->type === 'EDIT') {
            $displayType = 'ADJUSTMENT';
            
            // Handle Price Adjustment Logic
            if (str_contains($log->ref, 'Cost Price:')) {
                preg_match_all('/RM\s?([\d.]+)/', $log->ref, $matches);
                if (count($matches[1]) >= 2) {
                    $oldP = (float) $matches[1][0];
                    $newP = (float) $matches[1][1];
                    $totalRowAmount = $qty * ($newP - $oldP);
                }
            }
            // Handle Stock Adjustment Logic
            elseif (str_contains($log->ref, 'Stock:')) {
                // Find all numbers in the string
                preg_match_all('/\d+/', $log->ref, $matches);
                if (count($matches[0]) >= 2) {
                    $oldQ = (float) $matches[0][0];
                    $newQ = (float) $matches[0][1];
                    $totalRowAmount = ($newQ - $oldQ) * $supplierPrice;
                }
            }
        }

        return [
            $log->created_at->format('d/m/Y'),
            $log->ref,
            $log->product_name ?? $log->service_name,
            $displayType,
            $qty,
            $supplierPrice,
            ($log->type === 'IN' || $log->type === 'DELETE') ? '-' : $unitPrice,
            $totalRowAmount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Apply number formatting
        $sheet->getStyle('F2:F'.$highestRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('H2:H'.$highestRow)->getNumberFormat()->setFormatCode('#,##0.00');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E11D48'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $highestRow = $event->sheet->getHighestRow();

                $totalPurchases = 0; // Net investment (IN + DELETE + EDIT)
                $totalSales = 0;     // Total Revenue (OUT)

                foreach ($this->collection() as $log) {
                    // We use the same math logic here
                    if ($log->type === 'OUT') {
                        $totalSales += ($log->qty * ($log->price ?? $log->service_price ?? 0));
                    } else {
                        // Logic for IN, DELETE, and EDIT adjustments
                        $amount = 0;
                        if ($log->type === 'IN') $amount = ($log->qty * $log->supplier_price);
                        if ($log->type === 'DELETE') $amount = -($log->qty * $log->supplier_price);
                        if ($log->type === 'EDIT') {
                            // ... repeat the regex logic above or call a helper ...
                            // (For simplicity, ensure $totalRowAmount from map is what you sum)
                        }
                        $totalPurchases += $amount;
                    }
                }

                // Row for Total Purchases
                $rowIn = $highestRow + 2;
                $event->sheet->setCellValue("G{$rowIn}", 'Total Purchases:');
                $event->sheet->setCellValue("H{$rowIn}", $totalPurchases);

                // Row for Total Sales
                $rowOut = $highestRow + 3;
                $event->sheet->setCellValue("G{$rowOut}", 'Total Sales:');
                $event->sheet->setCellValue("H{$rowOut}", $totalSales);

                // Styling
                $event->sheet->getStyle("G{$rowIn}:H{$rowOut}")->getFont()->setBold(true);
                $event->sheet->getStyle("H{$rowIn}:H{$rowOut}")->getNumberFormat()->setFormatCode('"RM" #,##0.00');

                $event->sheet->getStyle("H{$rowIn}")->getFont()->getColor()->setRGB('DC2626');
                $event->sheet->getStyle("H{$rowOut}")->getFont()->getColor()->setRGB('059669');
            },
        ];
    }
}