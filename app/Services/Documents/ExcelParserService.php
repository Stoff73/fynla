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
                $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getFormattedValue();
                $headers[$col] = trim((string) $cellValue);
            }

            // Process rows
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasData = false;

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
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
