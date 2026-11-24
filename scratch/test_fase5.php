<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Filament\Resources\CardTemplates\Pages\EditCardTemplate;
use App\Filament\Resources\CardTemplates\Pages\ViewCardTemplate;
use App\Filament\Resources\CardTemplates\Tables\CardTemplatesTable;
use App\Models\CardTemplate;
use Filament\Tables\Table;

echo "=== TEST FASE 5: INTEGRASI FILAMENT ADMIN & ALUR NAVIGASI ===\n";

// 1. Uji Kolom dan Action di CardTemplatesTable
$table = Table::make(new \App\Filament\Resources\CardTemplates\Pages\ListCardTemplates);
$configuredTable = CardTemplatesTable::configure($table);

$columns = $configuredTable->getColumns();
$hasPreviewColumn = isset($columns['preview_path']);
echo "1. Kolom 'preview_path' terpasang di CardTemplatesTable: ".($hasPreviewColumn ? 'OK' : 'FAILED')."\n";

$actions = $configuredTable->getActions();
$hasEditorAction = false;
foreach ($actions as $action) {
    if ($action->getName() === 'editor') {
        $hasEditorAction = true;
        break;
    }
}
echo "2. Aksi baris 'editor' (Buka Editor) terpasang di CardTemplatesTable: ".($hasEditorAction ? 'OK' : 'FAILED')."\n";

// 2. Uji Action di EditCardTemplate
$editPage = new class extends EditCardTemplate
{
    public function getTestHeaderActions(): array
    {
        return $this->getHeaderActions();
    }
};
$editHeaderActions = $editPage->getTestHeaderActions();
$hasEditEditorAction = false;
foreach ($editHeaderActions as $action) {
    if ($action->getName() === 'editor') {
        $hasEditEditorAction = true;
        break;
    }
}
echo "3. Header Action 'editor' terpasang di EditCardTemplate: ".($hasEditEditorAction ? 'OK' : 'FAILED')."\n";

// 3. Uji Action di ViewCardTemplate
$viewPage = new class extends ViewCardTemplate
{
    public function getTestHeaderActions(): array
    {
        return $this->getHeaderActions();
    }
};
$viewHeaderActions = $viewPage->getTestHeaderActions();
$hasViewEditorAction = false;
$viewIcon = null;
foreach ($viewHeaderActions as $action) {
    if ($action->getName() === 'editor') {
        $hasViewEditorAction = true;
        $viewIcon = $action->getIcon();
        break;
    }
}
echo "4. Header Action 'editor' terpasang di ViewCardTemplate: ".($hasViewEditorAction ? 'OK' : 'FAILED')."\n";
echo '   Icon di ViewCardTemplate: '.($viewIcon === 'heroicon-o-paint-brush' ? 'OK (heroicon-o-paint-brush)' : "FAILED ({$viewIcon})")."\n";

// 4. Uji Render Blade Card Editor (Navigation Link ke Admin)
$template = CardTemplate::first() ?? CardTemplate::create([
    'name' => 'Template Uji Fase 5',
    'canvas_width' => 600,
    'canvas_height' => 950,
    'is_active' => true,
]);

$customFonts = [];
$qrPlaceholderUri = 'data:image/png;base64,sample';
$html = view('card-editor.index', compact('template', 'customFonts', 'qrPlaceholderUri'))->render();

$hasAdminLink = strpos($html, url('/admin/card-templates')) !== false;
$hasKembaliTitle = strpos($html, 'Kembali ke Panel Admin') !== false;
echo "5. Tombol 'Admin' & Link kembali ke Filament terpasang di Blade: ".($hasAdminLink && $hasKembaliTitle ? 'OK' : 'FAILED')."\n";

echo "\n=== SEMUA PENGUJIAN FASE 5 SELESAI ===\n";
