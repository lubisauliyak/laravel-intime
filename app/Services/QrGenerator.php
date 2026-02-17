<?php

namespace App\Services;

use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Common\ErrorCorrectionLevel;

class QrGenerator
{
    public static function generatePng(string $data, int $size = 500, int $margin = 2): string
    {
        // 1. Get the QR Matrix (e.g. 21x21 or 25x25)
        $qrCode = Encoder::encode($data, ErrorCorrectionLevel::H());
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();
        
        // 2. Calculate scale
        // Total cells including margin
        $totalMatrixSize = $matrixSize + ($margin * 2);
        $cellSize = (int) floor($size / $totalMatrixSize);
        
        // Final image size might be slightly smaller than $size due to floor()
        $finalSize = $cellSize * $totalMatrixSize;
        
        // 3. Create GD Image
        $image = imagecreatetruecolor($finalSize, $finalSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fill white background
        imagefill($image, 0, 0, $white);
        
        // 4. Draw cells
        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix->get($x, $y)) {
                    $x1 = ($x + $margin) * $cellSize;
                    $y1 = ($y + $margin) * $cellSize;
                    $x2 = $x1 + $cellSize - 1;
                    $y2 = $y1 + $cellSize - 1;
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
                }
            }
        }
        
        // 5. Capture PNG output
        ob_start();
        imagepng($image);
        $result = ob_get_clean();
        
        // Cleanup
        imagedestroy($image);
        
        return $result;
    }
}
