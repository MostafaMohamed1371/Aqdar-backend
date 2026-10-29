<?php

namespace App\Modules\Listing\Services;

class PdfWatermarker
{
    public function stamp(string $bytes, string $label): string
    {
        $label = $this->asciiLabel($label);
        $pdf = ltrim($bytes);

        if (! str_starts_with($pdf, '%PDF')) {
            return $this->coverPage($label);
        }

        $stamped = $this->injectFooter($pdf, $label);
        if ($stamped !== null) {
            return $stamped;
        }

        return $this->coverPage($label);
    }

    public function minimal(string $pageText = 'Document'): string
    {
        $content = 'BT /F1 24 Tf 72 720 Td ('.$this->escape($pageText).') Tj ET';

        return $this->assemble([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ]);
    }

    private function injectFooter(string $pdf, string $label): ?string
    {
        $trailer = $this->trailer($pdf);
        $pages = $this->pageObjects($pdf);
        if ($trailer === null || $pages === []) {
            return null;
        }

        $nextId = max($trailer['size'], $this->maxObjectId($pages) + 1);
        $fontId = $nextId++;
        $newObjects = [
            $fontId => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $resourceMap = [];

        foreach ($pages as $page) {
            $width = $this->mediaBoxWidth($page['body']);
            $overlay = $this->overlayStream($label, $width);
            $streamId = $nextId++;
            $newObjects[$streamId] = '<< /Length '.strlen($overlay)." >>\nstream\n{$overlay}\nendstream";

            $body = $this->withContents($page['body'], $streamId);
            [$body, $nextId] = $this->withFontResource($pdf, $body, $fontId, $nextId, $newObjects, $resourceMap);
            $newObjects[$page['id']] = $this->dictionaryOf($body);
        }

        return $this->incremental($pdf, $newObjects, $trailer, $nextId);
    }

    /** @return array{startxref: int, root: int, size: int}|null */
    private function trailer(string $pdf): ?array
    {
        if (! preg_match('/startxref\s+(\d+)\s*%%EOF\s*\z/', $pdf, $start)) {
            return null;
        }
        if (! preg_match_all('/trailer\s*<<(.*?)>>/s', $pdf, $matches, PREG_SET_ORDER)) {
            return null;
        }
        $dict = $matches[array_key_last($matches)][1];
        if (! preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $dict, $root) || ! preg_match('/\/Size\s+(\d+)/', $dict, $size)) {
            return null;
        }

        return [
            'startxref' => (int) $start[1],
            'root' => (int) $root[1],
            'size' => (int) $size[1],
        ];
    }

    /** @return list<array{id: int, body: string}> */
    private function pageObjects(string $pdf): array
    {
        if (! preg_match_all('/(\d+)\s+0\s+obj\b/', $pdf, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $pages = [];
        foreach ($matches as $match) {
            $id = (int) $match[1][0];
            $start = $match[0][1];
            $end = strpos($pdf, 'endobj', $start);
            if ($end === false) {
                continue;
            }
            $body = substr($pdf, $start, $end - $start);
            if (preg_match('/\/Type\s*\/Pages\b/', $body) || ! preg_match('/\/Type\s*\/Page\b/', $body)) {
                continue;
            }
            $pages[] = ['id' => $id, 'body' => $body];
        }

        return $pages;
    }

    /** @param list<array{id: int, body: string}> $pages */
    private function maxObjectId(array $pages): int
    {
        $max = 0;
        foreach ($pages as $page) {
            $max = max($max, $page['id']);
        }

        return $max;
    }

    private function withContents(string $body, int $streamId): string
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $body, $match)) {
            return preg_replace('/\/Contents\s*\[(.*?)\]/s', '/Contents ['.$match[1].' '.$streamId.' 0 R]', $body, 1) ?? $body;
        }
        if (preg_match('/\/Contents\s+(\d+\s+\d+\s+R)/', $body, $match)) {
            return preg_replace('/\/Contents\s+\d+\s+\d+\s+R/', '/Contents ['.$match[1].' '.$streamId.' 0 R]', $body, 1) ?? $body;
        }

        return preg_replace('/>>\s*\z/', '/Contents '.$streamId.' 0 R >>', $body, 1) ?? $body;
    }

    /**
     * @param  array<int, string>  $newObjects
     * @param  array<int, int>  $resourceMap
     * @return array{0: string, 1: int}
     */
    private function withFontResource(string $pdf, string $body, int $fontId, int $nextId, array &$newObjects, array &$resourceMap): array
    {
        if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $body, $match)) {
            $oldId = (int) $match[1];
            if (! isset($resourceMap[$oldId])) {
                $resource = $this->objectDictionary($pdf, $oldId);
                if ($resource === null) {
                    return [$body, $nextId];
                }
                $resourceMap[$oldId] = $nextId;
                $newObjects[$nextId] = $this->mergeFont($resource, $fontId);
                $nextId++;
            }

            $replaced = preg_replace('/\/Resources\s+\d+\s+0\s+R/', '/Resources '.$resourceMap[$oldId].' 0 R', $body, 1);

            return [$replaced ?? $body, $nextId];
        }

        $resources = $this->locateDictionary($body, 'Resources');
        if ($resources !== null && $resources['type'] === 'dict') {
            $merged = substr($body, 0, $resources['start']).'/Resources '.$this->mergeFont($resources['dict'], $fontId).substr($body, $resources['end']);

            return [$merged, $nextId];
        }

        $withResources = preg_replace(
            '/>>\s*\z/',
            '/Resources << /Font << /AqWm '.$fontId.' 0 R >> >> >>',
            $body,
            1,
        );

        return [$withResources ?? $body, $nextId];
    }

    /** @return array{type: string, dict?: string, id?: int, start: int, end: int}|null */
    private function locateDictionary(string $haystack, string $name): ?array
    {
        if (! preg_match('/\/'.$name.'\s+/', $haystack, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $from = $match[0][1] + strlen($match[0][0]);
        $rest = substr($haystack, $from);
        if (preg_match('/^(\d+)\s+0\s+R/', $rest, $ref)) {
            return [
                'type' => 'ref',
                'id' => (int) $ref[1],
                'start' => $match[0][1],
                'end' => $from + strlen($ref[0]),
            ];
        }
        $dictStart = strpos($haystack, '<<', $from);
        if ($dictStart === false || $dictStart > $from + 8) {
            return null;
        }
        $end = $this->closingDictionary($haystack, $dictStart);
        if ($end === null) {
            return null;
        }

        return [
            'type' => 'dict',
            'dict' => substr($haystack, $dictStart, $end - $dictStart),
            'start' => $match[0][1],
            'end' => $end,
        ];
    }

    private function closingDictionary(string $source, int $start): ?int
    {
        $depth = 0;
        $length = strlen($source);
        for ($i = $start; $i < $length - 1; $i++) {
            if ($source[$i] === '<' && $source[$i + 1] === '<') {
                $depth++;
                $i++;

                continue;
            }
            if ($source[$i] === '>' && $source[$i + 1] === '>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    private function mergeFont(string $resources, int $fontId): string
    {
        $dict = $resources;
        if (str_starts_with($dict, '/Resources')) {
            $dict = preg_replace('/^\/Resources\s*/', '', $dict) ?? $dict;
        }

        if (preg_match('/\/Font\s*<<.*?>>/s', $dict)) {
            $merged = preg_replace('/\/Font\s*<<(.*?)>>/s', '/Font <<$1 /AqWm '.$fontId.' 0 R >>', $dict, 1);

            return $this->dictionaryOf($merged ?? $dict);
        }

        $inner = trim($dict);
        if (str_starts_with($inner, '<<')) {
            $inner = rtrim(substr($inner, 2));
            $inner = preg_replace('/>>\s*\z/', '', $inner) ?? $inner;

            return '<< '.$inner.' /Font << /AqWm '.$fontId.' 0 R >> >>';
        }

        return '<< /Font << /AqWm '.$fontId.' 0 R >> >>';
    }

    private function objectDictionary(string $pdf, int $id): ?string
    {
        if (! preg_match('/'.$id.'\s+0\s+obj\b/', $pdf, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $match[0][1];
        $end = strpos($pdf, 'endobj', $start);
        if ($end === false) {
            return null;
        }

        return $this->dictionaryOf(substr($pdf, $start, $end - $start));
    }

    private function dictionaryOf(string $object): string
    {
        if (preg_match('/<<(.*)>>/s', $object, $match)) {
            return '<<'.$match[1].'>>';
        }

        return trim($object);
    }

    private function mediaBoxWidth(string $body): float
    {
        if (preg_match('/\/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+[\d.]+\s*\]/', $body, $match)) {
            return max(200.0, (float) $match[1]);
        }

        return 612.0;
    }

    private function overlayStream(string $label, float $width): string
    {
        $text = $this->escape($label);

        return 'q 0.047 0.11 0.141 rg 0 0 '.$width." 28 re f 1 1 1 rg BT /AqWm 10 Tf 12 10 Td ({$text}) Tj ET Q";
    }

    /**
     * @param  array<int, string>  $objects
     * @param  array{startxref: int, root: int, size: int}  $trailer
     */
    private function incremental(string $pdf, array $objects, array $trailer, int $nextId): string
    {
        $out = $pdf;
        if (! str_ends_with($out, "\n")) {
            $out .= "\n";
        }

        $offsets = [];
        ksort($objects);
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($out);
        $out .= $this->xref($offsets);
        $size = max($trailer['size'], $nextId);
        $out .= "trailer << /Size {$size} /Root {$trailer['root']} 0 R /Prev {$trailer['startxref']} >>\n";
        $out .= "startxref\n{$xref}\n%%EOF\n";

        return $out;
    }

    /** @param array<int, int> $offsets */
    private function xref(array $offsets): string
    {
        ksort($offsets);
        $out = "xref\n0 1\n0000000000 65535 f \n";
        $ids = array_keys($offsets);
        $i = 0;
        $count = count($ids);
        while ($i < $count) {
            $end = $i;
            while ($end + 1 < $count && $ids[$end + 1] === $ids[$end] + 1) {
                $end++;
            }
            $out .= $ids[$i].' '.($end - $i + 1)."\n";
            for ($k = $i; $k <= $end; $k++) {
                $out .= sprintf("%010d 00000 n \n", $offsets[$ids[$k]]);
            }
            $i = $end + 1;
        }

        return $out;
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

    private function coverPage(string $label, ?string $original = null): string
    {
        $content = $this->overlayStream($label, 612);
        $catalog = '<< /Type /Catalog /Pages 2 0 R'.($original !== null ? ' /Names << /EmbeddedFiles 8 0 R >>' : '').' >>';
        $objects = [
            1 => $catalog,
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /AqWm 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        if ($original !== null) {
            $objects[6] = '<< /Type /EmbeddedFile /Length '.strlen($original)." >>\nstream\n".$original."\nendstream";
            $objects[7] = '<< /Type /Filespec /F (original.pdf) /EF << /F 6 0 R >> >>';
            $objects[8] = '<< /Names [(original.pdf) 7 0 R] >>';
        }

        return $this->assemble($objects);
    }

    private function asciiLabel(string $label): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', ' ', $label) ?? $label;
        $ascii = trim(preg_replace('/\s+/', ' ', $ascii) ?? $ascii);

        return $ascii !== '' ? substr($ascii, 0, 120) : 'AQDAR staff';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
