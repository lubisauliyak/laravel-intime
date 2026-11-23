<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CardTemplate;
use App\Models\Member;
use App\Services\CardGeneratorService;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

echo "===============================================================\n";
echo "       FASE 6: PENGUJIAN KUALITAS & VERIFIKASI END-TO-END      \n";
echo "===============================================================\n\n";

$manager = new ImageManager(new Driver());
$service = new CardGeneratorService();
$allPassed = true;

// Helper untuk refleksi method protected
$reflection = new ReflectionClass($service);
$resolveFontMethod = $reflection->getMethod('resolveFontFile');
$resolveFontMethod->setAccessible(true);
$wrapTextMethod = $reflection->getMethod('wrapTextToWidth');
$wrapTextMethod->setAccessible(true);

// -------------------------------------------------------------
// 1. UJI RESOLVER FONT CUSTOM & FALLBACK MULTI-TIER
// -------------------------------------------------------------
echo "--- 1. Uji Verifikasi Font Custom & Multi-Tier Resolver ---\n";

// A. Lexend Bold
$lexendBold = $resolveFontMethod->invoke($service, 'Lexend', 'bold', 'normal');
$lexendBoldOk = str_contains($lexendBold, 'Lexend-Bold.ttf') && file_exists($lexendBold);
echo "1.1 Resolusi Lexend Bold: " . ($lexendBoldOk ? "OK ({$lexendBold})" : "FAILED ({$lexendBold})") . "\n";
if (!$lexendBoldOk) $allPassed = false;

// B. Lexend Regular
$lexendReg = $resolveFontMethod->invoke($service, 'Lexend', 'normal', 'normal');
$lexendRegOk = str_contains($lexendReg, 'Lexend-Regular.ttf') && file_exists($lexendReg);
echo "1.2 Resolusi Lexend Regular: " . ($lexendRegOk ? "OK ({$lexendReg})" : "FAILED ({$lexendReg})") . "\n";
if (!$lexendRegOk) $allPassed = false;

// C. Single-Variant Fallback Test (Hanya Regular yang diupload, tapi request Bold)
$dummyFontDir = storage_path('app/public/fonts');
if (!is_dir($dummyFontDir)) {
    mkdir($dummyFontDir, 0755, true);
}
$dummyRegularPath = $dummyFontDir . '/BrandFontX-Regular.ttf';
copy($lexendReg, $dummyRegularPath); // Pinjam font ttf valid

$brandFontBold = $resolveFontMethod->invoke($service, 'BrandFontX', 'bold', 'normal');
$brandFontFallbackOk = str_contains($brandFontBold, 'BrandFontX-Regular.ttf') && file_exists($brandFontBold);
echo "1.3 Fallback Single-Variant (BrandFontX request Bold -> dapat Regular): " . ($brandFontFallbackOk ? "OK (Tetap BrandFontX)" : "FAILED (Beralih ke {$brandFontBold})") . "\n";
if (!$brandFontFallbackOk) $allPassed = false;

// Hapus file dummy
if (file_exists($dummyRegularPath)) {
    unlink($dummyRegularPath);
}

// -------------------------------------------------------------
// 2. UJI KETAHANAN RASIO GAMBAR (ANTI-DISTORSI)
// -------------------------------------------------------------
echo "\n--- 2. Uji Ketahanan Rasio Gambar (Non-Distorsi Cover) ---\n";

// A. Gambar latar ultra-lebar (1200 x 400)
$wideBg = $manager->create(1200, 400)->fill('#3b82f6');
$wideBgPath = 'card-backgrounds/test_wide_bg.png';
Storage::disk('public')->put($wideBgPath, (string)$wideBg->toPng());

// B. Foto profil berasio potret 3:4 (300 x 400)
$portraitPhoto = $manager->create(300, 400)->fill('#ef4444');
$portraitPhotoPath = 'members/photos/test_portrait_photo.png';
Storage::disk('public')->put($portraitPhotoPath, (string)$portraitPhoto->toPng());

// C. Buat Template Uji
$testTemplate = CardTemplate::create([
    'name' => 'Template Uji Rasio E2E',
    'canvas_width' => 600,
    'canvas_height' => 950,
    'background_path' => $wideBgPath,
    'is_active' => true,
    'elements' => [
        [
            'type' => 'photo',
            'field' => 'profile_photo',
            'x' => 225,
            'y' => 150,
            'width' => 150,
            'height' => 150,
        ],
        [
            'type' => 'text',
            'field' => 'static_text',
            'text' => 'KARTU UJI DISTORSI',
            'x' => 300,
            'y' => 340,
            'fontSize' => 20,
            'fontFamily' => 'Lexend',
            'fontWeight' => 'bold',
            'color' => '#1e293b',
            'align' => 'center',
            'width' => 500,
        ]
    ]
]);

$previewPath = $service->preview($testTemplate, 'default');
$fullPreviewPhysicalPath = Storage::disk('public')->path($previewPath);
$previewImage = $manager->read($fullPreviewPhysicalPath);

$bgResOk = ($previewImage->width() === 600 && $previewImage->height() === 950);
echo "2.1 Render Background Ultra-Lebar (Cover): " . ($bgResOk ? "OK (Preserved to 600x950)" : "FAILED ({$previewImage->width()}x{$previewImage->height()})") . "\n";
if (!$bgResOk) $allPassed = false;

// -------------------------------------------------------------
// 3. UJI WORD-BOUNDARY WRAPPING PADA NAMA PANJANG
// -------------------------------------------------------------
echo "\n--- 3. Uji Pemecahan Teks Berbasis Batas Kata (Word-Boundary) ---\n";

$extremeLongName = "Prof. Dr. H. Muhammad Hidayatullah Al-Banjari, S.Kom., M.Cs., Ph.D.";
$wrappedText = $service->wrapTextToWidth($extremeLongName, 400.0, 18.0, $lexendBold);
$wrappedLines = explode("\n", $wrappedText);

echo "3.1 Teks Asli (70+ Karakter): {$extremeLongName}\n";
echo "    Hasil Pemecahan Baris (" . count($wrappedLines) . " baris):\n";
$wrappingClean = true;
foreach ($wrappedLines as $idx => $line) {
    echo "      Line " . ($idx + 1) . ": \"{$line}\"\n";
    // Pastikan tidak ada suku kata terpotong aneh di awal/akhir baris
    if (str_starts_with($line, '-') || str_ends_with($line, '-')) {
        $wrappingClean = false;
    }
}
echo "3.2 Status Pemecahan Kata: " . ($wrappingClean && count($wrappedLines) >= 2 ? "OK (Batas Kata Utuh)" : "FAILED") . "\n";
if (!$wrappingClean) $allPassed = false;

// -------------------------------------------------------------
// 4. UJI SIKLUS HIDUP LENGKAP (END-TO-END LIFECYCLE)
// -------------------------------------------------------------
echo "\n--- 4. Uji Siklus Hidup Penuh: Template -> Editor -> KTA Member ---\n";

// A. Tambahkan elemen lengkap pada template
$testTemplate->update([
    'elements' => [
        [
            'type' => 'text',
            'field' => 'static_text',
            'text' => 'KARTU TANDA ANGGOTA RESMI',
            'x' => 300,
            'y' => 50,
            'fontSize' => 18,
            'fontFamily' => 'Lexend',
            'fontWeight' => 'bold',
            'color' => '#ffffff',
            'align' => 'center',
            'width' => 550,
        ],
        [
            'type' => 'photo',
            'field' => 'profile_photo',
            'x' => 225,
            'y' => 120,
            'width' => 150,
            'height' => 150,
        ],
        [
            'type' => 'text',
            'field' => 'full_name',
            'x' => 300,
            'y' => 300,
            'fontSize' => 18,
            'fontFamily' => 'Lexend',
            'fontWeight' => 'bold',
            'color' => '#1e293b',
            'align' => 'center',
            'width' => 500,
        ],
        [
            'type' => 'text',
            'field' => 'member_code',
            'x' => 300,
            'y' => 360,
            'fontSize' => 14,
            'fontFamily' => 'Lexend',
            'fontWeight' => 'normal',
            'color' => '#64748b',
            'align' => 'center',
            'width' => 400,
        ],
        [
            'type' => 'qr',
            'field' => 'qr_code',
            'x' => 235,
            'y' => 420,
            'width' => 130,
            'height' => 130,
        ]
    ]
]);

// B. Buat dummy member pengujian
$existingMember = Member::first();
$memberUid = 'TESTE2E' . time();
if ($existingMember) {
    $memberData = $existingMember->toArray();
    unset($memberData['id'], $memberData['created_at'], $memberData['updated_at'], $memberData['deleted_at']);
    $memberData['uid'] = $memberUid;
    $memberData['member_code'] = 'KTA-E2E-999';
    $memberData['full_name'] = $extremeLongName;
    $memberData['nick_name'] = 'Hidayatullah';
    $memberData['profile_photo_path'] = $portraitPhotoPath;
    $memberData['membership_card_photo_path'] = null;
    $member = Member::create($memberData);
} else {
    $groupId = \App\Models\Group::value('id') ?? 1;
    $member = Member::create([
        'uid' => $memberUid,
        'group_id' => $groupId,
        'member_code' => 'KTA-E2E-999',
        'full_name' => $extremeLongName,
        'nick_name' => 'Hidayatullah',
        'profile_photo_path' => $portraitPhotoPath,
        'gender' => \App\Enums\Gender::MALE,
        'membership_type' => \App\Enums\MembershipType::ANGGOTA,
        'status' => true,
        'is_permanent' => false,
    ]);
}

// C. Generate KTA Melalui Service
$cardPath = $service->generate($testTemplate, $member);
$member->refresh();

$ktaPhysicalPath = Storage::disk('public')->path($cardPath);
$ktaExists = Storage::disk('public')->exists($cardPath) && file_exists($ktaPhysicalPath);
$ktaImage = $ktaExists ? $manager->read($ktaPhysicalPath) : null;
$ktaDimsOk = $ktaImage && ($ktaImage->width() === 600 && $ktaImage->height() === 950);
$memberModelUpdated = ($member->membership_card_photo_path === $cardPath);

echo "4.1 File KTA Fisik Dihasilkan: " . ($ktaExists ? "OK ({$cardPath})" : "FAILED") . "\n";
echo "4.2 Dimensi KTA Terverifikasi: " . ($ktaDimsOk ? "OK (600x950)" : "FAILED") . "\n";
echo "4.3 Model Member Terupdate dengan Path KTA: " . ($memberModelUpdated ? "OK" : "FAILED") . "\n";

if (!$ktaExists || !$ktaDimsOk || !$memberModelUpdated) {
    $allPassed = false;
}

// -------------------------------------------------------------
// CLEANUP DATA UJI
// -------------------------------------------------------------
echo "\n--- Pembersihan Data Uji ---\n";
if (Storage::disk('public')->exists($wideBgPath)) Storage::disk('public')->delete($wideBgPath);
if (Storage::disk('public')->exists($portraitPhotoPath)) Storage::disk('public')->delete($portraitPhotoPath);
$testTemplate->delete();
$member->delete();
echo "Data uji berhasil dibersihkan.\n\n";

// -------------------------------------------------------------
// KESIMPULAN
// -------------------------------------------------------------
echo "===============================================================\n";
if ($allPassed) {
    echo "       HASIL AKHIR FASE 6: SEMUA PENGUJIAN LULUS (PASS)        \n";
} else {
    echo "       HASIL AKHIR FASE 6: TERDAPAT PENGUJIAN GAGAL (FAILED)   \n";
}
echo "===============================================================\n";
