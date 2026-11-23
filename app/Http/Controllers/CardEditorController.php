<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardEditorController extends Controller
{
    public function index(CardTemplate $template)
    {
        $this->authorizeEditor($template);

        $customFonts = [];
        if (Storage::disk('public')->exists('fonts')) {
            $files = Storage::disk('public')->files('fonts');
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['ttf', 'otf'])) {
                    $fontName = pathinfo($file, PATHINFO_FILENAME);
                    // Remove common weight suffixes like -Regular to get the clean family name
                    $familyName = preg_replace('/-(Regular|Bold|Italic|BoldItalic)$/i', '', $fontName);
                    
                    $weight = 'normal';
                    $style = 'normal';
                    if (preg_match('/-BoldItalic$/i', $fontName)) {
                        $weight = 'bold';
                        $style = 'italic';
                    } elseif (preg_match('/-Bold$/i', $fontName)) {
                        $weight = 'bold';
                    } elseif (preg_match('/-Italic$/i', $fontName)) {
                        $style = 'italic';
                    }

                    $customFonts[$familyName][] = [
                        'url' => asset('storage/' . $file),
                        'weight' => $weight,
                        'style' => $style,
                    ];
                }
            }
        }
        
        $qrPlaceholderUri = 'data:image/png;base64,' . base64_encode(\App\Services\QrGenerator::generatePng('PREVIEW-QR-SAMPLE', 150, 0));
        
        return view('card-editor.index', compact('template', 'customFonts', 'qrPlaceholderUri'));
    }

    public function uploadFont(Request $request, CardTemplate $template)
    {
        $this->authorizeEditor($template);

        $request->validate([
            'font' => 'required|file|extensions:ttf,otf|max:5120',
        ], [
            'font.extensions' => 'Format font harus bertipe .ttf (TrueType) atau .otf (OpenType). Format web (.woff / .woff2) tidak didukung oleh generator kartu.',
            'font.max' => 'Ukuran file font maksimal 5MB.',
        ]);

        $file = $request->file('font');
        $fileName = $file->getClientOriginalName();
        $fontName = pathinfo($fileName, PATHINFO_FILENAME);
        $familyName = preg_replace('/-(Regular|Bold|Italic|BoldItalic)$/i', '', $fontName);

        $weight = 'normal';
        $style = 'normal';
        if (preg_match('/-BoldItalic$/i', $fontName)) {
            $weight = 'bold';
            $style = 'italic';
        } elseif (preg_match('/-Bold$/i', $fontName)) {
            $weight = 'bold';
        } elseif (preg_match('/-Italic$/i', $fontName)) {
            $style = 'italic';
        }

        // Check if font already exists
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('fonts/' . $fileName)) {
            return response()->json([
                'success' => false,
                'message' => 'File font ini (' . $fileName . ') sudah pernah diunggah. Silakan pilih dari daftar yang tersedia.'
            ], 422);
        }

        // Store directly in fonts/ to be accessible via storage
        $path = $file->storeAs('fonts', $fileName, 'public');

        return response()->json([
            'success' => true,
            'name' => $familyName,
            'weight' => $weight,
            'style' => $style,
            'url' => asset('storage/' . $path)
        ]);
    }

    public function uploadBackground(Request $request, CardTemplate $template)
    {
        $this->authorizeEditor($template);

        $request->validate([
            'background' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }

        $path = $request->file('background')->store('card-backgrounds', 'public');
        $template->update(['background_path' => $path]);

        return response()->json([
            'path' => asset('storage/' . $path)
        ]);
    }

    public function save(Request $request, CardTemplate $template)
    {
        $this->authorizeEditor($template);

        $request->validate([
            'elements' => 'nullable|array',
        ]);

        $template->update(['elements' => $request->elements ?? []]);

        try {
            $service = new \App\Services\CardGeneratorService();
            $path = $service->preview($template);
            $template->update(['preview_path' => $path]);
        } catch (\Exception $e) {
            // Log or ignore if preview generation fails, the elements are still saved
            \Illuminate\Support\Facades\Log::error('Failed to generate card preview: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    public function previewCard(Request $request, CardTemplate $template)
    {
        $this->authorizeEditor($template);

        $request->validate([
            'elements' => 'nullable|array',
            'scenario' => 'nullable|string|in:default,long',
        ]);

        try {
            $service = new \App\Services\CardGeneratorService();
            $scenario = $request->input('scenario', 'default');
            $path = $service->preview($template, $scenario, $request->input('elements'));

            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path) . '?t=' . time(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to render card preview modal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal merender pratinjau kartu: ' . $e->getMessage()
            ], 500);
        }
    }

    private function authorizeEditor(CardTemplate $template): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }

        if (!$user->can('openEditor', $template)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka atau mengedit Card Editor.');
        }
    }
}
