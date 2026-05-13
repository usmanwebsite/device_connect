<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class VisitorReportExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder
{
    protected $visitors;

    public function __construct($visitors)
    {
        $this->visitors = $visitors;
    }

    public function collection()
    {
        return collect($this->visitors)->map(function ($visitor) {
            return [
                'No' => $visitor['no'],
                'IC No / Passport' => $visitor['ic_passport'], // raw value, no casting
                'Name' => $visitor['name'],
                'Contact No' => $visitor['contact_no'],
                'Company Name' => $visitor['company_name'],
                'Date of Visit' => $visitor['date_of_visit'],
                'Time In' => $visitor['time_in'],
                'Time Out' => $visitor['time_out'] ?? 'N/A',
                'Purpose of Visit' => $visitor['purpose'],
                'Host Name' => $visitor['host_name'],
                'Current Location' => $visitor['current_location'],
                'Location Accessed' => $visitor['location_accessed'],
                'Duration' => $visitor['duration'],
                'Status' => $visitor['status']
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No', 'IC No / Passport', 'Name', 'Contact No', 'Company Name',
            'Date of Visit', 'Time In', 'Time Out', 'Purpose of Visit',
            'Host Name', 'Current Location', 'Location Accessed', 'Duration', 'Status'
        ];
    }

    /**
     * Custom value binder: force column B (index 1) to be string.
     */
    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn(); // 'B' for IC column
        if ($column == 'B') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet)
    {
        // Set column B text format as additional safety
        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        
        // Header style
        $sheet->getStyle('1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '556ee6']]
        ]);
        
        // Auto-size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return [];
    }
}

