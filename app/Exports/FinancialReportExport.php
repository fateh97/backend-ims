<?php

namespace App\Exports;

use App\Models\InventoryLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class FinancialReportExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents
{
    public function collection()
    {
        return InventoryLog::with('product')
            ->where('type', 'OUT')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            "Date of Sale",
            "Receipt Code (Ref)",
            "Product Name",
            "Quantity",
            "Unit Price (RM)",
            "Total Amount (RM)"
        ];
    }

    public function map($log): array
    {
        $unitPrice = $log->product ? (float)$log->product->price : 0;
        return [
            $log->created_at->format('d/m/Y'),
            $log->ref,
            $log->product ? $log->product->name : 'Deleted Product',
            $log->qty,
            $unitPrice,
            $log->qty * $unitPrice
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('E2:E' . $highestRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F2:F' . $highestRow)->getNumberFormat()->setFormatCode('#,##0.00');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E11D48']
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
                $lastRow = $highestRow + 1;

                $totalRevenue = $this->collection()->reduce(function ($carry, $log) {
                    return $carry + ($log->qty * ($log->product->price ?? 0));
                }, 0);

                $event->sheet->setCellValue("E{$lastRow}", 'Grand Total:');
                $event->sheet->setCellValue("F{$lastRow}", $totalRevenue);

                $event->sheet->getStyle("E{$lastRow}:F{$lastRow}")->getFont()->setBold(true);
                $event->sheet->getStyle("F{$lastRow}")->getNumberFormat()->setFormatCode('"RM" #,##0.00');
            },
        ];
    }
}
