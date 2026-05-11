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
        return InventoryLog::whereIn('type', ['IN', 'OUT'])
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
        $qty = $log->qty;
        $supplierPrice = (float) $log->supplier_price;
        $unitPrice = (float) ($log->price ?? $log->service_price ?? 0);

        $displayType = ($log->type === 'IN') ? 'PURCHASE' : 'SALES';

        $totalRowAmount = ($log->type === 'IN')
            ? ($qty * $supplierPrice)
            : ($qty * $unitPrice);

        // 3. Conditional Pricing Display
        // If it's a PURCHASE, we show '-' or 0.00 for the Unit Price column
        $displayUnitPrice = ($log->type === 'IN') ? '-' : $unitPrice;

        return [
            $log->created_at->format('d/m/Y'),
            $log->ref,
            $log->product_name ?? $log->service_name,
            $displayType,
            $qty,
            $supplierPrice,
            $displayUnitPrice, // Unit Price column
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

                $totalIn = 0;
                $totalOut = 0;

                foreach ($this->collection() as $log) {
                    if ($log->type === 'IN') {
                        $totalIn += ($log->qty * $log->supplier_price);
                    } elseif ($log->type === 'OUT') {
                        $totalOut += ($log->qty * ($log->price ?? $log->service_price ?? 0));
                    }
                }

                // Row for Total Purchases
                $rowIn = $highestRow + 2;
                $event->sheet->setCellValue("G{$rowIn}", 'Total Purchases:');
                $event->sheet->setCellValue("H{$rowIn}", $totalIn);

                // Row for Total Sales
                $rowOut = $highestRow + 3;
                $event->sheet->setCellValue("G{$rowOut}", 'Total Sales:');
                $event->sheet->setCellValue("H{$rowOut}", $totalOut);

                // Styling
                $event->sheet->getStyle("G{$rowIn}:H{$rowOut}")->getFont()->setBold(true);
                $event->sheet->getStyle("H{$rowIn}:H{$rowOut}")->getNumberFormat()->setFormatCode('"RM" #,##0.00');

                $event->sheet->getStyle("H{$rowIn}")->getFont()->getColor()->setRGB('DC2626');
                $event->sheet->getStyle("H{$rowOut}")->getFont()->getColor()->setRGB('059669');
            },
        ];
    }
}
