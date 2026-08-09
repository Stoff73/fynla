<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Content;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

/**
 * Parses a .docx file into Fynla's canonical body_blocks structure
 * (see App\Services\Insights\BlockValidator for the shape).
 *
 * Supported Word constructs:
 *   Heading 2/3/4          → heading blocks
 *   Body paragraphs        → paragraph blocks (with <strong>/<em>/<a>)
 *   Bulleted/numbered list → list blocks
 *   Images                 → image blocks (extracted to disk + uploaded via InsightImageService)
 *   Simple tables          → paragraph blocks (rows joined)
 *
 * NOT supported (silently skipped, logged):
 *   Comments, tracked changes, footnotes, nested tables, text boxes,
 *   WordArt, embedded charts, custom paragraph styles outside standard
 *   heading levels.
 */
class WordDocxIngestor
{
    private const ALLOWED_HEADING_LEVELS = [2, 3, 4];

    /**
     * Ingest a .docx file. Returns the canonical body_blocks array + a
     * SHA-256 hash of the raw file for change detection.
     *
     * Images in the source doc are logged and skipped in this MVP —
     * marketing can add hero images via the CMS after import.
     *
     * @return array{blocks: list<array<string,mixed>>, hash: string, image_count: int}
     */
    public function ingest(string $docxPath): array
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("Word doc not found: {$docxPath}");
        }

        $hash = hash_file('sha256', $docxPath);

        try {
            $phpWord = IOFactory::load($docxPath, 'Word2007');
        } catch (\Throwable $e) {
            Log::channel('pipeline')->error('PhpWord load failed.', [
                'path' => $docxPath,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Could not parse Word doc: '.$e->getMessage(), previous: $e);
        }

        $blocks = [];
        $skippedImages = 0;

        foreach ($phpWord->getSections() as $section) {
            /** @var Section $section */
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getMediaId') && ! ($element instanceof Title)) {
                    $skippedImages++;

                    continue;
                }
                $block = $this->convertElement($element);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        if ($skippedImages > 0) {
            Log::channel('pipeline')->info('WordDocxIngestor skipped image blocks.', [
                'path' => $docxPath,
                'count' => $skippedImages,
            ]);
        }

        return [
            'blocks' => $this->coalesceConsecutiveLists($blocks),
            'hash' => $hash,
            'image_count' => $skippedImages,
        ];
    }

    private function convertElement(object $element): ?array
    {
        return match (true) {
            $element instanceof Title => $this->convertTitle($element),
            $element instanceof TextRun => $this->convertParagraph($element),
            $element instanceof ListItem => $this->convertListItem($element),
            $element instanceof Table => $this->convertTable($element),
            method_exists($element, 'getText') => $this->convertPlainText($element),
            default => null,
        };
    }

    private function convertTitle(Title $title): ?array
    {
        $level = $title->getDepth() + 1;
        if (! in_array($level, self::ALLOWED_HEADING_LEVELS, true)) {
            return ['type' => 'paragraph', 'html' => '<p>'.$this->escape((string) $title->getText()).'</p>'];
        }

        return [
            'type' => 'heading',
            'level' => $level,
            'text' => trim((string) $title->getText()),
        ];
    }

    private function convertParagraph(TextRun $run): ?array
    {
        $html = '';
        foreach ($run->getElements() as $inline) {
            $html .= $this->renderInline($inline);
        }
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        return [
            'type' => 'paragraph',
            'html' => '<p>'.$html.'</p>',
        ];
    }

    private function convertPlainText(object $element): ?array
    {
        $text = trim((string) $element->getText());
        if ($text === '') {
            return null;
        }

        return [
            'type' => 'paragraph',
            'html' => '<p>'.$this->escape($text).'</p>',
        ];
    }

    private function convertListItem(ListItem $item): array
    {
        return [
            'type' => '__pending_list_item__',
            'text' => trim((string) $item->getText()),
        ];
    }

    private function convertTable(Table $table): ?array
    {
        $lines = [];
        foreach ($table->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellEl) {
                    if (method_exists($cellEl, 'getText')) {
                        $cells[] = trim((string) $cellEl->getText());
                    }
                }
            }
            if ($cells !== []) {
                $lines[] = implode(' · ', array_filter($cells));
            }
        }

        if ($lines === []) {
            return null;
        }

        return [
            'type' => 'paragraph',
            'html' => '<p>'.$this->escape(implode(' — ', $lines)).'</p>',
        ];
    }

    private function renderInline(object $inline): string
    {
        if (! method_exists($inline, 'getText')) {
            return '';
        }

        $text = $this->escape((string) $inline->getText());
        if ($text === '') {
            return '';
        }

        $style = method_exists($inline, 'getFontStyle') ? $inline->getFontStyle() : null;
        if ($style !== null) {
            if (method_exists($style, 'isBold') && $style->isBold()) {
                $text = '<strong>'.$text.'</strong>';
            }
            if (method_exists($style, 'isItalic') && $style->isItalic()) {
                $text = '<em>'.$text.'</em>';
            }
        }

        if (method_exists($inline, 'getSource') && method_exists($inline, 'getTarget')) {
            $href = (string) $inline->getSource();
            if ($href !== '') {
                $text = '<a href="'.$this->escape($href).'">'.$text.'</a>';
            }
        }

        return $text;
    }

    /**
     * Merge consecutive __pending_list_item__ entries into a single
     * `list` block. Called once after the whole doc is parsed.
     *
     * @param  list<array<string,mixed>>  $blocks
     * @return list<array<string,mixed>>
     */
    private function coalesceConsecutiveLists(array $blocks): array
    {
        $out = [];
        $pending = [];

        $flush = function () use (&$out, &$pending): void {
            if ($pending !== []) {
                $out[] = [
                    'type' => 'list',
                    'style' => 'unordered',
                    'items' => $pending,
                ];
                $pending = [];
            }
        };

        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === '__pending_list_item__') {
                $text = (string) ($block['text'] ?? '');
                if ($text !== '') {
                    $pending[] = $text;
                }

                continue;
            }
            $flush();
            $out[] = $block;
        }
        $flush();

        return $out;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
