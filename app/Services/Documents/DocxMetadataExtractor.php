<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class DocxMetadataExtractor
{
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';

    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';

    /**
     * @return array{title: ?string, subject: ?string, description: ?string, creator: ?string, keywords: ?string}
     */
    public function extract(string $docxPath): array
    {
        $zip = new ZipArchive;
        $opened = $zip->open($docxPath);
        if ($opened !== true) {
            throw new RuntimeException("File at {$docxPath} is not a valid docx (zip open failed: {$opened})");
        }

        try {
            $xml = $zip->getFromName('docProps/core.xml');
        } finally {
            $zip->close();
        }

        if ($xml === false) {
            return $this->emptyMeta();
        }

        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            Log::warning('[DocxMetadataExtractor] core.xml is malformed XML, falling back to client metadata', [
                'path' => $docxPath,
            ]);

            return $this->emptyMeta();
        }

        $dc = $doc->children(self::NS_DC);
        $cp = $doc->children(self::NS_CP);

        return [
            'title' => $this->stringOrNull($dc->title ?? null),
            'subject' => $this->stringOrNull($dc->subject ?? null),
            'description' => $this->stringOrNull($dc->description ?? null),
            'creator' => $this->stringOrNull($dc->creator ?? null),
            'keywords' => $this->stringOrNull($cp->keywords ?? null),
        ];
    }

    /**
     * @return array{title: null, subject: null, description: null, creator: null, keywords: null}
     */
    private function emptyMeta(): array
    {
        return [
            'title' => null,
            'subject' => null,
            'description' => null,
            'creator' => null,
            'keywords' => null,
        ];
    }

    private function stringOrNull(mixed $node): ?string
    {
        if ($node === null) {
            return null;
        }
        $s = trim((string) $node);

        return $s === '' ? null : $s;
    }
}
