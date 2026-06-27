<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gerador PDF minimalista (texto/UTF-8 básico) — sem dependências externas.
 */
final class SimplePdfDocument
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;
    private const MARGIN_L = 50.0;
    private const MARGIN_R = 50.0;
    private const MARGIN_T = 50.0;
    private const MARGIN_B = 50.0;

    /** @var list<array{size:float,text:string,bold:bool,gap:float}> */
    private array $blocks = [];

    public function addTitle(string $text): void
    {
        $this->blocks[] = ['size' => 16.0, 'text' => $text, 'bold' => true, 'gap' => 8.0];
    }

    public function addHeading(string $text): void
    {
        $this->blocks[] = ['size' => 13.0, 'text' => $text, 'bold' => true, 'gap' => 6.0];
    }

    public function addParagraph(string $text, float $size = 11.0): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $this->blocks[] = ['size' => $size, 'text' => $text, 'bold' => false, 'gap' => 4.0];
    }

    public function addSpacer(float $gap = 10.0): void
    {
        $this->blocks[] = ['size' => 11.0, 'text' => '', 'bold' => false, 'gap' => $gap];
    }

    public function saveToFile(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta para PDF.');
        }

        $bytes = $this->render();
        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new \RuntimeException('Não foi possível gravar o PDF.');
        }
    }

    public function render(): string
    {
        $pages = $this->layoutPages();
        $pageCount = count($pages);
        $objects = [];
        $objectIndex = 0;

        $reserve = static function () use (&$objectIndex): int {
            ++$objectIndex;

            return $objectIndex;
        };

        $fontRegularId = $reserve();
        $fontBoldId    = $reserve();
        $pageIds       = [];
        $contentIds    = [];

        foreach ($pages as $pageLines) {
            $contentIds[] = $reserve();
            $pageIds[]    = $reserve();
        }
        $pagesTreeId = $reserve();
        $catalogId   = $reserve();

        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId]    = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $i => $pageLines) {
            $stream = $this->buildContentStream($pageLines, $fontRegularId, $fontBoldId);
            $objects[$contentIds[$i]] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            $objects[$pageIds[$i]] = '<< /Type /Page /Parent ' . $pagesTreeId . ' 0 R /MediaBox [0 0 '
                . self::PAGE_W . ' ' . self::PAGE_H . '] /Contents ' . $contentIds[$i] . " 0 R /Resources << /Font << /F1 "
                . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . " 0 R >> >> >>";
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageIds));
        $objects[$pagesTreeId] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $pageCount . ' >>';
        $objects[$catalogId]   = '<< /Type /Catalog /Pages ' . $pagesTreeId . ' 0 R >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($i = 1; $i <= $objectIndex; ++$i) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($objectIndex + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $objectIndex; ++$i) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($objectIndex + 1) . ' /Root ' . $catalogId . " 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /**
     * @return list<list<array{size:float,text:string,bold:bool,gap:float}>>
     */
    private function layoutPages(): array
    {
        $usableW = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $usableH = self::PAGE_H - self::MARGIN_T - self::MARGIN_B;
        $pages = [[]];
        $y = 0.0;

        foreach ($this->blocks as $block) {
            $lines = $block['text'] === '' ? [''] : $this->wrapText($block['text'], $block['size'], $usableW);
            $lineHeight = $block['size'] * 1.35;

            foreach ($lines as $line) {
                if ($y + $lineHeight > $usableH && $pages[count($pages) - 1] !== []) {
                    $pages[] = [];
                    $y = 0.0;
                }
                $pages[count($pages) - 1][] = [
                    'size' => $block['size'],
                    'text' => $line,
                    'bold' => $block['bold'],
                    'gap'  => $block['gap'],
                ];
                $y += $lineHeight;
            }
            $y += $block['gap'];
        }

        if ($pages === [[]]) {
            $pages[0][] = ['size' => 11.0, 'text' => '', 'bold' => false, 'gap' => 0.0];
        }

        return $pages;
    }

    /**
     * @param list<array{size:float,text:string,bold:bool,gap:float}> $lines
     */
    private function buildContentStream(array $lines, int $fontRegularId, int $fontBoldId): string
    {
        $stream = "BT\n";
        $y = self::PAGE_H - self::MARGIN_T;

        foreach ($lines as $line) {
            $font = $line['bold'] ? 'F2' : 'F1';
            $size = $line['size'];
            $y   -= $size * 1.35;
            $text = $this->escapePdfText($line['text']);
            $stream .= "/{$font} {$size} Tf\n";
            $stream .= '1 0 0 1 ' . self::MARGIN_L . ' ' . round($y, 2) . " Tm\n";
            $stream .= "({$text}) Tj\n";
        }

        return $stream . 'ET';
    }

    /** @return list<string> */
    private function wrapText(string $text, float $fontSize, float $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === []) {
            return [''];
        }

        $lines = [];
        $current = '';
        $charW = $fontSize * 0.52;

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) * $charW <= $maxWidth) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function escapePdfText(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return preg_replace('/[^\x09\x0A\x0D\x20-\xFF]/', '?', $text) ?? $text;
    }
}
