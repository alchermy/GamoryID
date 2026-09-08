<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PharData;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class InventoryImportReader
{
    private const MAX_ARCHIVE_ENTRIES = 200;

    private const MAX_UNCOMPRESSED_BYTES = 25 * 1024 * 1024;

    /** Stop tracking a column's distinct values past this — it isn't a status column. */
    private const DISTINCT_CAP = 60;

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>, total_rows: int, distinct_values: array<string, array<int, string>>}
     */
    public function read(string $disk, string $path, ?int $previewLimit = null): array
    {
        $absolutePath = Storage::disk($disk)->path($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'xlsx'
            ? $this->readXlsx($absolutePath, $previewLimit)
            : $this->readCsv($absolutePath, $previewLimit);
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>, total_rows: int, distinct_values: array<string, array<int, string>>}
     */
    private function readCsv(string $path, ?int $previewLimit): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('ไม่สามารถอ่านไฟล์ CSV ได้');
        }

        try {
            $headers = $this->normalizeHeaders(fgetcsv($handle) ?: []);
            $rows = [];
            $totalRows = 0;
            $distinct = [];
            while (($row = fgetcsv($handle)) !== false) {
                $normalized = array_slice(array_pad($row, count($headers), null), 0, count($headers));
                if ($this->isBlankRow($normalized)) {
                    continue;
                }
                $totalRows++;
                $assoc = array_combine($headers, $normalized) ?: [];
                if ($previewLimit === null || count($rows) < $previewLimit) {
                    $rows[] = $assoc;
                }
                if ($previewLimit !== 0) {
                    $this->trackDistinct($distinct, $assoc);
                }
            }
        } finally {
            fclose($handle);
        }

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows, 'distinct_values' => $this->finalizeDistinct($distinct)];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>, total_rows: int, distinct_values: array<string, array<int, string>>}
     */
    private function readXlsx(string $path, ?int $previewLimit): array
    {
        try {
            $archive = new PharData($path);
            $this->guardArchive($archive);
            $sharedStrings = $this->sharedStrings($archive);
            $sheetXml = $this->archiveText($archive, 'xl/worksheets/sheet1.xml');
            $document = $this->xml($sheetXml, 'ไม่สามารถอ่านชีตข้อมูลจากไฟล์ Excel ได้');
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('ไฟล์ Excel ไม่ถูกต้องหรือเสียหาย', previous: $exception);
        }

        $namespace = $document->getNamespaces(true)['x'] ?? $document->getDocNamespaces(true)[''] ?? null;
        $worksheet = $namespace ? $document->children($namespace) : $document;
        $rawRows = [];
        foreach ($worksheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->children($namespace)->c as $cell) {
                $attributes = $cell->attributes();
                $index = $this->columnIndex((string) ($attributes['r'] ?? ''));
                $values[$index] = $this->cellValue($cell, $namespace, $sharedStrings);
            }
            if ($values === []) {
                continue;
            }
            ksort($values);
            $width = max(array_keys($values)) + 1;
            $normalized = array_fill(0, $width, null);
            foreach ($values as $index => $value) {
                $normalized[$index] = $value;
            }
            if (! $this->isBlankRow($normalized)) {
                $rawRows[] = $normalized;
            }
        }

        if ($rawRows === []) {
            throw new RuntimeException('ไม่พบข้อมูลในชีตแรกของไฟล์ Excel');
        }

        $headers = $this->normalizeHeaders(array_shift($rawRows));
        $rows = [];
        $distinct = [];
        foreach ($rawRows as $row) {
            $normalized = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            $assoc = array_combine($headers, $normalized) ?: [];
            if ($previewLimit === null || count($rows) < $previewLimit) {
                $rows[] = $assoc;
            }
            if ($previewLimit !== 0) {
                $this->trackDistinct($distinct, $assoc);
            }
        }
        $totalRows = count($rawRows);

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows, 'distinct_values' => $this->finalizeDistinct($distinct)];
    }

    /**
     * Accumulate up to DISTINCT_CAP unique non-blank values per column; a column
     * that overflows is marked `false` and dropped from the result.
     *
     * @param  array<string, array<string, true>|false>  $distinct
     * @param  array<string, string|null>  $assocRow
     */
    private function trackDistinct(array &$distinct, array $assocRow): void
    {
        foreach ($assocRow as $header => $value) {
            if (($distinct[$header] ?? null) === false) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            $distinct[$header] ??= [];
            $distinct[$header][$trimmed] = true;
            if (count($distinct[$header]) > self::DISTINCT_CAP) {
                $distinct[$header] = false;
            }
        }
    }

    /**
     * @param  array<string, array<string, true>|false>  $distinct
     * @return array<string, array<int, string>>
     */
    private function finalizeDistinct(array $distinct): array
    {
        $out = [];
        foreach ($distinct as $header => $values) {
            if ($values !== false) {
                $out[$header] = array_keys($values);
            }
        }

        return $out;
    }

    private function guardArchive(PharData $archive): void
    {
        $entries = 0;
        $bytes = 0;
        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            $entries++;
            $bytes += $file->getSize();
            if ($entries > self::MAX_ARCHIVE_ENTRIES || $bytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('ไฟล์ Excel มีขนาดข้อมูลภายในมากเกินไป');
            }
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(PharData $archive): array
    {
        if (! isset($archive['xl/sharedStrings.xml'])) {
            return [];
        }
        $document = $this->xml(
            $archive['xl/sharedStrings.xml']->getContent(),
            'ไม่สามารถอ่านข้อความในไฟล์ Excel ได้',
        );
        $namespace = $document->getNamespaces(true)['x'] ?? $document->getDocNamespaces(true)[''] ?? null;
        $root = $namespace ? $document->children($namespace) : $document;

        $strings = [];
        foreach ($root->si as $item) {
            $strings[] = $this->flattenText($item, $namespace);
        }

        return $strings;
    }

    private function cellValue(SimpleXMLElement $cell, ?string $namespace, array $sharedStrings): ?string
    {
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');
        $children = $namespace ? $cell->children($namespace) : $cell;
        if ($type === 'inlineStr') {
            return $this->flattenText($children->is, $namespace);
        }

        $value = isset($children->v) ? (string) $children->v : null;
        if ($value === null || $value === '') {
            return null;
        }

        return $type === 's' ? ($sharedStrings[(int) $value] ?? null) : $value;
    }

    private function flattenText(SimpleXMLElement $node, ?string $namespace): string
    {
        $children = $namespace ? $node->children($namespace) : $node;
        $text = isset($children->t) ? (string) $children->t : '';
        foreach ($children->r as $run) {
            $runChildren = $namespace ? $run->children($namespace) : $run;
            $text .= (string) ($runChildren->t ?? '');
        }

        return $text;
    }

    private function archiveText(PharData $archive, string $path): string
    {
        if (! isset($archive[$path])) {
            throw new RuntimeException('ไม่พบชีตข้อมูลในไฟล์ Excel');
        }

        return $archive[$path]->getContent();
    }

    private function xml(string $xml, string $message): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if ($document === false) {
                throw new RuntimeException($message);
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<int, string> */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = array_map(
            static fn ($header) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? ''),
            $headers,
        );
        if ($normalized === [] || in_array('', $normalized, true)) {
            throw new RuntimeException('หัวคอลัมน์ต้องไม่เว้นว่าง');
        }
        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new RuntimeException('พบชื่อหัวคอลัมน์ซ้ำ กรุณาแก้ไขไฟล์แล้วลองใหม่');
        }

        return $normalized;
    }

    private function isBlankRow(array $row): bool
    {
        return count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0;
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max(0, $index - 1);
    }
}
