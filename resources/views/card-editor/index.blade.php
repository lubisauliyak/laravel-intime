<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Editor - {{ $template->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,400;1,700&family=Montserrat:ital,wght@0,400;0,700;1,400;1,700&family=Roboto:ital,wght@0,400;0,700;1,400;1,700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&family=Lato:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        .canvas-container {
            margin: 0 auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            background: white;
        }
    </style>
</head>

<body class="bg-gray-100 h-screen flex overflow-hidden">
    <!-- Sidebar -->
    <div class="w-80 bg-white border-r flex flex-col h-full shrink-0">
        <div class="p-4 border-b flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-800">Card Editor</h1>
                <p class="text-sm text-gray-500 truncate max-w-[150px]" title="{{ $template->name }}">
                    {{ $template->name }}</p>
            </div>
            <a href="{{ url('/admin/card-templates') }}"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition shadow-2xs"
                title="Kembali ke Panel Admin">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Admin</span>
            </a>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-6">
            <!-- Background -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase mb-2">Latar Belakang</h3>
                <input type="file" id="bg-upload" class="hidden" accept="image/*">
                <button onclick="document.getElementById('bg-upload').click()"
                    class="w-full bg-blue-50 text-blue-600 border border-blue-200 rounded px-3 py-2 text-sm font-medium hover:bg-blue-100 transition">
                    Unggah Latar Belakang
                </button>
                <button onclick="removeBackground()"
                    class="w-full mt-2 bg-red-50 text-red-600 border border-red-200 rounded px-3 py-2 text-sm font-medium hover:bg-red-100 transition">
                    Hapus Latar Belakang
                </button>
            </div>

            <!-- Add Elements -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase mb-2">Tambah Elemen</h3>
                <div class="mb-2">
                    <button onclick="addText('static_text', 'Teks Judul / Label')"
                        class="w-full bg-indigo-50 text-indigo-700 border border-indigo-200 rounded px-2 py-2 text-xs font-semibold hover:bg-indigo-100 transition flex items-center justify-center gap-1">
                        <span>+ Teks Statis / Judul</span>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="addText('member_code', '<< ID Anggota >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">ID Anggota</button>
                    <button onclick="addPhoto()"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Foto Profil</button>
                    <button onclick="addText('full_name', '<< Nama Lengkap >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Nama Lengkap</button>
                    <button onclick="addText('nick_name', '<< Nama Panggilan >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Panggilan</button>
                    <button onclick="addText('age_group', '<< Kategori Usia >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Kategori Usia</button>
                    <button onclick="addText('age', '<< Usia >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Usia</button>
                    <button onclick="addText('gender', '<< Jenis Kelamin >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Jenis Kelamin</button>
                    <button onclick="addText('membership_type', '<< Status Kepengurusan >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Kepengurusan</button>
                    <button onclick="addText('group_name', '<< Nama Kelompok >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Kelompok</button>
                    <button onclick="addText('village_name', '<< Nama Desa >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Desa</button>
                    <button onclick="addText('region_name', '<< Nama Daerah >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Daerah</button>
                    <button onclick="addText('city_name', '<< Nama Kab/Kota >>')"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">Kab/Kota</button>
                    <button onclick="addQRCode()"
                        class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100">QR Code</button>
                </div>
            </div>

            <!-- Properties (Visible when element selected) -->
            <div id="properties-panel" class="hidden border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase mb-3">Properti</h3>

                <div class="space-y-3">
                    <div id="text-props">
                        <label class="block text-xs text-gray-600 mb-1">Isi Teks</label>
                        <textarea id="prop-text-content" rows="2"
                            class="w-full border rounded px-2 py-1 text-sm bg-white mb-2"
                            oninput="updateTextContent(this.value)" placeholder="Ketik teks di sini..."></textarea>

                        <label class="block text-xs text-gray-600 mb-1">Jenis Huruf</label>
                        <select id="prop-font" class="w-full border rounded px-2 py-1 text-sm bg-white mb-2"
                            onchange="updateSelected('fontFamily', this.value)">
                            <option value="Inter">Inter</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Roboto">Roboto</option>
                            <option value="Open Sans">Open Sans</option>
                            <option value="Lato">Lato</option>
                            <option value="Arial">Arial</option>
                            @if(count($customFonts) > 0)
                                <optgroup label="Custom Fonts" id="custom-fonts-group">
                                    @foreach(array_keys($customFonts) as $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </optgroup>
                            @else
                                <optgroup label="Custom Fonts" id="custom-fonts-group" class="hidden"></optgroup>
                            @endif
                        </select>

                        <input type="file" id="font-upload" class="hidden" accept=".ttf,.otf">
                        <button onclick="document.getElementById('font-upload').click()"
                            class="w-full bg-indigo-50 text-indigo-600 border border-indigo-200 rounded px-2 py-1 text-xs font-medium hover:bg-indigo-100 transition">
                            Unggah Font Baru
                        </button>
                        <p class="text-[10px] text-gray-400 mt-1">Format: .ttf, .otf (Maks. 5MB)</p>

                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Ukuran Huruf</label>
                                <input type="number" id="prop-size"
                                    class="w-full border rounded px-2 py-1 text-sm bg-white text-center"
                                    onchange="updateSelected('fontSize', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Warna Huruf</label>
                                <input type="color" id="prop-color" class="w-full h-8 border rounded px-1 py-1 bg-white"
                                    onchange="updateSelected('fill', this.value)">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Gaya Huruf</label>
                                <select id="prop-style" class="w-full border rounded px-2 py-1 text-sm bg-white"
                                    onchange="updateSelected('fontStyle', this.value)">
                                    <option value="normal">Normal</option>
                                    <option value="italic">Miring</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Ketebalan Huruf</label>
                                <select id="prop-weight" class="w-full border rounded px-2 py-1 text-sm bg-white"
                                    onchange="updateSelected('fontWeight', this.value)">
                                    <option value="normal">Normal</option>
                                    <option value="bold">Tebal</option>
                                </select>
                            </div>
                        </div>

                        <label class="block text-xs text-gray-600 mb-1 mt-2">Perataan Huruf</label>
                        <select id="prop-align" class="w-full border rounded px-2 py-1 text-sm bg-white"
                            onchange="updateSelected('textAlign', this.value)">
                            <option value="left">Kiri</option>
                            <option value="center">Tengah</option>
                            <option value="right">Kanan</option>
                        </select>
                    </div>

                    <div id="general-props">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Lebar (px)</label>
                                <input type="number" id="prop-width"
                                    class="w-full border rounded px-2 py-1 text-sm text-center bg-white"
                                    onchange="updateSize('width', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Tinggi (px)</label>
                                <input type="number" id="prop-height"
                                    class="w-full border rounded px-2 py-1 text-sm text-center bg-white"
                                    onchange="updateSize('height', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">X (px)</label>
                                <input type="number" id="prop-x"
                                    class="w-full border rounded px-2 py-1 text-sm text-center bg-white"
                                    onchange="updatePosition('x', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Y (px)</label>
                                <input type="number" id="prop-y"
                                    class="w-full border rounded px-2 py-1 text-sm text-center bg-white"
                                    onchange="updatePosition('y', this.value)">
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t">
                        <label class="block text-xs text-gray-600 mb-1">Rata Tengah</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button id="btn-center-h" onclick="centerHorizontal()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition">Horizontal</button>
                            <button id="btn-center-v" onclick="centerVertical()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition">Vertikal</button>
                        </div>
                    </div>

                    <div class="pt-2 border-t">
                        <label class="block text-xs text-gray-600 mb-1">Urutan Layer</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="bringToFront()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700"
                                title="Bawa ke Paling Depan">Paling Depan</button>
                            <button onclick="sendToBack()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700"
                                title="Kirim ke Paling Belakang">Paling Belakang</button>
                            <button onclick="bringForward()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700"
                                title="Maju Satu Langkah">Maju</button>
                            <button onclick="sendBackward()"
                                class="bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700"
                                title="Mundur Satu Langkah">Mundur</button>
                        </div>
                    </div>

                    <button onclick="deleteSelected()"
                        class="w-full bg-red-600 text-white rounded px-3 py-2 text-sm font-medium hover:bg-red-700 transition mt-4">
                        Hapus Elemen
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 border-t bg-gray-50 space-y-2">
            <button onclick="openPreviewModal()" id="btn-preview"
                class="w-full bg-blue-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-blue-700 transition flex justify-center items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>Pratinjau Kartu</span>
            </button>
            <button onclick="saveTemplate()" id="btn-save"
                class="w-full bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700 transition flex justify-center items-center shadow-sm">
                Simpan Desain
            </button>
            <a href="/admin/card-templates"
                class="w-full bg-white border text-gray-700 rounded px-4 py-2 text-sm font-medium hover:bg-gray-50 transition flex justify-center items-center">
                Kembali
            </a>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="flex-1 bg-gray-200 overflow-auto p-8 relative">
        <div class="fixed top-4 right-8 bg-white shadow rounded-lg border flex overflow-hidden z-40 items-center">
            <!-- Undo / Redo -->
            <button id="btn-undo" onclick="undo()"
                class="px-3 py-1.5 hover:bg-gray-100 border-r text-xs font-semibold text-gray-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                title="Batalkan (Ctrl+Z)" disabled>
                <span>↺</span>
            </button>
            <button id="btn-redo" onclick="redo()"
                class="px-3 py-1.5 hover:bg-gray-100 border-r text-xs font-semibold text-gray-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                title="Ulangi (Ctrl+Y)" disabled>
                <span>↻</span>
            </button>
            <!-- Zoom -->
            <button onclick="changeZoom(-0.1)"
                class="px-3 py-1.5 hover:bg-gray-100 border-r font-bold text-gray-700">-</button>
            <div class="px-3 py-1.5 text-xs font-medium text-gray-600 flex items-center justify-center min-w-[3.5rem]"
                id="zoom-level">100%</div>
            <button onclick="changeZoom(0.1)"
                class="px-3 py-1.5 hover:bg-gray-100 border-r font-bold text-gray-700">+</button>
            <button onclick="resetZoom()" class="px-3 py-1.5 hover:bg-gray-100 text-xs font-medium text-gray-700"
                title="Reset Zoom">Reset</button>
        </div>

        <div id="canvas-wrapper" style="width: max-content; margin: 0 auto;">
            <canvas id="c"></canvas>
        </div>
    </div>

    <script>
        const csrfToken = '{{ csrf_token() }}';
        const templateId = '{{ $template->id }}';
        const canvasWidth = {{ $template->canvas_width }};
        const canvasHeight = {{ $template->canvas_height }};
        const savedElements = {!! $template->elements ? json_encode($template->elements) : '[]' !!};
        const bgPath = '{{ $template->background_path ? asset("storage/" . $template->background_path) : "" }}';
        const customFonts = @json($customFonts);
        const qrPlaceholderUri = '{{ $qrPlaceholderUri }}';

        // Dynamically load custom fonts with multi-variant support
        async function loadCustomFonts() {
            for (const [name, variants] of Object.entries(customFonts)) {
                if (typeof variants === 'string') {
                    try {
                        const font = new FontFace(name, `url(${variants})`);
                        await font.load();
                        document.fonts.add(font);
                    } catch (err) {
                        console.error('Failed to load font', name, err);
                    }
                } else if (Array.isArray(variants)) {
                    for (const v of variants) {
                        try {
                            const descriptors = {};
                            if (v.weight) descriptors.weight = v.weight;
                            if (v.style) descriptors.style = v.style;
                            const font = new FontFace(name, `url(${v.url})`, descriptors);
                            await font.load();
                            document.fonts.add(font);
                        } catch (err) {
                            console.error('Failed to load font variant', name, v, err);
                        }
                    }
                }
            }
        }

        // Initialize Fabric JS
        const canvas = new fabric.Canvas('c', {
            width: canvasWidth,
            height: canvasHeight,
            backgroundColor: '#ffffff'
        });

        let currentZoom = 1;
        function changeZoom(delta) {
            currentZoom += delta;
            if (currentZoom < 0.1) currentZoom = 0.1;
            if (currentZoom > 5) currentZoom = 5;
            applyZoom();
        }

        function resetZoom() {
            currentZoom = 1;
            applyZoom();
        }

        function applyZoom() {
            canvas.setZoom(currentZoom);
            canvas.setWidth(canvasWidth * currentZoom);
            canvas.setHeight(canvasHeight * currentZoom);
            document.getElementById('zoom-level').innerText = Math.round(currentZoom * 100) + '%';
        }


        // Load Background if exists
        if (bgPath) {
            setBackgroundImage(bgPath);
        }

        // History / Undo-Redo State Management
        let history = [];
        let historyIndex = -1;
        let isHistoryLocked = true; // start locked while loading initial elements

        loadCustomFonts().then(() => {
            document.fonts.ready.then(() => {
                // Load saved elements
                if (savedElements && savedElements.length > 0) {
                    savedElements.forEach(el => {
                        if (el.type === 'text') {
                            addText(el.field, el.text, el);
                        } else if (el.type === 'qrcode') {
                            addQRCode(el);
                        } else if (el.type === 'photo') {
                            addPhoto(el);
                        }
                    });
                }
                canvas.renderAll();
                isHistoryLocked = false;
                saveState();
            });
        });

        // Event Listeners for UI
        document.getElementById('bg-upload').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('background', file);

            document.getElementById('btn-save').innerText = 'Mengunggah...';

            fetch(`/card-editor/${templateId}/upload-background`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let errMsg = data.message || 'Gagal upload latar belakang.';
                        if (data.errors && data.errors.background) {
                            errMsg = data.errors.background.join('\n');
                        }
                        throw new Error(errMsg);
                    }
                    return data;
                })
                .then(data => {
                    setBackgroundImage(data.path);
                    showToast('Latar belakang berhasil diunggah!', 'success');
                })
                .catch(err => {
                    console.error(err);
                    showToast(err.message || 'Gagal upload background', 'error');
                })
                .finally(() => {
                    document.getElementById('btn-save').innerText = 'Simpan Desain';
                    e.target.value = '';
                });
        });

        canvas.on('selection:created', showProperties);
        canvas.on('selection:updated', showProperties);
        canvas.on('selection:cleared', hideProperties);
        canvas.on('object:moving', showProperties);
        canvas.on('object:scaling', showProperties);
        canvas.on('object:modified', () => {
            saveState();
            const obj = canvas.getActiveObject();
            if (obj) showProperties({ selected: [obj] });
        });

        document.getElementById('font-upload').addEventListener('change', async function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('font', file);
            formData.append('_token', csrfToken);

            try {
                // Show loading indicator
                const uploadBtn = e.target.nextElementSibling;
                const originalText = uploadBtn.innerText;
                uploadBtn.innerText = 'Mengunggah...';
                uploadBtn.disabled = true;

                const response = await fetch(`/card-editor/${templateId}/upload-font`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Load the new font to the browser with variant descriptors
                    const descriptors = {};
                    if (data.weight) descriptors.weight = data.weight;
                    if (data.style) descriptors.style = data.style;
                    const font = new FontFace(data.name, `url(${data.url})`, descriptors);
                    await font.load();
                    document.fonts.add(font);

                    // Add to select options
                    const optgroup = document.getElementById('custom-fonts-group');
                    optgroup.classList.remove('hidden');

                    // Check if option already exists
                    let exists = Array.from(optgroup.children).some(opt => opt.value === data.name);
                    if (!exists) {
                        const option = document.createElement('option');
                        option.value = data.name;
                        option.text = data.name;
                        optgroup.appendChild(option);
                    }

                    // Automatically select it if text is active
                    const activeObject = canvas.getActiveObject();
                    if (activeObject && (activeObject.type === 'i-text' || activeObject.type === 'textbox' || activeObject.type === 'text')) {
                        document.getElementById('prop-font').value = data.name;
                        updateSelected('fontFamily', data.name);
                    }

                    showToast('Font berhasil diunggah!', 'success');
                } else {
                    let errMsg = data.message || 'Gagal mengunggah font.';
                    if (data.errors && data.errors.font) {
                        errMsg = data.errors.font.join('\n');
                    }
                    showToast(errMsg, 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Gagal mengunggah font. Error: ' + err.message, 'error');
            } finally {
                // Reset file input and button
                e.target.value = '';
                const uploadBtn = e.target.nextElementSibling;
                uploadBtn.innerText = 'Unggah Font Baru';
                uploadBtn.disabled = false;
            }
        });

        // Functions
        function setBackgroundImage(url) {
            const cacheBuster = (url.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
            fabric.Image.fromURL(url + cacheBuster, function (img) {
                if (!img || !img.width) {
                    console.error('Failed to load background image from', url);
                    showToast('Gagal memuat gambar latar belakang ke canvas.', 'error');
                    return;
                }
                // Scale to fit canvas
                const scale = Math.max(canvasWidth / img.width, canvasHeight / img.height);
                img.set({
                    originX: 'center',
                    originY: 'center',
                    left: canvasWidth / 2,
                    top: canvasHeight / 2,
                    scaleX: scale,
                    scaleY: scale
                });
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            }, { crossOrigin: 'anonymous' });
        }

        function removeBackground() {
            canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));
            // You might want to also delete it from server if needed
        }

        function addText(field, placeholder, options = {}) {
            const isNew = options.left === undefined;
            const text = new fabric.Textbox(placeholder, {
                originX: options.originX || (isNew ? 'center' : 'left'),
                originY: options.originY || (isNew ? 'center' : 'top'),
                left: options.left !== undefined ? options.left : canvasWidth / 2,
                top: options.top !== undefined ? options.top : canvasHeight / 2,
                width: options.width || 200,
                fontSize: options.fontSize || 24,
                fontFamily: options.fontFamily || 'Inter',
                fill: options.fill || '#000000',
                textAlign: options.textAlign || 'center',
                fontWeight: options.fontWeight || 'normal',
                fontStyle: options.fontStyle || 'normal',
                transparentCorners: false,
                cornerColor: 'blue',
                cornerSize: 8,
                splitByGrapheme: false,
            });
            text.set('customField', field);
            text.set('elementType', 'text');
            text.setControlsVisibility({ mt: false, mb: false }); // Disable vertical resizing for text

            // Convert legacy top-left origin to center origin
            if (text.originX !== 'center') {
                const center = text.getCenterPoint();
                text.set({
                    originX: 'center',
                    originY: 'center',
                    left: center.x,
                    top: center.y
                });
            }

            canvas.add(text);
            canvas.setActiveObject(text);
            canvas.renderAll();
            saveState();
        }

        function addQRCode(options = {}) {
            // Use local generated placeholder data URI for QR Code (offline-ready)
            const qrUrl = qrPlaceholderUri;
            fabric.Image.fromURL(qrUrl, function (img) {
                const isNew = options.left === undefined;
                img.set({
                    originX: options.originX || (isNew ? 'center' : 'left'),
                    originY: options.originY || (isNew ? 'center' : 'top'),
                    left: options.left !== undefined ? options.left : canvasWidth / 2,
                    top: options.top !== undefined ? options.top : canvasHeight / 2,
                    scaleX: options.scaleX || 1,
                    scaleY: options.scaleY || 1,
                    transparentCorners: false,
                    cornerColor: 'blue',
                    cornerSize: 8,
                });
                img.set('customField', 'qrcode');
                img.set('elementType', 'qrcode');
                img.setControlsVisibility({ mt: false, mb: false, ml: false, mr: false });

                if (img.originX !== 'center') {
                    const center = img.getCenterPoint();
                    img.set({ originX: 'center', originY: 'center', left: center.x, top: center.y });
                }

                let targetW = options.width || 150;
                let targetH = options.height || 150;
                applyCover(img, targetW, targetH);

                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.renderAll();
                saveState();
            }, { crossOrigin: 'anonymous' });
        }

        function addPhoto(options = {}) {
            // Use a proper avatar icon
            const photoUrl = '{{ asset("images/default-avatar.png") }}';
            fabric.Image.fromURL(photoUrl, function (img) {
                const isNew = options.left === undefined;
                img.set({
                    originX: options.originX || (isNew ? 'center' : 'left'),
                    originY: options.originY || (isNew ? 'center' : 'top'),
                    left: options.left !== undefined ? options.left : canvasWidth / 2,
                    top: options.top !== undefined ? options.top : canvasHeight / 2,
                    scaleX: options.scaleX || 1,
                    scaleY: options.scaleY || 1,
                    transparentCorners: false,
                    cornerColor: 'blue',
                    cornerSize: 8,
                });
                img.set('customField', 'photo');
                img.set('elementType', 'photo');
                img.setControlsVisibility({ mt: false, mb: false, ml: false, mr: false });

                if (img.originX !== 'center') {
                    const center = img.getCenterPoint();
                    img.set({ originX: 'center', originY: 'center', left: center.x, top: center.y });
                }

                let targetW = options.width || 150;
                let targetH = options.height || 150;
                applyCover(img, targetW, targetH);

                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.renderAll();
                saveState();
            }, { crossOrigin: 'anonymous' });
        }

        function showProperties(e) {
            const obj = e.selected ? e.selected[0] : e.target;
            if (!obj) return;

            document.getElementById('properties-panel').classList.remove('hidden');

            document.getElementById('prop-width').value = Math.round(obj.width * obj.scaleX);
            document.getElementById('prop-height').value = Math.round(obj.height * obj.scaleY);

            let centerX = obj.left;
            let centerY = obj.top;

            if (obj.originX !== 'center') {
                const center = obj.getCenterPoint();
                centerX = center.x;
                centerY = center.y;
            }

            document.getElementById('prop-x').value = Math.round(centerX);
            document.getElementById('prop-y').value = Math.round(centerY);

            const btnH = document.getElementById('btn-center-h');
            const btnV = document.getElementById('btn-center-v');
            const isCenteredH = Math.round(centerX) === Math.round(canvasWidth / 2);
            const isCenteredV = Math.round(centerY) === Math.round(canvasHeight / 2);

            if (isCenteredH) {
                btnH.className = "bg-blue-500 text-white border border-blue-600 rounded px-2 py-1.5 text-xs hover:bg-blue-600 transition";
            } else {
                btnH.className = "bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700";
            }

            if (isCenteredV) {
                btnV.className = "bg-blue-500 text-white border border-blue-600 rounded px-2 py-1.5 text-xs hover:bg-blue-600 transition";
            } else {
                btnV.className = "bg-gray-50 border rounded px-2 py-1.5 text-xs hover:bg-gray-100 transition text-gray-700";
            }

            if (obj.elementType === 'text') {
                document.getElementById('text-props').classList.remove('hidden');
                document.getElementById('prop-text-content').value = obj.text || '';
                document.getElementById('prop-font').value = obj.fontFamily;
                document.getElementById('prop-size').value = obj.fontSize;
                document.getElementById('prop-color').value = obj.fill;
                document.getElementById('prop-align').value = obj.textAlign;
                document.getElementById('prop-style').value = obj.fontStyle || 'normal';
                document.getElementById('prop-weight').value = obj.fontWeight || 'normal';

                document.getElementById('prop-height').disabled = true;
                document.getElementById('prop-height').title = "Tinggi teks otomatis dihitung";
            } else {
                document.getElementById('text-props').classList.add('hidden');
                document.getElementById('prop-height').disabled = false;
                document.getElementById('prop-height').title = "";
            }
        }

        function hideProperties() {
            document.getElementById('properties-panel').classList.add('hidden');
        }

        function updatePosition(prop, value) {
            const obj = canvas.getActiveObject();
            if (!obj) return;

            value = parseFloat(value);

            if (obj.originX !== 'center') {
                const center = obj.getCenterPoint();
                obj.set({
                    originX: 'center',
                    originY: 'center',
                    left: center.x,
                    top: center.y
                });
            }

            if (prop === 'x') {
                obj.set('left', value);
            } else if (prop === 'y') {
                obj.set('top', value);
            }

            obj.setCoords();
            canvas.renderAll();
            showProperties({ selected: [obj] });
            saveState();
        }

        function updateTextContent(value) {
            const obj = canvas.getActiveObject();
            if (!obj || obj.elementType !== 'text') return;
            obj.set('text', value);
            canvas.renderAll();
            saveState();
        }

        function bringToFront() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.bringToFront(obj);
            canvas.renderAll();
            saveState();
            showToast('Elemen dipindahkan ke paling depan', 'info');
        }

        function sendToBack() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.sendToBack(obj);
            canvas.renderAll();
            saveState();
            showToast('Elemen dipindahkan ke paling belakang', 'info');
        }

        function bringForward() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.bringForward(obj);
            canvas.renderAll();
            saveState();
        }

        function sendBackward() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.sendBackwards(obj);
            canvas.renderAll();
            saveState();
        }

        function updateSelected(prop, value) {
            const obj = canvas.getActiveObject();
            if (!obj) return;

            if (prop === 'fontSize') value = parseInt(value);

            obj.set(prop, value);
            canvas.renderAll();
            saveState();
        }

        function applyCover(img, targetWidth, targetHeight) {
            if (!img._element) return;

            const originalWidth = img._element.naturalWidth || img._element.width;
            const originalHeight = img._element.naturalHeight || img._element.height;

            if (!originalWidth || !originalHeight) return;

            const scaleX = targetWidth / originalWidth;
            const scaleY = targetHeight / originalHeight;
            const scale = Math.max(scaleX, scaleY);

            const cropWidth = targetWidth / scale;
            const cropHeight = targetHeight / scale;

            const cropX = (originalWidth - cropWidth) / 2;
            const cropY = (originalHeight - cropHeight) / 2;

            img.set({
                cropX: cropX,
                cropY: cropY,
                width: cropWidth,
                height: cropHeight,
                scaleX: scale,
                scaleY: scale
            });
        }

        function updateSize(prop, value) {
            const obj = canvas.getActiveObject();
            if (!obj) return;

            value = parseFloat(value);

            if (obj.elementType === 'text') {
                if (prop === 'width') {
                    obj.set('width', value / obj.scaleX);
                }
            } else {
                let targetWidth = obj.width * obj.scaleX;
                let targetHeight = obj.height * obj.scaleY;

                if (prop === 'width') {
                    targetWidth = value;
                } else if (prop === 'height') {
                    targetHeight = value;
                }

                applyCover(obj, targetWidth, targetHeight);
            }
            canvas.renderAll();
            showProperties({ selected: [obj] });
            saveState();
        }

        function deleteSelected() {
            const activeObjects = canvas.getActiveObjects();
            if (activeObjects.length) {
                canvas.discardActiveObject();
                activeObjects.forEach(function (object) {
                    canvas.remove(object);
                });
                canvas.renderAll();
                saveState();
                showToast('Elemen berhasil dihapus', 'info');
            }
        }

        function centerHorizontal() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.centerObjectH(obj);
            obj.setCoords();
            canvas.renderAll();
            showProperties({ selected: [obj] });
            saveState();
        }

        function centerVertical() {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            canvas.centerObjectV(obj);
            obj.setCoords();
            canvas.renderAll();
            showProperties({ selected: [obj] });
            saveState();
        }

        function saveTemplate() {
            const btn = document.getElementById('btn-save');
            btn.innerText = 'Menyimpan...';

            const objects = canvas.getObjects();
            const elementsData = [];
            objects.forEach(obj => {
                if (obj.elementType === 'text') {
                    elementsData.push({
                        type: 'text',
                        field: obj.customField,
                        text: obj.text,
                        left: obj.left,
                        top: obj.top,
                        width: obj.width * obj.scaleX,
                        fontSize: Math.round(obj.fontSize * obj.scaleY),
                        fontFamily: obj.fontFamily,
                        fill: obj.fill,
                        textAlign: obj.textAlign,
                        fontWeight: obj.fontWeight,
                        fontStyle: obj.fontStyle,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                } else if (obj.elementType === 'qrcode') {
                    elementsData.push({
                        type: 'qrcode',
                        field: 'qrcode',
                        left: obj.left,
                        top: obj.top,
                        width: obj.getScaledWidth(),
                        height: obj.getScaledHeight(),
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                } else if (obj.elementType === 'photo') {
                    elementsData.push({
                        type: 'photo',
                        field: 'photo',
                        left: obj.left,
                        top: obj.top,
                        width: obj.getScaledWidth(),
                        height: obj.getScaledHeight(),
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                }
            });

            fetch(`/card-editor/${templateId}/save`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ elements: elementsData })
            })
                .then(async res => {
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        throw new Error(err.message || 'Gagal menyimpan');
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Desain berhasil disimpan!', 'success');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Gagal menyimpan desain: ' + (err.message || ''), 'error');
                })
                .finally(() => {
                    btn.innerText = 'Simpan Desain';
                });
        }

        // Live Preview Modal Implementation
        let currentPreviewScenario = 'default';

        function getSerializedElements() {
            const objects = canvas.getObjects();
            const elementsData = [];
            objects.forEach(obj => {
                if (obj.elementType === 'text') {
                    elementsData.push({
                        type: 'text',
                        field: obj.customField,
                        text: obj.text,
                        left: obj.left,
                        top: obj.top,
                        width: obj.width * obj.scaleX,
                        fontSize: Math.round(obj.fontSize * obj.scaleY),
                        fontFamily: obj.fontFamily,
                        fill: obj.fill,
                        textAlign: obj.textAlign,
                        fontWeight: obj.fontWeight,
                        fontStyle: obj.fontStyle,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                } else if (obj.elementType === 'qrcode') {
                    elementsData.push({
                        type: 'qrcode',
                        field: 'qrcode',
                        left: obj.left,
                        top: obj.top,
                        width: obj.getScaledWidth(),
                        height: obj.getScaledHeight(),
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                } else if (obj.elementType === 'photo') {
                    elementsData.push({
                        type: 'photo',
                        field: 'photo',
                        left: obj.left,
                        top: obj.top,
                        width: obj.getScaledWidth(),
                        height: obj.getScaledHeight(),
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        originX: obj.originX,
                        originY: obj.originY
                    });
                }
            });
            return elementsData;
        }

        async function openPreviewModal(scenario = 'default') {
            currentPreviewScenario = scenario;
            updateScenarioTabStyles();
            document.getElementById('preview-modal').classList.remove('hidden');
            await loadServerPreview();
        }

        function closePreviewModal() {
            document.getElementById('preview-modal').classList.add('hidden');
        }

        async function switchPreviewScenario(scenario) {
            if (currentPreviewScenario === scenario) return;
            currentPreviewScenario = scenario;
            updateScenarioTabStyles();
            await loadServerPreview();
        }

        function updateScenarioTabStyles() {
            const tabDefault = document.getElementById('tab-scenario-default');
            const tabLong = document.getElementById('tab-scenario-long');
            if (currentPreviewScenario === 'default') {
                tabDefault.className = 'px-3 py-1 text-xs font-medium rounded-md bg-white text-blue-700 shadow-sm border border-blue-200 transition';
                tabLong.className = 'px-3 py-1 text-xs font-medium rounded-md bg-transparent text-gray-600 hover:bg-white/60 transition';
            } else {
                tabLong.className = 'px-3 py-1 text-xs font-medium rounded-md bg-white text-blue-700 shadow-sm border border-blue-200 transition';
                tabDefault.className = 'px-3 py-1 text-xs font-medium rounded-md bg-transparent text-gray-600 hover:bg-white/60 transition';
            }
        }

        async function loadServerPreview() {
            const loader = document.getElementById('preview-loading');
            const img = document.getElementById('preview-img');
            loader.classList.remove('hidden');

            try {
                const response = await fetch(`/card-editor/${templateId}/preview`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        scenario: currentPreviewScenario,
                        elements: getSerializedElements()
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    img.src = data.url;
                } else {
                    showToast(data.message || 'Gagal memuat pratinjau kartu', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Gagal memuat pratinjau kartu', 'error');
            } finally {
                loader.classList.add('hidden');
            }
        }

        function saveFromPreview() {
            closePreviewModal();
            saveTemplate();
        }

        // Undo & Redo implementations
        function saveState() {
            if (isHistoryLocked) return;
            if (historyIndex < history.length - 1) {
                history = history.slice(0, historyIndex + 1);
            }
            if (history.length > 30) {
                history.shift();
                historyIndex--;
            }
            const json = JSON.stringify(canvas.toDatalessJSON(['customField', 'elementType', 'selectable']));
            history.push(json);
            historyIndex = history.length - 1;
            updateUndoRedoButtons();
        }

        function undo() {
            if (historyIndex > 0) {
                isHistoryLocked = true;
                historyIndex--;
                canvas.loadFromJSON(history[historyIndex], function () {
                    canvas.renderAll();
                    isHistoryLocked = false;
                    updateUndoRedoButtons();
                    const active = canvas.getActiveObject();
                    if (active) showProperties({ selected: [active] });
                    else hideProperties();
                    showToast('Langkah dibatalkan (Undo)', 'info');
                });
            }
        }

        function redo() {
            if (historyIndex < history.length - 1) {
                isHistoryLocked = true;
                historyIndex++;
                canvas.loadFromJSON(history[historyIndex], function () {
                    canvas.renderAll();
                    isHistoryLocked = false;
                    updateUndoRedoButtons();
                    const active = canvas.getActiveObject();
                    if (active) showProperties({ selected: [active] });
                    else hideProperties();
                    showToast('Langkah diulangi (Redo)', 'info');
                });
            }
        }

        function updateUndoRedoButtons() {
            const btnUndo = document.getElementById('btn-undo');
            const btnRedo = document.getElementById('btn-redo');
            if (btnUndo) btnUndo.disabled = (historyIndex <= 0);
            if (btnRedo) btnRedo.disabled = (historyIndex >= history.length - 1);
        }

        // Toast Notification System
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            const bgColors = {
                success: 'bg-emerald-600 text-white shadow-emerald-500/20',
                error: 'bg-rose-600 text-white shadow-rose-500/20',
                info: 'bg-blue-600 text-white shadow-blue-500/20'
            };
            const icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ'
            };
            toast.className = `pointer-events-auto flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-lg text-sm font-medium transition-all duration-300 transform translate-y-3 opacity-0 ${bgColors[type] || bgColors.info}`;
            toast.innerHTML = `<span class="font-bold">${icons[type] || ''}</span><span>${message}</span>`;
            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            });
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-3');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Keyboard Event Listeners (Undo/Redo, Delete)
        window.addEventListener('keydown', function (e) {
            const isTyping = ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) ||
                (canvas.getActiveObject() && canvas.getActiveObject().isEditing);

            // Undo / Redo shortcuts
            if ((e.ctrlKey || e.metaKey) && !isTyping) {
                if (e.key === 'z' || e.key === 'Z') {
                    e.preventDefault();
                    if (e.shiftKey) {
                        redo();
                    } else {
                        undo();
                    }
                    return;
                } else if (e.key === 'y' || e.key === 'Y') {
                    e.preventDefault();
                    redo();
                    return;
                }
            }

            // Delete shortcut
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (!isTyping && canvas.getActiveObject()) {
                    deleteSelected();
                }
            }
        });
    </script>

    <!-- Live Preview Modal -->
    <div id="preview-modal"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
        <div
            class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <!-- Header -->
            <div class="px-6 py-4 border-b flex items-center justify-between bg-gray-50">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Pratinjau Hasil Cetak Kartu</h3>
                    <p class="text-xs text-gray-500">Dirender langsung oleh server generator gambar (WYSIWYG)</p>
                </div>
                <button onclick="closePreviewModal()"
                    class="text-gray-400 hover:text-gray-600 text-lg font-bold p-1">✕</button>
            </div>

            <!-- Scenario Switcher Tabs -->
            <div class="px-6 pt-3 pb-2 border-b bg-gray-100/70 flex items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 mr-1">Simulasi Data:</span>
                <button id="tab-scenario-default" onclick="switchPreviewScenario('default')"
                    class="px-3 py-1 text-xs font-medium rounded-md bg-white text-blue-700 shadow-sm border border-blue-200 transition">
                    Nama Standar
                </button>
                <button id="tab-scenario-long" onclick="switchPreviewScenario('long')"
                    class="px-3 py-1 text-xs font-medium rounded-md bg-transparent text-gray-600 hover:bg-white/60 transition">
                    Nama Sangat Panjang (Stress Test)
                </button>
            </div>

            <!-- Content / Image Container -->
            <div class="flex-1 overflow-y-auto p-6 bg-gray-200 flex items-center justify-center min-h-[420px] relative">
                <div id="preview-loading"
                    class="absolute inset-0 bg-gray-200/80 backdrop-blur-xs flex flex-col items-center justify-center z-10 hidden">
                    <div class="w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-medium text-gray-600 mt-2">Merender kartu dari server...</p>
                </div>
                <img id="preview-img" src="" alt="Pratinjau KTA"
                    class="max-h-[65vh] w-auto object-contain rounded shadow-lg border bg-white">
            </div>

            <!-- Footer -->
            <div class="px-6 py-3 border-t bg-gray-50 flex items-center justify-between">
                <span class="text-xs text-gray-400" id="preview-meta-dimensions">{{ $template->canvas_width }} x
                    {{ $template->canvas_height }} px</span>
                <div class="flex items-center gap-2">
                    <button onclick="closePreviewModal()"
                        class="px-4 py-2 border rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition">
                        Tutup
                    </button>
                    <button onclick="saveFromPreview()"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium transition flex items-center gap-1 shadow-sm">
                        Simpan Desain Ini
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none"></div>
</body>

</html>