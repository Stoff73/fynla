<?php

declare(strict_types=1);

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class ExcelParserService
{
    /**
     * Maximum rows to process per sheet.
     */
    private const MAX_ROWS = 500;

    /**
     * Maximum columns to process.
     */
    private const MAX_COLS = 26; // A-Z

    /**
     * Maximum sheets to process per workbook.
     */
    private const MAX_SHEETS = 10;

    /**
     * Parse an Excel file and return text content for AI extraction.
     */
    public function parseToText(string $filePath): string
    {
        try {
            $spreadsheet = IOFactory::load($filePath);

            return $this->convertToText($spreadsheet);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to parse Excel file: '.$e->getMessage());
        }
    }

    /**
     * Parse Excel from binary content.
     */
    public function parseFromContent(string $content, string $mimeType): string
    {
        // Write content to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $extension = $this->getExtensionFromMime($mimeType);
        $tempPath = $tempFile.'.'.$extension;

        rename($tempFile, $tempPath);
        file_put_contents($tempPath, $content);

        try {
            $result = $this->parseToText($tempPath);
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return $result;
    }

    /**
     * Parse an Excel file into per-sheet structured data.
     *
     * @return array<int, array{name: string, content: string, row_count: int, headers: array}>
     */
    public function parseToSheets(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to parse Excel file: '.$e->getMessage());
        }

        $sheets = [];
        $sheetCount = min($spreadsheet->getSheetCount(), self::MAX_SHEETS);

        for ($i = 0; $i < $sheetCount; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $sheetName = $sheet->getTitle();

            $highestRow = min($sheet->getHighestRow(), self::MAX_ROWS);
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = min(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn),
                self::MAX_COLS
            );

            // Check if sheet has any data at all
            $hasAnyData = false;
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                if ($sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).'1')->getFormattedValue() !== '') {
                    $hasAnyData = true;
                    break;
                }
            }
            if (! $hasAnyData) {
                continue;
            }

            // Get headers (first row)
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).'1')->getFormattedValue());
                if ($cellValue !== '') {
                    $headers[$col] = $cellValue;
                }
            }

            // Build text content per sheet
            $lines = ["=== Sheet: {$sheetName} ==="];
            if (! empty($headers)) {
                $lines[] = 'Headers: '.implode(' | ', $headers);
            }

            $dataRowCount = 0;
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasData = false;

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $value = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row)->getFormattedValue();
                    if ($value !== null && $value !== '') {
                        $hasData = true;
                        if (! empty($headers[$col])) {
                            $rowData[] = "{$headers[$col]}: {$value}";
                        } else {
                            $rowData[] = (string) $value;
                        }
                    }
                }

                if ($hasData) {
                    $dataRowCount++;
                    $lines[] = "Row {$row}: ".implode(', ', $rowData);
                }
            }

            if ($dataRowCount === 0 && empty($headers)) {
                continue;
            }

            $sheets[] = [
                'name' => $sheetName,
                'content' => implode("\n", $lines),
                'row_count' => $dataRowCount + 1,
                'headers' => array_values($headers),
            ];
        }

        return $sheets;
    }

    /**
     * Convert spreadsheet to formatted text.
     */
    private function convertToText(Spreadsheet $spreadsheet): string
    {
        $output = [];
        $sheetCount = $spreadsheet->getSheetCount();

        for ($i = 0; $i < $sheetCount; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $sheetName = $sheet->getTitle();

            $output[] = "=== Sheet: {$sheetName} ===\n";

            $highestRow = min($sheet->getHighestRow(), self::MAX_ROWS);
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = min(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn),
                self::MAX_COLS
            );

            // Get headers (first row) for context
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).'1')->getFormattedValue();
                $headers[$col] = trim((string) $cellValue);
            }

            // Process rows
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasData = false;

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row);
                    $value = $cell->getFormattedValue();

                    if ($value !== null && $value !== '') {
                        $hasData = true;
                        // For rows after header, include header context
                        if ($row > 1 && ! empty($headers[$col])) {
                            $rowData[] = "{$headers[$col]}: {$value}";
                        } else {
                            $rowData[] = (string) $value;
                        }
                    }
                }

                if ($hasData) {
                    if ($row === 1) {
                        // Header row - just list column names
                        $output[] = 'Headers: '.implode(' | ', $rowData);
                    } else {
                        $output[] = "Row {$row}: ".implode(', ', $rowData);
                    }
                }
            }

            $output[] = ''; // Blank line between sheets
        }

        return implode("\n", $output);
    }

    /**
     * Get extension from MIME type.
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'text/csv', 'application/csv' => 'csv',
            default => 'xlsx',
        };
    }

    /**
     * Check if a MIME type is an Excel/spreadsheet type.
     */
    public function isSpreadsheet(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv',
        ], true);
    }
}
