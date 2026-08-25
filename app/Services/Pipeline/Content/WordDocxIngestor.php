<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Content;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use ZipArchive;

/**
 * Parses a .docx file into Fynla's canonical body_blocks structure
 * (see App\Services\Insights\BlockValidator for the shape).
 *
 * What survives, verified by ingesting Word-authored .docx files:
 *   Word Heading 1, 2, 3   become heading blocks at level 2, 3, 4 (depth + 1)
 *   Body paragraphs        become paragraph blocks
 *   Bold, italic           become <strong>, <em>
 *   Hyperlinks             become <a>; links to Fynla are rewritten relative
 *   Bulleted lists         become list blocks with ordered = false
 *   Numbered lists         become list blocks with ordered = true
 *   Simple tables          are flattened into one paragraph, rows joined
 *
 * What does NOT survive (dropped or flattened, logged where useful):
 *   Images, comments, tracked changes, footnotes, nested tables, text boxes,
 *   WordArt, embedded charts, and Word Heading 4 or deeper (plain paragraphs).
 *   Nested list levels are flattened into their parent list.
 */
class WordDocxIngestor
{
    private const ALLOWED_HEADING_LEVELS = [2, 3, 4];

    /** Hosts treated as "our own site", whose links are rewritten site-relative. */
    private const OWN_HOSTS = ['fynla.org'];

    private const PENDING_LIST_ITEM = '__pending_list_item__';

    private const WORDPROCESSING_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

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
        $numbering = $this->readNumberingFormats($docxPath);

        foreach ($phpWord->getSections() as $section) {
            /** @var Section $section */
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getMediaId') && ! ($element instanceof Title)) {
                    $skippedImages++;

                    continue;
                }
                $block = $this->convertElement($element, $numbering);
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

    /**
     * @param  array<string,bool>  $numbering  numId to "is an ordered list"
     */
    private function convertElement(object $element, array $numbering): ?array
    {
        return match (true) {
            $element instanceof Title => $this->convertTitle($element),
            // ListItemRun extends TextRun, so it must be tested first or every
            // Word list item is silently swallowed by the paragraph arm.
            $element instanceof ListItemRun => $this->convertListItemRun($element, $numbering),
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
            'type' => self::PENDING_LIST_ITEM,
            'html' => $this->escape(trim((string) $item->getText())),
            'ordered' => false,
            'group' => 'legacy',
        ];
    }

    /**
     * A genuine Word list item. Inline content is rendered the same way as a
     * paragraph, so bold, italic and links survive inside bullets.
     *
     * @param  array<string,bool>  $numbering
     */
    private function convertListItemRun(ListItemRun $item, array $numbering): array
    {
        $html = '';
        foreach ($item->getElements() as $inline) {
            $html .= $this->renderInline($inline);
        }

        $style = $item->getStyle();
        $numId = $style !== null && method_exists($style, 'getNumId')
            ? (string) $style->getNumId()
            : '';

        return [
            'type' => self::PENDING_LIST_ITEM,
            'html' => trim($html),
            // Word records only which numbering definition an item belongs to;
            // whether that renders as bullets or numbers lives in numbering.xml.
            'ordered' => $numbering[$numId] ?? false,
            'group' => $numId,
        ];
    }

    /**
     * Map each numbering definition in the document to whether it is ordered.
     *
     * Word stores a list item as an ordinary paragraph carrying a numId; the
     * bullet-versus-number decision sits in word/numbering.xml, which PhpWord's
     * reader does not surface (it leaves the style's listType null). So read it
     * out of the package directly.
     *
     * @return array<string,bool>
     */
    private function readNumberingFormats(string $docxPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/numbering.xml');
        $zip->close();

        // No numbering part simply means the document contains no lists.
        if (! is_string($xml) || trim($xml) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            Log::channel('pipeline')->warning('WordDocxIngestor could not parse numbering.xml; lists default to bullets.', [
                'path' => $docxPath,
            ]);

            return [];
        }

        $ns = $doc->getNamespaces(true)['w'] ?? self::WORDPROCESSING_NS;
        $doc->registerXPathNamespace('w', $ns);

        // abstractNumId to the format of its top level.
        $abstract = [];
        foreach ($doc->xpath('//w:abstractNum') ?: [] as $node) {
            $id = (string) ($node->attributes($ns)['abstractNumId'] ?? '');
            if ($id === '') {
                continue;
            }
            $node->registerXPathNamespace('w', $ns);
            $fmt = $node->xpath('.//w:lvl[@w:ilvl="0"]/w:numFmt') ?: [];
            $abstract[$id] = $fmt === []
                ? 'bullet'
                : strtolower((string) ($fmt[0]->attributes($ns)['val'] ?? 'bullet'));
        }

        $map = [];
        foreach ($doc->xpath('//w:num') ?: [] as $node) {
            $numId = (string) ($node->attributes($ns)['numId'] ?? '');
            if ($numId === '') {
                continue;
            }
            $node->registerXPathNamespace('w', $ns);
            $ref = $node->xpath('./w:abstractNumId') ?: [];
            $abstractId = $ref === [] ? '' : (string) ($ref[0]->attributes($ns)['val'] ?? '');
            $format = $abstract[$abstractId] ?? 'bullet';

            $map[$numId] = ! in_array($format, ['bullet', 'none'], true);
        }

        return $map;
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

        // PhpWord's Link exposes getSource() and has no getTarget() at all, so
        // the old method_exists() pair was never satisfied — every hyperlink in
        // every imported doc was silently flattened to plain text.
        if ($inline instanceof Link) {
            $href = $this->normaliseHref((string) $inline->getSource());
            if ($href !== '') {
                $text = '<a href="'.$this->escape($href).'">'.$text.'</a>';
            }
        }

        return $text;
    }

    /**
     * Links pointing at Fynla itself are rewritten site-relative so they keep
     * working on whichever environment the article is read on — an author
     * pasting a fynla.org address into a draft shouldn't send a dev reader to
     * production. Anything external keeps its absolute URL untouched.
     */
    private function normaliseHref(string $href): string
    {
        $href = trim($href);

        // mailto:, tel:, #anchors and already-relative paths pass through as-is.
        if ($href === '' || ! preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($href);
        if ($parts === false || ! isset($parts['host']) || ! $this->isOwnHost($parts['host'])) {
            return $href;
        }

        $relative = $parts['path'] ?? '/';
        if (($parts['query'] ?? '') !== '') {
            $relative .= '?'.$parts['query'];
        }
        if (($parts['fragment'] ?? '') !== '') {
            $relative .= '#'.$parts['fragment'];
        }

        return $relative === '' ? '/' : $relative;
    }

    private function isOwnHost(string $host): bool
    {
        $strip = static fn (string $h): string => (string) preg_replace('/^www\./i', '', strtolower(trim($h)));

        $host = $strip($host);
        if ($host === '') {
            return false;
        }

        // The configured app host covers dev and local as well as production,
        // so a link copied from whichever environment the author was browsing
        // still resolves for the reader.
        $appHost = $strip((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return in_array($host, self::OWN_HOSTS, true) || ($appHost !== '' && $host === $appHost);
    }

    /**
     * Merge consecutive pending list items into `list` blocks. Called once
     * after the whole doc is parsed.
     *
     * A run is broken by anything that is not a list item, and also by a change
     * of numbering definition or of bullets-versus-numbers, so two lists that
     * sit back to back in the document stay two lists.
     *
     * @param  list<array<string,mixed>>  $blocks
     * @return list<array<string,mixed>>
     */
    private function coalesceConsecutiveLists(array $blocks): array
    {
        $out = [];
        $pending = [];
        $ordered = false;
        $group = null;

        $flush = function () use (&$out, &$pending, &$ordered, &$group): void {
            if ($pending !== []) {
                $out[] = [
                    'type' => 'list',
                    'ordered' => $ordered,
                    'items' => $pending,
                ];
                $pending = [];
            }
            $group = null;
        };

        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== self::PENDING_LIST_ITEM) {
                $flush();
                $out[] = $block;

                continue;
            }

            $html = (string) ($block['html'] ?? '');
            if ($html === '') {
                continue;
            }

            $itemOrdered = (bool) ($block['ordered'] ?? false);
            $itemGroup = (string) ($block['group'] ?? '');

            if ($group !== null && ($itemGroup !== $group || $itemOrdered !== $ordered)) {
                $flush();
            }

            $group = $itemGroup;
            $ordered = $itemOrdered;
            $pending[] = $html;
        }
        $flush();

        return $out;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
