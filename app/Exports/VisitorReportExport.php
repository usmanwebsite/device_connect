<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VisitorReportExport implements FromCollection, WithHeadings, WithStyles
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
                'IC No / Passport' => $visitor['ic_passport'],
                'Name' => $visitor['name'],
                'Contact No' => $visitor['contact_no'],
                'Company Name' => $visitor['company_name'],
                'Date of Visit' => $visitor['date_of_visit'],
                'Time In' => $visitor['time_in'],
                'Time Out' => $visitor['time_out'] ?? 'N/A',
                'Purpose of Visit' => $visitor['purpose'], // ✅ Yeh automatically updated hoga
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
            'No',
            'IC No / Passport',
            'Name',
            'Contact No',
            'Company Name',
            'Date of Visit',
            'Time In',
            'Time Out',
            'Purpose of Visit',
            'Host Name',
            'Current Location',
            'Location Accessed',
            'Duration',
            'Status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row style
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '556ee6']]
            ],
            
            // Auto-size columns
            'A:N' => [
                'alignment' => ['vertical' => 'center']
            ],
        ];
    }
}
