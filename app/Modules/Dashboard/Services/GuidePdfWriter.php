<?php

namespace App\Modules\Dashboard\Services;

/**
 * Multi-page Helvetica text PDF for the staff workflow guide (ASCII-safe).
 */
class GuidePdfWriter
{
    /**
     * @param  list<string>  $lines
     */
    public function document(string $title, array $lines): string
    {
        $title = $this->ascii($title, 80);
        $normalized = [];
        foreach ($lines as $line) {
            $normalized = array_merge($normalized, $this->wrap($this->ascii((string) $line, 500), 92));
        }

        $perPage = 48;
        $chunks = $normalized === [] ? [['(empty)']] : array_chunk($normalized, $perPage);
        $pageCount = count($chunks);
        $pageIds = [];
        $objects = [
            1 => '',
            2 => '',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $nextId = 6;
        foreach ($chunks as $index => $chunk) {
            $stream = $this->pageStream($title, $chunk, $index + 1, $pageCount);
            $contentId = $nextId++;
            $pageId = $nextId++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.$contentId.' 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
            $pageIds[] = $pageId.' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.$pageCount.' >>';

        return $this->assemble($objects);
    }

    /**
     * @param  list<string>  $lines
     */
    private function pageStream(string $title, array $lines, int $page, int $pages): string
    {
        $ops = [];
        $ops[] = 'BT /F1 14 Tf 36 754 Td ('.$this->escape($title).') Tj ET';
        $ops[] = 'BT /F1 8 Tf 36 738 Td ('.$this->escape('page '.$page.'/'.$pages).') Tj ET';
        $y = 716;
        foreach ($lines as $line) {
            $ops[] = 'BT /F1 9 Tf 36 '.$this->num($y).' Td ('.$this->escape($line).') Tj ET';
            $y -= 14;
        }

        return implode("\n", $ops);
    }

    /** @param array<int, string> $objects */
    private function assemble(array $objects): string
    {
        $out = "%PDF-1.4\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($out);
        $size = max(array_keys($objects)) + 1;
        $out .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $out .= "trailer << /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $out;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        if ($text === '') {
            return [''];
        }

        $wrapped = wordwrap($text, $width, "\n", true);

        return explode("\n", $wrapped);
    }

    private function ascii(string $value, int $max = 80): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', ' ', $value) ?? $value;
        $ascii = trim(preg_replace('/\s+/', ' ', $ascii) ?? $ascii);

        return substr($ascii !== '' ? $ascii : '-', 0, $max);
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.') ?: '0';
    }
}
