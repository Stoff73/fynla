<?php

declare(strict_types=1);

use App\Services\Documents\ExcelParserService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('parses workbook into per-sheet structured data', function () {
    $spreadsheet = new Spreadsheet;

    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('ISA');
    $sheet1->setCellValue('A1', 'Security Name');
    $sheet1->setCellValue('B1', 'Ticker');
    $sheet1->setCellValue('C1', 'Value');
    $sheet1->setCellValue('A2', 'Vanguard FTSE 100');
    $sheet1->setCellValue('B2', 'VUKE');
    $sheet1->setCellValue('C2', '15000');

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('SIPP');
    $sheet2->setCellValue('A1', 'Fund Name');
    $sheet2->setCellValue('B1', 'Units');
    $sheet2->setCellValue('A2', 'L&G Global Equity');
    $sheet2->setCellValue('B2', '500');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService;
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(2);
    expect($sheets[0]['name'])->toBe('ISA');
    expect($sheets[0]['row_count'])->toBe(2);
    expect($sheets[0]['content'])->toContain('Vanguard FTSE 100');
    expect($sheets[1]['name'])->toBe('SIPP');
    expect($sheets[1]['content'])->toContain('L&G Global Equity');

    unlink($tempFile);
});

it('skips empty sheets', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('Data');
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Name');
    $spreadsheet->getActiveSheet()->setCellValue('A2', 'Test');
    $spreadsheet->createSheet()->setTitle('Empty');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService;
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(1);
    expect($sheets[0]['name'])->toBe('Data');

    unlink($tempFile);
});

it('caps at 10 sheets', function () {
    $spreadsheet = new Spreadsheet;
    for ($i = 0; $i < 12; $i++) {
        $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle("Sheet{$i}");
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('A2', "Row{$i}");
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService;
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(10);

    unlink($tempFile);
});
