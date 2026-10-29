<?php

namespace App\Modules\Dashboard\Services;

class ReportPdfWriter
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string|null>>  $rows
     */
    public function table(string $title, array $header, array $rows): string
    {
        $title = $this->ascii($title, 80);
        $header = array_map(fn ($cell) => $this->ascii((string) $cell, 40), $header);
        $normalized = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $i => $_) {
                $line[] = $this->ascii((string) ($row[$i] ?? ''), 48);
            }
            $normalized[] = $line;
        }
        if (count($normalized) > 1500) {
            $normalized = array_slice($normalized, 0, 1500);
            $normalized[] = array_pad(['truncated'], count($header), '');
        }

        $perPage = 28;
        $chunks = $normalized === [] ? [[]] : array_chunk($normalized, $perPage);
        $pageCount = count($chunks);
        $pageIds = [];
        $objects = [
            1 => '',
            2 => '',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $nextId = 6;
        foreach ($chunks as $index => $chunk) {
            $stream = $this->pageStream($title, $header, $chunk, $index + 1, $pageCount);
            $contentId = $nextId++;
            $pageId = $nextId++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Contents '.$contentId.' 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
            $pageIds[] = $pageId.' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.$pageCount.' >>';

        return $this->assemble($objects);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function pageStream(string $title, array $header, array $rows, int $page, int $pages): string
    {
        $ops = [];
        $ops[] = 'BT /F1 12 Tf 36 580 Td ('.$this->escape($title).') Tj ET';
        $ops[] = 'BT /F1 8 Tf 36 564 Td ('.$this->escape('page '.$page.'/'.$pages).') Tj ET';
        $cols = max(count($header), 1);
        $width = 720 / $cols;
        $y = 540;
        foreach ($header as $i => $cell) {
            $x = 36 + ($width * $i);
            $ops[] = 'BT /F1 8 Tf '.$this->num($x).' '.$this->num($y).' Td ('.$this->escape($cell).') Tj ET';
        }
        $y -= 16;
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $x = 36 + ($width * $i);
                $ops[] = 'BT /F1 8 Tf '.$this->num($x).' '.$this->num($y).' Td ('.$this->escape($cell).') Tj ET';
            }
            $y -= 16;
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
