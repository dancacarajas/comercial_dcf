<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Carrega imagens para PDF — achata transparência sobre branco, redimensiona para embed leve.
 */
final class PdfImageLoader
{
    private const MAX_W = 360;

    /**
     * @return array{format:'rgb',width:int,height:int,color:string}|null
     */
    public static function fromFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        if (!extension_loaded('gd')) {
            if (self::isPng($path)) {
                $png = PdfPngImage::fromFile($path, true);
                if ($png === null) {
                    return null;
                }

                return [
                    'format' => 'rgb',
                    'width'  => $png['width'],
                    'height' => $png['height'],
                    'color'  => $png['color'],
                ];
            }

            return null;
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $gd = @imagecreatefromstring($data);
        if ($gd === false) {
            return null;
        }

        return self::flattenGdImage($gd);
    }

    private static function isPng(string $path): bool
    {
        $head = file_get_contents($path, false, null, 0, 8);

        return $head === "\x89PNG\r\n\x1a\n";
    }

    /** @return array{format:'rgb',width:int,height:int,color:string} */
    private static function flattenGdImage(\GdImage $source): array
    {
        $width  = imagesx($source);
        $height = imagesy($source);

        if ($width > self::MAX_W) {
            $newH = (int) max(1, round($height * (self::MAX_W / $width)));
            $resized = imagecreatetruecolor(self::MAX_W, $newH);
            if ($resized !== false) {
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefill($resized, 0, 0, $white);
                imagealphablending($resized, true);
                imagecopyresampled($resized, $source, 0, 0, 0, 0, self::MAX_W, $newH, $width, $height);
                imagedestroy($source);
                $source = $resized;
                $width = self::MAX_W;
                $height = $newH;
            }
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('Falha ao preparar imagem.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $color = '';
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgba = imagecolorat($canvas, $x, $y);
                $color .= chr(($rgba >> 16) & 0xFF)
                    . chr(($rgba >> 8) & 0xFF)
                    . chr($rgba & 0xFF);
            }
        }

        imagedestroy($canvas);

        return [
            'format' => 'rgb',
            'width'  => $width,
            'height' => $height,
            'color'  => $color,
        ];
    }

    /**
     * @param array{format:string,width:int,height:int,color:string} $img
     * @return array{name:string,colorId:int,maskId:?int,objects:array<int,string>}
     */
    public static function toPdfObjects(array $img, int &$nextId): array
    {
        $colorId = $nextId++;
        $colorStream = (string) ($img['color'] ?? '');
        $objects = [];
        $objects[$colorId] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $img['width']
            . ' /Height ' . (int) $img['height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length ' . strlen($colorStream)
            . " >>\nstream\n" . $colorStream . "\nendstream";

        return [
            'name'    => 'Im' . $colorId,
            'colorId' => $colorId,
            'maskId'  => null,
            'objects' => $objects,
        ];
    }
}
