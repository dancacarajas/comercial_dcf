<?php

declare(strict_types=1);

namespace App\Services;

/**
 * PDF contratual premium — identidade Dança Carajás + carimbo de assinatura com link de auditoria.
 */
final class ContractPdfDocument
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;
    private const MARGIN_L = 48.0;
    private const MARGIN_R = 48.0;
    private const MARGIN_B = 48.0;
    private const FOOTER_H = 42.0;

    private const C_YELLOW = '0.969 0.769 0.0';
    private const C_BLACK  = '0.02 0.02 0.02';
    private const C_MUTED  = '0.35 0.35 0.35';
    private const C_LINE   = '0.82 0.82 0.81';

    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    /** @var array<string, mixed> */
    private array $branding = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var list<array<string, mixed>> */
    private array $stamps = [];

    /** @var array<string, array{width:int,height:int,color:string,format:string}> */
    private array $loadedImages = [];

    /** @var array<string, array{name:string,colorId:int,maskId:?int,objects:array<int,string>}> */
    private array $imageObjects = [];

    /** @var list<array{page:int,rect:array{0:float,1:float,2:float,3:float},url:string}> */
    private array $linkAnnotations = [];

    /** @param array<string, mixed> $branding */
    public function setBranding(array $branding): void
    {
        $this->branding = $branding;
    }

    /** @param array<string, mixed> $meta */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    /** @param array<string, mixed> $stamp */
    public function setSignatureStamp(array $stamp): void
    {
        $this->addSignatureStamp($stamp);
    }

    /** @param array<string, mixed> $stamp */
    public function addSignatureStamp(array $stamp): void
    {
        $this->stamps[] = $stamp;
        $this->blocks[] = ['type' => 'stamp', 'height' => 96.0, 'stamp' => $stamp];
    }

    public function addHeading(string $text): void
    {
        $this->blocks[] = ['type' => 'heading', 'text' => $text];
    }

    public function addParagraph(string $text, float $size = 10.0): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $this->blocks[] = ['type' => 'paragraph', 'text' => $text, 'size' => $size];
    }

    public function addSpacer(float $gap = 10.0): void
    {
        $this->blocks[] = ['type' => 'spacer', 'gap' => $gap];
    }

    public function saveToFile(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta para PDF.');
        }

        if (file_put_contents($absolutePath, $this->render()) === false) {
            throw new \RuntimeException('Não foi possível gravar o PDF.');
        }
    }

    public function render(): string
    {
        $pages = $this->layoutPages();
        $pageCount = count($pages);
        $objects = [];
        $nextId = 0;
        $reserve = function () use (&$nextId): int {
            return ++$nextId;
        };

        $fontRegularId = $reserve();
        $fontBoldId = $reserve();
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId]    = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $this->loadBrandingImages($objects, $nextId);

        $contentIds = [];
        $pageIds = [];
        $pageAnnotIds = [];

        foreach ($pages as $i => $pageLines) {
            $contentIds[] = $reserve();
            $pageIds[] = $reserve();
            $pageAnnotIds[$i] = [];
        }

        $pagesTreeId = $reserve();
        $catalogId = $reserve();

        foreach ($pages as $i => $pageLines) {
            $stream = $this->buildPageStream($pageLines, $i + 1, $pageCount, $i === 0, $i + 1);
            $objects[$contentIds[$i]] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

            foreach ($this->linkAnnotations as $link) {
                if ($link['page'] !== $i + 1) {
                    continue;
                }
                $annotId = $reserve();
                [$x1, $y1, $x2, $y2] = $link['rect'];
                $uri = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $link['url']);
                $objects[$annotId] = '<< /Type /Annot /Subtype /Link /Rect [' . round($x1, 2) . ' ' . round($y1, 2)
                    . ' ' . round($x2, 2) . ' ' . round($y2, 2) . '] /Border [0 0 1] /C [0 0.75 0.75] /A << /S /URI /URI (' . $uri . ') >> >>';
                $pageAnnotIds[$i][] = $annotId;
            }

            $xObjects = $this->buildXObjectDict();
            $resources = '<< /Font << /F1 ' . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >>';
            if ($xObjects !== '') {
                $resources .= ' /XObject << ' . $xObjects . ' >>';
            }
            $resources .= ' >>';

            $annotPart = '';
            if ($pageAnnotIds[$i] !== []) {
                $annotPart = ' /Annots [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageAnnotIds[$i])) . ']';
            }

            $objects[$pageIds[$i]] = '<< /Type /Page /Parent ' . $pagesTreeId . ' 0 R /MediaBox [0 0 '
                . self::PAGE_W . ' ' . self::PAGE_H . '] /Contents ' . $contentIds[$i] . ' 0 R /Resources '
                . $resources . $annotPart . ' >>';
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageIds));
        $objects[$pagesTreeId] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $pageCount . ' >>';
        $objects[$catalogId]   = '<< /Type /Catalog /Pages ' . $pagesTreeId . ' 0 R >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $maxId = max(array_keys($objects));
        for ($i = 1; $i <= $maxId; ++$i) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . ($objects[$i] ?? '<< >>') . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; ++$i) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . ' /Root ' . $catalogId . " 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /** @param array<int, string> $objects */
    private function loadBrandingImages(array &$objects, int &$nextId): void
    {
        foreach (['festival' => 'festival_logo_path', 'producer' => 'producer_logo_path'] as $key => $cfgKey) {
            $path = (string) ($this->branding[$cfgKey] ?? '');
            if ($path === '') {
                continue;
            }
            $img = PdfImageLoader::fromFile($path);
            if ($img === null) {
                continue;
            }
            $this->loadedImages[$key] = $img;
            $this->imageObjects[$key] = PdfImageLoader::toPdfObjects($img, $nextId);
            foreach ($this->imageObjects[$key]['objects'] as $id => $def) {
                $objects[$id] = $def;
            }
        }
    }

    private function buildXObjectDict(): string
    {
        $parts = [];
        foreach ($this->imageObjects as $imgObj) {
            $parts[] = '/' . $imgObj['name'] . ' ' . $imgObj['colorId'] . ' 0 R';
        }

        return implode(' ', $parts);
    }

    /** @return list<list<array<string, mixed>>> */
    private function layoutPages(): array
    {
        $usableW = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $pages = [[]];
        $pageIndex = 0;
        $y = 0.0;
        $usableFirst = self::PAGE_H - self::MARGIN_B - self::FOOTER_H - 128.0;
        $usableOther = self::PAGE_H - self::MARGIN_B - self::FOOTER_H - 48.0;

        foreach ($this->blocks as $block) {
            $items = $this->expandBlock($block, $usableW);
            $maxH = $pageIndex === 0 ? $usableFirst : $usableOther;

            foreach ($items as $item) {
                $itemH = (float) ($item['height'] ?? 0.0);
                if ($y + $itemH > $maxH && $pages[$pageIndex] !== []) {
                    $pages[] = [];
                    ++$pageIndex;
                    $y = 0.0;
                    $maxH = $usableOther;
                }
                $pages[$pageIndex][] = $item;
                $y += $itemH;
            }
        }

        return $this->coalesceOrphanPages($pages);
    }

    /**
     * Evita página final quase vazia (ex.: só carimbo + rodapé).
     *
     * @param list<list<array<string, mixed>>> $pages
     * @return list<list<array<string, mixed>>>
     */
    private function coalesceOrphanPages(array $pages): array
    {
        if (count($pages) <= 1) {
            return $pages === [[]] ? [[['kind' => 'spacer', 'height' => 10.0]]] : $pages;
        }

        $lastIdx = count($pages) - 1;
        $last = $pages[$lastIdx];
        $textItems = array_filter($last, static fn (array $i): bool => ($i['kind'] ?? '') === 'text');
        $hasStamp = (bool) array_filter($last, static fn (array $i): bool => ($i['kind'] ?? '') === 'stamp');

        if ($hasStamp && count($textItems) === 0) {
            $stampItems = array_values(array_filter($last, static fn (array $i): bool => ($i['kind'] ?? '') === 'stamp'));
            array_pop($pages);
            foreach ($stampItems as $stampItem) {
                $pages[count($pages) - 1][] = $stampItem;
            }
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function expandBlock(array $block, float $usableW): array
    {
        $type = (string) ($block['type'] ?? 'paragraph');

        if ($type === 'spacer') {
            return [['kind' => 'spacer', 'height' => (float) ($block['gap'] ?? 10.0)]];
        }

        if ($type === 'stamp') {
            return [[
                'kind'   => 'stamp',
                'height' => (float) ($block['height'] ?? 96.0),
                'stamp'  => (array) ($block['stamp'] ?? []),
            ]];
        }

        $size = $type === 'heading' ? 10.5 : (float) ($block['size'] ?? 10.0);
        $bold = $type === 'heading';
        $gap = $type === 'heading' ? 8.0 : 4.0;
        $lineHeight = $size * 1.42;
        $text = (string) ($block['text'] ?? '');
        $lines = $text === '' ? [] : $this->wrapText($text, $size, $usableW);

        $items = [];
        foreach ($lines as $line) {
            $items[] = ['kind' => 'text', 'size' => $size, 'text' => $line, 'bold' => $bold, 'height' => $lineHeight];
        }
        if ($items !== []) {
            $items[count($items) - 1]['height'] += $gap;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function buildPageStream(array $items, int $pageNum, int $pageCount, bool $isFirst, int $pageIndex): string
    {
        $stream = '';
        $this->drawTopBand($stream);
        $contentTop = $this->drawHeader($stream, $isFirst);
        $y = $contentTop;

        foreach ($items as $item) {
            $kind = (string) ($item['kind'] ?? 'text');
            if ($kind === 'spacer') {
                $y -= (float) ($item['height'] ?? 0.0);
                continue;
            }
            if ($kind === 'stamp') {
                $y = $this->drawSignatureStamp($stream, $y, $pageIndex, (array) ($item['stamp'] ?? []));
                continue;
            }

            $font = !empty($item['bold']) ? 'F2' : 'F1';
            $size = (float) ($item['size'] ?? 10.0);
            $y -= $size * 1.15;
            $stream .= "BT\n/{$font} {$size} Tf\n" . self::C_BLACK . " rg\n";
            $stream .= '1 0 0 1 ' . self::MARGIN_L . ' ' . round($y, 2) . " Tm\n";
            $stream .= PdfText::literal((string) ($item['text'] ?? '')) . " Tj\nET\n";
            $y -= ((float) ($item['height'] ?? 0.0)) - ($size * 1.15);
        }

        $this->drawFooter($stream, $pageNum, $pageCount);

        return $stream;
    }

    private function drawTopBand(string &$stream): void
    {
        $stream .= "q\n" . self::C_YELLOW . " rg\n0 " . (self::PAGE_H - 6) . ' ' . self::PAGE_W . " 6 re\nf\nQ\n";
    }

    private function drawHeader(string &$stream, bool $isFirst): float
    {
        $logoTop = self::PAGE_H - 24.0;
        $this->drawLogo($stream, 'festival', self::MARGIN_L, $logoTop, 120.0, 32.0);
        $this->drawLogo($stream, 'producer', self::PAGE_W - self::MARGIN_R, $logoTop, 110.0, 32.0, true);

        if ($isFirst) {
            $kicker = 'DOCUMENTO CONTRATUAL';
            $stream .= "BT\n/F2 7 Tf\n" . self::C_MUTED . " rg\n";
            $stream .= '1 0 0 1 ' . (self::PAGE_W / 2 - 42) . ' ' . (self::PAGE_H - 58.0) . " Tm\n";
            $stream .= PdfText::literal($kicker) . " Tj\nET\n";

            $title = mb_strtoupper(trim((string) ($this->meta['title'] ?? 'Contrato')));
            $stream .= "BT\n/F2 11.5 Tf\n" . self::C_BLACK . " rg\n";
            $stream .= '1 0 0 1 ' . self::MARGIN_L . ' ' . (self::PAGE_H - 76.0) . " Tm\n";
            $stream .= PdfText::literal($title) . " Tj\nET\n";

            $meta = [];
            if (($ref = trim((string) ($this->meta['reference'] ?? ''))) !== '') {
                $meta[] = $ref;
            }
            if (($issued = trim((string) ($this->meta['issued_at'] ?? ''))) !== '') {
                $meta[] = 'Emitido em ' . $issued;
            }
            if ($meta !== []) {
                $stream .= "BT\n/F1 8 Tf\n" . self::C_MUTED . " rg\n";
                $stream .= '1 0 0 1 ' . self::MARGIN_L . ' ' . (self::PAGE_H - 90.0) . " Tm\n";
                $stream .= PdfText::literal(implode('   |   ', $meta)) . " Tj\nET\n";
            }

            $lineY = self::PAGE_H - 98.0;
        } else {
            $lineY = self::PAGE_H - 46.0;
        }

        $this->hline($stream, self::MARGIN_L, self::PAGE_W - self::MARGIN_R, $lineY, 0.75);

        return $lineY - 12.0;
    }

    /** @param array<string, mixed> $stamp */
    private function drawSignatureStamp(string &$stream, float $y, int $pageIndex, array $stamp): float
    {
        if ($stamp === []) {
            return $y;
        }

        $boxW = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $boxH = 88.0;
        $boxY = $y - $boxH;

        $stream .= "q\n1 1 1 rg\n" . self::MARGIN_L . ' ' . $boxY . ' ' . $boxW . ' ' . $boxH . " re\nf\nQ\n";
        $this->rect($stream, self::MARGIN_L, $boxY, $boxW, $boxH, 0.75, self::C_LINE);

        $sealX = self::MARGIN_L + 14.0;
        $sealY = $boxY + $boxH - 34.0;
        $stream .= "q\n" . self::C_BLACK . " rg\n";
        $stream .= sprintf('%.2F %.2F 24 24 re', $sealX, $sealY);
        $stream .= "f\nQ\n";
        $stream .= "q\n" . self::C_YELLOW . " RG\n1.2 w\n";
        $stream .= sprintf('%.2F %.2F 24 24 re', $sealX, $sealY);
        $stream .= "S\nQ\n";

        $roleLabel = (string) ($stamp['role_label'] ?? 'Assinatura eletronica');
        $tx = $sealX + 34.0;
        $ty = $boxY + $boxH - 24.0;
        $stream .= "BT\n/F2 9.5 Tf\n" . self::C_BLACK . " rg\n1 0 0 1 {$tx} {$ty} Tm\n";
        $stream .= PdfText::literal($roleLabel) . " Tj\nET\n";

        $rows = [
            ['Nome', (string) ($stamp['signer_name'] ?? '')],
            ['Documento', (string) ($stamp['signer_document'] ?? '-')],
            ['Data', (string) ($stamp['signed_at'] ?? '')],
            ['Codigo', (string) ($stamp['verification_code'] ?? '')],
        ];
        $ly = $ty - 16.0;
        $col2 = self::MARGIN_L + 118.0;
        foreach ($rows as [$label, $value]) {
            $stream .= "BT\n/F1 7.5 Tf\n" . self::C_MUTED . " rg\n1 0 0 1 {$tx} {$ly} Tm\n";
            $stream .= PdfText::literal($label . ':') . " Tj\nET\n";
            $stream .= "BT\n/F2 8 Tf\n" . self::C_BLACK . " rg\n1 0 0 1 {$col2} {$ly} Tm\n";
            $stream .= PdfText::literal($value) . " Tj\nET\n";
            $ly -= 11.0;
        }

        $auditUrl = (string) ($stamp['audit_url'] ?? '');
        if ($auditUrl !== '') {
            $linkY = $boxY + 8.0;
            $stream .= "BT\n/F2 7 Tf\n0 0.45 0.55 rg\n1 0 0 1 {$tx} {$linkY} Tm\n";
            $stream .= PdfText::literal('Verificar auditoria') . " Tj\nET\n";
            $this->linkAnnotations[] = [
                'page' => $pageIndex,
                'rect' => [$tx, $linkY - 2, $tx + 90, $linkY + 10],
                'url'  => $auditUrl,
            ];
        }

        return $boxY - 8.0;
    }

    private function drawFooter(string &$stream, int $pageNum, int $pageCount): void
    {
        $lineY = self::MARGIN_B + self::FOOTER_H - 4.0;
        $this->hline($stream, self::MARGIN_L, self::PAGE_W - self::MARGIN_R, $lineY, 0.5);

        $left = (string) ($this->branding['producer_legal_line'] ?? 'JA Produções — Responsável jurídica pela contratação');
        $right = (string) ($this->branding['footer_line'] ?? 'Dança Carajás Captação');
        $center = "Página {$pageNum} de {$pageCount}";
        $textY = self::MARGIN_B + 16.0;

        $stream .= "BT\n/F1 7.5 Tf\n" . self::C_MUTED . " rg\n1 0 0 1 " . self::MARGIN_L . " {$textY} Tm\n";
        $stream .= PdfText::literal($left) . " Tj\nET\n";
        $stream .= "BT\n/F1 7.5 Tf\n1 0 0 1 " . (self::PAGE_W / 2 - 24) . " {$textY} Tm\n";
        $stream .= PdfText::literal($center) . " Tj\nET\n";
        $stream .= "BT\n/F1 7.5 Tf\n1 0 0 1 " . (self::PAGE_W - self::MARGIN_R - 110) . " {$textY} Tm\n";
        $stream .= PdfText::literal($right) . " Tj\nET\n";
    }

    private function drawLogo(string &$stream, string $key, float $anchorX, float $anchorTopY, float $maxW, float $maxH, bool $rightAlign = false): void
    {
        if (!isset($this->loadedImages[$key], $this->imageObjects[$key])) {
            return;
        }
        $img = $this->loadedImages[$key];
        $obj = $this->imageObjects[$key];
        [$drawW, $drawH] = $this->scaleToFit((int) $img['width'], (int) $img['height'], $maxW, $maxH);
        $x = $rightAlign ? $anchorX - $drawW : $anchorX;
        $y = $anchorTopY - $drawH;
        $stream .= "q\n" . sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $drawW, $drawH, $x, $y) . "\n/" . $obj['name'] . " Do\nQ\n";
    }

    private function hline(string &$stream, float $x1, float $x2, float $y, float $w): void
    {
        $stream .= "q\n{$w} w\n" . self::C_LINE . " RG\n{$x1} {$y} m\n{$x2} {$y} l\nS\nQ\n";
    }

    private function rect(string &$stream, float $x, float $y, float $w, float $h, float $stroke, string $color): void
    {
        $stream .= "q\n{$stroke} w\n{$color} RG\n{$x} {$y} {$w} {$h} re\nS\nQ\n";
    }

    /** @return array{0:float,1:float} */
    private function scaleToFit(int $width, int $height, float $maxW, float $maxH): array
    {
        if ($width <= 0 || $height <= 0) {
            return [0.0, 0.0];
        }
        $ratio = min($maxW / $width, $maxH / $height);

        return [round($width * $ratio, 2), round($height * $ratio, 2)];
    }

    /** @return list<string> */
    private function wrapText(string $text, float $fontSize, float $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === []) {
            return [];
        }
        $lines = [];
        $current = '';
        $charW = $fontSize * 0.48;
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
}
