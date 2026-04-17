<?php

namespace App\Services;

use App\Models\JudoJournal;
use App\Models\UserCompany;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class JournalExportService
{
    public function prepareSpreadsheet(JudoJournal $journal, UserCompany $company)
    {
        $templatePath = public_path('ЖУДО.xls');
        $spreadsheet = IOFactory::load($templatePath);

        $periodDate = Carbon::parse($journal->period);
        $startDate = $periodDate->copy()->startOfMonth();
        $endDate = $periodDate->copy()->endOfMonth();
        $periodStr = Str::ucfirst($periodDate->translatedFormat('F Y'));

        if ($journal->type === 'year') {
            $periodStr = $periodDate->year . ' год';
            $startDate = $periodDate->copy()->startOfYear();
            $endDate = $periodDate->copy()->endOfYear();
        } elseif ($journal->type === 'quarter') {
            $q = ceil($periodDate->month / 3);
            $periodStr = $q . ' квартал ' . $periodDate->year . ' года';
            $startDate = $periodDate->copy()->startOfQuarter();
            $endDate = $periodDate->copy()->endOfQuarter();
        }

        $sheet0 = $spreadsheet->getSheet(0);

        $monthsGenitive = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $currentMonthRus = $monthsGenitive[(int)date('n')];
        $currentDateLine = '" ' . date('d') . ' " ' . $currentMonthRus . ' ' . date('Y') . ' г.';

        $sheet0->setCellValue('F3', 'Генеральный директор ' . ($company->name ?? ''));
        $sheet0->setCellValue('M5', $company->contact_person ?? '');
        $sheet0->setCellValue('M8', $currentDateLine);
        
        $sheet0->setCellValue('D13', $company->name ?? '');
        $sheet0->setCellValue('C21', $company->name ?? '');

        $sheet0->setCellValue('D27', Date::PHPToExcel($startDate));
        $sheet0->getStyle('D27')->getNumberFormat()->setFormatCode('dd.mm.yyyy');
        
        $sheet0->setCellValue('D29', Date::PHPToExcel($endDate));
        $sheet0->getStyle('D29')->getNumberFormat()->setFormatCode('dd.mm.yyyy');

        $populate = function($sheetIdx, $data, $columns, $startRow, $numCol = 'B', $dataStart = 3) use ($spreadsheet) {
            $sheet = $spreadsheet->getSheet($sheetIdx);
            $r = $startRow;
            foreach ($data as $idx => $item) {

                $sheet->setCellValue($numCol . $r, $idx + 1);
                $sheet->getStyle($numCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($numCol . $r)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle($numCol . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE);

                $colIndex = $dataStart;
                foreach ($columns as $key) {
                    $val = $item[$key] ?? 0;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter . $r, $val);
                    $sheet->getStyle($colLetter . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($colLetter . $r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle($colLetter . $r)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $sheet->getStyle($colLetter . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE);
                    $colIndex++;
                }

                if ($sheetIdx === 1) {
                    for ($extra = $colIndex; $extra <= 8; $extra++) {
                        $colLetter = Coordinate::stringFromColumnIndex($extra);
                        $sheet->setCellValue($colLetter . $r, '');
                        $sheet->getStyle($colLetter . $r)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                        $sheet->getStyle($colLetter . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE);
                    }
                }

                $sheet->getRowDimension($r)->setRowHeight(-1);
                $r++;
            }
        };

        $populate(1, $journal->table1_data, ['name', 'fkko', 'hazard', 'origin', 'aggregate_state', 'chemical_composition'], 7, 'B', 3);

        $table2_excel_data = collect($journal->table2_data)->map(function ($item) {
            $item['start_storage'] = $item['start_storage'] ?? 0;
            $item['start_accumulation'] = $item['start_accumulation'] ?? $item['balance_begin'] ?? 0;
            $item['placed_total'] = ($item['stored'] ?? 0) + ($item['buried'] ?? 0);
            $item['end_storage'] = $item['end_storage'] ?? 0;
            $item['end_accumulation'] = $item['end_accumulation'] ?? $item['balance_end'] ?? 0;
            return $item;
        })->toArray();
        $populate(2, $table2_excel_data, [
            'name', 'fkko', 'hazard', 'start_storage', 'start_accumulation', 'generated', 'received',
            'processed', 'utilized', 'neutralized', 'transferred_total',
            'placed_total', 'stored', 'buried', 'end_storage', 'end_accumulation'
        ], 9, 'B', 3);

        $populate(3, $journal->table3_data, [
            'waste', 'fkko', 'hazard', 'amount', 'amt_process', 'amt_util', 'amt_neutr', 'amt_store', 'amt_bury', 'counterparty', 'contract_details', 'contract_validity', 'license'
        ], 8, 'A', 2);

        $populate(4, $journal->table4_data, [
            'waste', 'fkko', 'hazard', 'amount', 'amt_third_party', 'amt_process', 
            'amt_util', 'amt_neutr', 'amt_store', 'amt_bury', 'counterparty', 
            'contract_details', 'contract_validity'
        ], 10, 'B', 3);

        $filename = "ЖУДО_" . str_replace(' ', '_', $company->name) . "_" . $periodDate->format('Y-m') . ".xls";
        return ['spreadsheet' => $spreadsheet, 'filename' => $filename];
    }
}
