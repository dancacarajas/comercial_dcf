<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Incorpora PNG (RGB/RGBA) em objetos de imagem PDF — sem GD/Imagick.
 */
final class PdfPngImage
{
    /** @return array{width:int,height:int,color:string,mask:?string}|null */
    public static function fromFile(string $path, bool $flattenOnWhite = false): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if ($data === false || strlen($data) < 24 || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $offset = 8;
        $width = 0;
        $height = 0;
        $colorType = 0;
        $idat = '';

        while ($offset + 8 <= strlen($data)) {
            $len = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            $chunk = substr($data, $offset + 8, $len);

            if ($type === 'IHDR' && $len >= 13) {
                $width = unpack('N', substr($chunk, 0, 4))[1];
                $height = unpack('N', substr($chunk, 4, 4))[1];
                $colorType = ord($chunk[9]);
            } elseif ($type === 'IDAT') {
                $idat .= $chunk;
            } elseif ($type === 'IEND') {
                break;
            }

            $offset += 12 + $len;
        }

        if ($width <= 0 || $height <= 0 || $idat === '') {
            return null;
        }

        $raw = @gzuncompress($idat);
        if ($raw === false) {
            return null;
        }

        $bytesPerPixel = match ($colorType) {
            2       => 3,
            6       => 4,
            default => 0,
        };
        if ($bytesPerPixel === 0) {
            return null;
        }

        $rowBytes = $width * $bytesPerPixel;
        $colorLen = $width * $height * 3;
        $color = str_repeat("\0", $colorLen);
        $maskBytes = ($colorType === 6 && !$flattenOnWhite) ? str_repeat("\0", $width * $height) : null;
        $prevRow = str_repeat("\0", $rowBytes);
        $pos = 0;

        for ($y = 0; $y < $height; ++$y) {
            if ($pos >= strlen($raw)) {
                break;
            }

            $filter = ord($raw[$pos]);
            ++$pos;
            if ($pos + $rowBytes > strlen($raw)) {
                break;
            }

            $filtered = substr($raw, $pos, $rowBytes);
            $pos += $rowBytes;
            $row = self::unfilterRow($filter, $filtered, $prevRow, $bytesPerPixel);
            $prevRow = $row;

            for ($x = 0; $x < $width; ++$x) {
                $i = $x * $bytesPerPixel;
                $r = ord($row[$i]);
                $g = ord($row[$i + 1]);
                $b = ord($row[$i + 2]);
                $a = $bytesPerPixel === 4 ? ord($row[$i + 3]) : 255;

                if ($flattenOnWhite && $a < 255) {
                    $alpha = $a / 255;
                    $r = (int) round($r * $alpha + 255 * (1 - $alpha));
                    $g = (int) round($g * $alpha + 255 * (1 - $alpha));
                    $b = (int) round($b * $alpha + 255 * (1 - $alpha));
                    $a = 255;
                }

                $out = ($y * $width + $x) * 3;
                $color[$out]     = chr($r);
                $color[$out + 1] = chr($g);
                $color[$out + 2] = chr($b);

                if ($maskBytes !== null && !$flattenOnWhite) {
                    $maskBytes[$y * $width + $x] = chr($a);
                }
            }
        }

        return [
            'width'  => $width,
            'height' => $height,
            'color'  => $color,
            'mask'   => $maskBytes,
        ];
    }

    private static function unfilterRow(int $filter, string $row, string $prevRow, int $bpp): string
    {
        $rowLen = strlen($row);
        if ($filter === 0) {
            return $row;
        }

        $out = $row;
        for ($i = 0; $i < $rowLen; ++$i) {
            $x = $i % $bpp;
            $left = $i >= $bpp ? ord($out[$i - $bpp]) : 0;
            $up = $prevRow !== '' ? ord($prevRow[$i]) : 0;
            $upLeft = ($i >= $bpp && $prevRow !== '') ? ord($prevRow[$i - $bpp]) : 0;
            $v = ord($row[$i]);

            $value = match ($filter) {
                1       => ($v + $left) & 0xFF,
                2       => ($v + $up) & 0xFF,
                3       => ($v + (int) floor(($left + $up) / 2)) & 0xFF,
                4       => ($v + self::paethPredictor($left, $up, $upLeft)) & 0xFF,
                default => $v,
            };

            $out[$i] = chr($value);
        }

        return $out;
    }

    private static function paethPredictor(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        if ($pb <= $pc) {
            return $b;
        }

        return $c;
    }

    /**
     * @param array{width:int,height:int,color:string,mask:?string} $img
     * @return array{name:string,colorId:int,maskId:?int,objects:array<int,string>}
     */
    public static function toPdfObjects(array $img, int &$nextId): array
    {
        $colorId = $nextId++;
        $objects = [];
        $colorStream = $img['color'];
        $objects[$colorId] = '<< /Type /XObject /Subtype /Image /Width ' . $img['width']
            . ' /Height ' . $img['height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length ' . strlen($colorStream)
            . " >>\nstream\n" . $colorStream . "\nendstream";

        $maskId = null;
        if ($img['mask'] !== null) {
            $maskId = $nextId++;
            $maskStream = $img['mask'];
            $objects[$maskId] = '<< /Type /XObject /Subtype /Image /Width ' . $img['width']
                . ' /Height ' . $img['height']
                . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Length ' . strlen($maskStream)
                . " >>\nstream\n" . $maskStream . "\nendstream";
        }

        $name = 'Im' . $colorId;

        return [
            'name'    => $name,
            'colorId' => $colorId,
            'maskId'  => $maskId,
            'objects' => $objects,
        ];
    }
}
