<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\Member;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CardGeneratorService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function generate(CardTemplate $template, Member $member): string
    {
        return $this->processImage($template, clone $member, false);
    }

    public function preview(CardTemplate $template, string $scenario = 'default', ?array $overrideElements = null): string
    {
        $dummyMember = new Member([
            'uid' => 'preview-123',
            'member_code' => $scenario === 'long' ? 'KTA-1007001999' : 'KTA-1007001',
            'full_name' => $scenario === 'long' ? 'Prof. Dr. H. Muhammad Hidayatullah Al-Banjari, S.Kom., M.Cs., Ph.D.' : 'Ahmad Fauzi',
            'nick_name' => $scenario === 'long' ? 'Hidayatullah' : 'Ahmad',
        ]);
        
        return $this->processImage($template, $dummyMember, true, $scenario, $overrideElements);
    }

    protected function processImage(
        CardTemplate $template,
        Member $member,
        bool $isPreview = false,
        string $scenario = 'default',
        ?array $overrideElements = null
    ): string {
        $width = $template->canvas_width ?? 600;
        $height = $template->canvas_height ?? 950;

        $image = $this->manager->create($width, $height)->fill('ffffff');

        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            $bgPath = Storage::disk('public')->path($template->background_path);
            $bg = $this->manager->read($bgPath);
            $bg->cover($width, $height);
            $image->place($bg, 'top-left', 0, 0);
        }

        $elements = $overrideElements ?? $template->elements ?? [];

        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'text') {
                if (($el['field'] ?? '') === 'static_text' || empty($el['field'])) {
                    $text = (string) ($el['text'] ?? 'Teks');
                } else {
                    $text = $this->getFieldValue($member, $el['field'] ?? '', $isPreview, $scenario);
                }
                
                $fontFamily = $el['fontFamily'] ?? 'Inter';
                $fontWeight = $el['fontWeight'] ?? 'normal';
                $fontStyle = $el['fontStyle'] ?? 'normal';
                
                $fontFile = $this->resolveFontFile($fontFamily, $fontWeight, $fontStyle);

                $align = $el['textAlign'] ?? 'left';
                $originX = $el['originX'] ?? 'left';
                $originY = $el['originY'] ?? 'top';
                $x = (float) ($el['left'] ?? 0);
                $y = (float) ($el['top'] ?? 0);
                $width = (float) ($el['width'] ?? 0);
                
                if ($originX === 'center') {
                    if ($align === 'left') {
                        $anchorX = $x - ($width / 2);
                    } elseif ($align === 'right') {
                        $anchorX = $x + ($width / 2);
                    } else { // center
                        $anchorX = $x;
                    }
                } else {
                    if ($align === 'left') {
                        $anchorX = $x;
                    } elseif ($align === 'right') {
                        $anchorX = $x + $width;
                    } else { // center
                        $anchorX = $x + ($width / 2);
                    }
                }

                if ($originY === 'center') {
                    $anchorY = $y;
                    $vAlign = 'middle';
                } else {
                    $anchorY = $y;
                    $vAlign = 'top';
                }
                
                $fontSize = (int) ($el['fontSize'] ?? 24);
                if ($width > 0) {
                    $text = $this->wrapTextToWidth($text, $width, (float) $fontSize, $fontFile);
                }

                $image->text($text, $anchorX, $anchorY, function ($font) use ($el, $fontFile, $align, $vAlign) {
                    $font->filename($fontFile);
                    $font->size((int) ($el['fontSize'] ?? 24));
                    $font->color($el['fill'] ?? '#000000');
                    $font->align($align);
                    $font->valign($vAlign);
                    $font->lineHeight(1.16); // Standard fabric.js line height
                });
            } elseif (($el['type'] ?? '') === 'qrcode') {
                $code = $member->member_code ?? 'QR-CODE';
                $qrSize = (int) ($el['width'] ?? 150);
                
                $x = (int) ($el['left'] ?? 0);
                $y = (int) ($el['top'] ?? 0);
                $originX = $el['originX'] ?? 'left';
                $originY = $el['originY'] ?? 'top';

                $placeX = $originX === 'center' ? $x - (int)($qrSize / 2) : $x;
                $placeY = $originY === 'center' ? $y - (int)($qrSize / 2) : $y;
                
                $qrData = \App\Services\QrGenerator::generatePng($code, $qrSize, 0);
                $qrImage = $this->manager->read($qrData);
                $image->place($qrImage, 'top-left', $placeX, $placeY);
            } elseif (($el['type'] ?? '') === 'photo') {
                $photoSize = (int) ($el['width'] ?? 150);
                $photoPath = null;
                
                if (!$isPreview && $member->profile_photo_path && Storage::disk('public')->exists($member->profile_photo_path)) {
                    $photoPath = Storage::disk('public')->path($member->profile_photo_path);
                }
                
                $x = (int) ($el['left'] ?? 0);
                $y = (int) ($el['top'] ?? 0);
                $originX = $el['originX'] ?? 'left';
                $originY = $el['originY'] ?? 'top';

                $placeX = $originX === 'center' ? $x - (int)($photoSize / 2) : $x;
                $placeY = $originY === 'center' ? $y - (int)($photoSize / 2) : $y;
                
                if ($photoPath) {
                    $photoImage = $this->manager->read($photoPath);
                    $photoImage->cover($photoSize, $photoSize);
                    $image->place($photoImage, 'top-left', $placeX, $placeY);
                } else {
                    // Fallback placeholder with avatar icon
                    $placeholderPath = public_path('images/default-avatar.png');
                    if (file_exists($placeholderPath)) {
                        $placeholder = $this->manager->read($placeholderPath);
                        $placeholder->cover($photoSize, $photoSize);
                        $image->place($placeholder, 'top-left', $placeX, $placeY);
                    } else {
                        // Very plain fallback
                        $placeholder = $this->manager->create($photoSize, $photoSize)->fill('#e2e8f0');
                        $image->place($placeholder, 'top-left', $placeX, $placeY);
                    }
                }
            }
        }

        if ($isPreview) {
            $filename = 'preview_' . $template->id . ($scenario !== 'default' ? '_' . $scenario : '') . '.png';
            $path = 'kta/previews/' . $filename;
        } else {
            $filename = $member->uid . '.png';
            $path = 'kta/' . $filename;
        }

        // Save image to storage
        $imageData = (string) $image->toPng();
        Storage::disk('public')->put($path, $imageData);

        if (!$isPreview) {
            $member->update(['membership_card_photo_path' => $path]);
        }

        return $path;
    }

    protected function getFieldValue(Member $member, string $field, bool $isPreview, string $scenario = 'default'): string
    {
        if ($isPreview) {
            if ($scenario === 'long') {
                $previewData = [
                    'full_name' => 'Prof. Dr. H. Muhammad Hidayatullah Al-Banjari, S.Kom., M.Cs., Ph.D.',
                    'nick_name' => 'Hidayatullah',
                    'member_code' => 'KTA-1007001999',
                    'group_name' => 'Kelompok Mandiri Sejahtera Abadi Jaya',
                    'village_name' => 'Desa Karanganyar Kulon Progo',
                    'region_name' => 'Daerah Istimewa Yogyakarta Selatan',
                    'city_name' => 'Kabupaten Sidoarjo Timur',
                    'age_group' => 'Dewasa Madya',
                    'age' => '38',
                    'gender' => 'Laki-laki',
                    'membership_type' => 'Ketua Pengurus Harian',
                ];
            } else {
                $previewData = [
                    'full_name' => 'Ahmad Fauzi',
                    'nick_name' => 'Ahmad',
                    'member_code' => 'KTA-1007001',
                    'group_name' => 'Kelompok Al-Ikhlas',
                    'village_name' => 'Desa Sukamaju',
                    'region_name' => 'Daerah Barat',
                    'city_name' => 'Kota Surabaya',
                    'age_group' => 'Dewasa',
                    'age' => '25',
                    'gender' => 'Laki-laki',
                    'membership_type' => 'Pengurus',
                ];
            }
            return $previewData[$field] ?? 'Contoh Teks';
        }

        return match ($field) {
            'group_name' => $member->group ? $member->group->name : '-',
            'village_name' => $member->group ? ($member->group->getParentAtLevel(2)->name ?? '-') : '-',
            'region_name' => $member->group ? ($member->group->getParentAtLevel(3)->name ?? '-') : '-',
            'city_name' => $member->group ? ($member->group->getParentAtLevel(4)->name ?? '-') : '-',
            'age_group' => $member->ageGroup ? $member->ageGroup->name : '-',
            'age' => $member->age ? (string) $member->age : '-',
            'gender' => $member->gender === \App\Enums\Gender::MALE || (is_string($member->gender) && strtolower($member->gender) === 'male') || (is_object($member->gender) && $member->gender->value === 'male') ? 'Laki-laki' : 'Perempuan',
            'membership_type' => $member->membership_type === \App\Enums\MembershipType::PENGURUS || (is_string($member->membership_type) && strtolower($member->membership_type) === 'pengurus') || (is_object($member->membership_type) && $member->membership_type->value === 'pengurus') ? 'Pengurus' : 'Anggota',
            default => $member->getAttribute($field) ? (string) $member->getAttribute($field) : '-',
        };
    }

    /**
     * Menyelesaikan path file font dengan strategi fallback cerdas 4 tingkat.
     * Menjaga keluarga font yang dipilih pengguna tetap dipertahankan meskipun hanya varian Regular yang diunggah.
     */
    public function resolveFontFile(string $fontFamily, string $fontWeight = 'normal', string $fontStyle = 'normal'): string
    {
        $fontSuffix = '-Regular';
        if ($fontWeight === 'bold' && $fontStyle === 'italic') {
            $fontSuffix = '-BoldItalic';
        } elseif ($fontWeight === 'bold') {
            $fontSuffix = '-Bold';
        } elseif ($fontStyle === 'italic') {
            $fontSuffix = '-Italic';
        }

        $fontName = str_replace(' ', '', $fontFamily);

        // 1. Cek di resource_path('fonts/...') - font bawaan sistem
        $builtinCandidates = [
            resource_path('fonts/' . $fontName . $fontSuffix . '.ttf'),
            resource_path('fonts/' . $fontName . $fontSuffix . '.otf'),
            resource_path('fonts/' . $fontName . '-Regular.ttf'),
            resource_path('fonts/' . $fontName . '.ttf'),
        ];
        foreach ($builtinCandidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        // 2. Cek di storage custom fonts (storage/app/public/fonts)
        $storageDir = storage_path('app/public/fonts');
        if (is_dir($storageDir)) {
            // Prioritas A: Varian spesifik (contoh: Lexend-Bold.ttf / .otf)
            $specificFiles = [
                $storageDir . '/' . $fontFamily . $fontSuffix . '.ttf',
                $storageDir . '/' . $fontFamily . $fontSuffix . '.otf',
                $storageDir . '/' . $fontName . $fontSuffix . '.ttf',
                $storageDir . '/' . $fontName . $fontSuffix . '.otf',
            ];
            foreach ($specificFiles as $file) {
                if (file_exists($file)) {
                    return $file;
                }
            }

            // Prioritas B: Nama keluarga murni (contoh: Lexend.ttf / .otf)
            $familyFiles = [
                $storageDir . '/' . $fontFamily . '.ttf',
                $storageDir . '/' . $fontFamily . '.otf',
                $storageDir . '/' . $fontName . '.ttf',
                $storageDir . '/' . $fontName . '.otf',
            ];
            foreach ($familyFiles as $file) {
                if (file_exists($file)) {
                    return $file;
                }
            }

            // Prioritas C: Varian Regular (contoh: Lexend-Regular.ttf / .otf)
            $regularFiles = [
                $storageDir . '/' . $fontFamily . '-Regular.ttf',
                $storageDir . '/' . $fontFamily . '-Regular.otf',
                $storageDir . '/' . $fontName . '-Regular.ttf',
                $storageDir . '/' . $fontName . '-Regular.otf',
            ];
            foreach ($regularFiles as $file) {
                if (file_exists($file)) {
                    return $file;
                }
            }

            // Prioritas D: Varian apa saja dalam keluarga yang sama (wildcard glob)
            $patternTtf = $storageDir . '/' . $fontFamily . '-*.ttf';
            $matchesTtf = glob($patternTtf);
            if (!empty($matchesTtf) && file_exists($matchesTtf[0])) {
                return $matchesTtf[0];
            }

            $patternOtf = $storageDir . '/' . $fontFamily . '-*.otf';
            $matchesOtf = glob($patternOtf);
            if (!empty($matchesOtf) && file_exists($matchesOtf[0])) {
                return $matchesOtf[0];
            }
        }

        // 3. Fallback Terakhir: Inter-Regular.ttf + Log Warning
        $defaultFont = resource_path('fonts/Inter-Regular.ttf');
        Log::warning(
            "Font '{$fontFamily}' (weight: {$fontWeight}, style: {$fontStyle}) tidak ditemukan di sistem maupun storage. Fallback ke Inter-Regular."
        );

        return $defaultFont;
    }

    /**
     * Memecah teks ke dalam beberapa baris berdasarkan batas lebar piksel asli (WYSIWYG).
     * Menggunakan metrik glyph FreeType GD untuk mengukur lebar riil tanpa memotong kata di tengah huruf.
     */
    public function wrapTextToWidth(string $text, float $maxWidth, float $fontSize, string $fontFile): string
    {
        if ($maxWidth <= 0 || empty($text)) {
            return $text;
        }

        // GD imagettfbbox menghitung ukuran dalam satuan poin (pt)
        $ptSize = floatval(ceil($fontSize * 0.75));
        $lines = [];
        $paragraphs = explode("\n", $text);

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph));
            if (empty($words) || (count($words) === 1 && $words[0] === '')) {
                $lines[] = '';
                continue;
            }

            $currentLine = '';

            foreach ($words as $word) {
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                $bbox = @imagettfbbox($ptSize, 0, $fontFile, $testLine);

                if ($bbox !== false) {
                    $lineWidth = abs($bbox[4] - $bbox[0]);
                    if ($lineWidth > $maxWidth && $currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    } else {
                        $currentLine = $testLine;
                    }
                } else {
                    // Fallback jika font file tidak dapat dibaca oleh imagettfbbox
                    $approxCharWidth = $fontSize * 0.55;
                    if (mb_strlen($testLine) * $approxCharWidth > $maxWidth && $currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    } else {
                        $currentLine = $testLine;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return implode("\n", $lines);
    }
}
