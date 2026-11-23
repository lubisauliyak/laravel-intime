# Dokumen Rencana: Halaman Publik Preview Data Grup & Anggota

Dokumen ini memuat perencanaan teknis untuk membuat **halaman publik tanpa login** yang menampilkan data kehadiran per grup dan per anggota, bertujuan untuk dibagikan kepada **pembina grup** dan **wali anggota**.

Prinsip utama:
1. **Tanpa Login:** Pembina/wali cukup buka link, tidak perlu akun.
2. **Link Pendek & Aman:** Identifier pendek (kode grup) + HMAC token.
3. **Read-Only:** Hanya menampilkan data, tidak ada aksi edit/hapus.
4. **Konsisten dengan Pola:** Mengikuti pola halaman publik yang sudah ada (`self-permission`, `self-presence`).

---

## 1. Ringkasan Fitur

| Aspek | Detail |
|---|---|
| **Tujuan** | Pembina grup melihat data kehadiran anggotanya; wali melihat data kehadiran anaknya |
| **Akses** | Publik, tanpa login, hanya perlu link |
| **Keamanan** | HMAC token yang di-generate dari ULID + secret |
| **Data Ditampilkan (Grup)** | Daftar anggota + rekap absensi per rapat |
| **Data Ditampilkan (Anggota)** | Biodata + foto + riwayat kehadiran |
| **Generate Link** | Operator dari admin panel (Filament) |

---

## 2. Aturan Bisnis & Desain Link

### 2.1 Format URL

| Halaman | Format | Contoh | Estimasi Panjang |
|---|---|---|---|
| Preview Grup | `/p/g/{code}?k={token}` | `/p/g/GRUP01?k=a1b2c3d4e5f6g7h8` | ~50-55 karakter |
| Preview Anggota | `/p/m/{uid}?k={token}` | `/p/m/01HXYZ...26char?k=a1b2c3d4e5f6g7h8` | ~65-70 karakter |

**Keterangan:**
- `{code}` = `groups.code` — kode grup yang sudah ada (indexed, pendek)
- `{uid}` = `members.uid` — ULID anggota (26 karakter)
- `{token}` = HMAC hash — disimpan di tabel sebagai `preview_token`

### 2.2 Generasi Token (HMAC)

```
token = hash_hmac('sha256', uid, config('app.key'))
```

- Token di-generate dari ULID record + `APP_KEY`
- Disimpan di kolom `preview_token` pada record yang bersangkutan
- Saat akses halaman preview, token di-match langsung dari database (lookup by `preview_token`)
- Jika token tidak cocok → **404 Not Found**

### 2.3 Regenerasi Token

- Operator bisa regenerate token kapan saja dari admin panel
- Token lama otomatis **tidak berfungsi** setelah regenerate
- Berguna jika link lama bocor atau sudah tidak ingin digunakan

### 2.4 Skenario Akses

```
1. Operator buka admin → pilih grup → klik "Bagikan Kehadiran ke Pendamping"
2. Sistem generate token jika belum ada → copy link ke clipboard
3. Operator kirim link ke pembina via WhatsApp/SMS/etc
4. Pembina buka link → halaman preview tampil tanpa login
5. (Opsional) Operator bisa regenerate → link lama mati
```

---

## 3. Struktur Database

### 3.1 Migration: Tambah Kolom `preview_token`

**Tabel `groups`:**
```
+ Column: preview_token (string, nullable, unique, indexed)
```

**Tabel `members`:**
```
+ Column: preview_token (string, nullable, unique, indexed)
```

**Catatan:**
- Kolom `nullable` karena token hanya di-generate saat operator mengklik "Bagikan"
- `unique` memastikan tidak ada duplikat token antar record
- `indexed` untuk performa lookup saat verifikasi akses

---

## 4. Arsitektur Teknis

### 4.1 Model

**Group (`app/Models/Group.php`):**

| Tambahan | Tipe | Keterangan |
|---|---|---|
| `$fillable[]` | array | Tambah `'preview_token'` |
| `generatePreviewToken()` | method | Generate & simpan HMAC token |
| `getPreviewUrl()` | method | Return URL lengkap `/p/g/{code}?k={token}` |

**Member (`app/Models/Member.php`):**

| Tambahan | Tipe | Keterangan |
|---|---|---|
| `$fillable[]` | array | Tambah `'preview_token'` |
| `generatePreviewToken()` | method | Generate & simpan HMAC token |
| `getPreviewUrl()` | method | Return URL lengkap `/p/m/{uid}?k={token}` |

### 4.2 Routes (Public, No Auth)

```php
// routes/web.php — Public preview routes
Route::get('/p/g/{group:code}', [PreviewController::class, 'group'])
    ->name('preview.group')
    ->middleware('throttle:30,1');

Route::get('/p/m/{member:uid}', [PreviewController::class, 'member'])
    ->name('preview.member')
    ->middleware('throttle:30,1');
```

**Keterangan:**
- Route model binding: `{group:code}` lookup by `code`, `{member:uid}` lookup by `uid`
- Throttle: maksimal 30 request per menit per IP (mencegah brute-force)
- Tidak ada middleware `auth` — halaman bisa diakses siapa saja yang punya link

### 4.3 Controller: `PreviewController`

**File:** `app/Http/Controllers/PreviewController.php`

| Method | Parameter | Return |
|---|---|---|
| `group(Group $group, Request $request)` | `$group` via route model binding | Blade view `preview.group` |
| `member(Member $member, Request $request)` | `$member` via route model binding | Blade view `preview.member` |

**Logika Verifikasi (sama untuk kedua method):**
1. Cek apakah `$record->preview_token` ada (null → 404)
2. Bandingkan `$request->input('k')` dengan `$record->preview_token`
3. Jika tidak cocok → abort 404
4. Jika cocok → load data & render view

**Data yang Di-load:**

| Halaman | Eager Loading |
|---|---|
| **Grup** | `members` (with `attendances.meeting`), `level`, `parent` |
| **Anggota** | `group.level`, `attendances.meeting`, `ageGroup`, `positions` |

### 4.4 Blade Views

**Prinsip desain mengikuti pola yang sudah ada:**
- Self-contained (tidak `@extends` layout apapun)
- Tailwind CSS via CDN
- Dark mode class-based (`@include('components.theme-script')`)
- Font Manrope + Material Icons

#### `resources/views/preview/group.blade.php`

| Sesi | Konten |
|---|---|
| **Header** | Nama grup, hierarki level (Daerah > Desa > Kelompok), badge status |
| **Ringkasan** | Jumlah anggota aktif, jumlah rapat, rata-rata kehadiran |
| **Tabel Anggota** | Kolom: Kode, Nama, Gender, Status Aktif |
| **Rekap Absensi** | Tabel: Nama Anggota × Kolom per Rapat (Hadir/Sakit/Izin/Tidak Hadir) |

#### `resources/views/preview/member.blade.php`

| Sesi | Konten |
|---|---|
| **Header** | Foto profil, nama lengkap, nama panggilan |
| **Biodata** | Kode anggota, tanggal lahir, usia, kelompok, jenis kelamin, status |
| **Kartu Anggota** | Tampilan KTA jika tersedia (`membership_card_photo_path`) |
| **Riwayat Kehadiran** | Tabel: Tanggal Rapat, Status Kehadiran (Hadir/Sakit/Izin/Tidak Hadir) |

---

## 5. Integrasi Filament (Admin Panel)

### 5.1 ViewGroup — Header Action

**File:** `app/Filament/Resources/Groups/Pages/ViewGroup.php`

Tambah header action:

```php
Action::make('share_presence')
    ->label('Bagikan Kehadiran ke Pendamping')
    ->icon('heroicon-o-share')
    ->color('gray')
    ->action(function (Group $record) {
        if (!$record->preview_token) {
            $record->generatePreviewToken();
        }
        return response()->json([
            'url' => $record->getPreviewUrl(),
        ]);
    })
    ->clipboard(fn (Group $record) => $record->getPreviewUrl() ?? '')
    ->after(function () {
        Notification::make()->title('Link disalin ke clipboard!')->success()->send();
    })
```

**Fitur:**
- Jika token belum ada → generate dulu
- `->clipboard()` → otomatis copy URL ke clipboard
- Toast notification "Link disalin ke clipboard!" setelah action selesai
- Tombol "Regenerate" di dalam dropdown action (opsional)

### 5.2 ViewMember — Header Action

**File:** `app/Filament/Resources/Members/Pages/ViewMember.php`

Tambah header action serupa:

```php
Action::make('share_to_guardian')
    ->label('Bagikan ke Wali')
    ->icon('heroicon-o-share')
    ->color('gray')
    ->action(function (Member $record) {
        if (!$record->preview_token) {
            $record->generatePreviewToken();
        }
    })
    ->clipboard(fn (Member $record) => $record->getPreviewUrl() ?? '')
    ->after(function () {
        Notification::make()->title('Link disalin ke clipboard!')->success()->send();
    })
```

### 5.3 Regenerate Token

Tombol "Regenerate" bisa ditambahkan sebagai sub-action atau action terpisah:

```php
Action::make('regenerate_preview_token')
    ->label('Regenerate Link')
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->requiresConfirmation()
    ->modalHeading('Regenerate Preview Link')
    ->modalDescription('Link lama akan berhenti berfungsi. Apakah Anda yakin?')
    ->action(function (Group $record) {
        $record->generatePreviewToken(); // overwrite token lama
        Notification::make()->title('Link baru telah dibuat!')->success()->send();
    })
```

---

## 6. Keamanan

| Aspek | Implementasi |
|---|---|
| **Autentikasi** | HMAC token (bukan secret di URL) |
| **Token Storage** | Disimpan di database, bukan di-deduct dari URL |
| **Rate Limiting** | Throttle 30 request/menit per IP |
| **Token Rotation** | Bisa regenerate kapan saja dari admin |
| **Data Exposure** | Hanya data read-only (bisa diakses tanpa login) |
| **404 on Invalid** | Token tidak cocok → 404 (bukan 403, untuk mencegah info leakage) |
| **No Sensitive Data** | Tidak menampilkan password, email, data sensitif lainnya |

---

## 7. Checklist Implementasi

### Fase 1: Database & Model
- [ ] Migration tambah `preview_token` ke tabel `groups`
- [ ] Migration tambah `preview_token` ke tabel `members`
- [ ] Update `Group` model: `$fillable`, `generatePreviewToken()`, `getPreviewUrl()`
- [ ] Update `Member` model: `$fillable`, `generatePreviewToken()`, `getPreviewUrl()`
- [ ] Jalankan `php artisan migrate`

### Fase 2: Backend (Controller & Routes)
- [ ] Buat `PreviewController` dengan method `group()` dan `member()`
- [ ] Definisikan route publik `/p/g/{code}` dan `/p/m/{uid}` di `routes/web.php`
- [ ] Implementasi logika verifikasi token (404 jika tidak cocok)
- [ ] Implementasi eager loading data untuk kedua view

### Fase 3: Frontend (Blade Views)
- [ ] Buat `resources/views/preview/group.blade.php`
  - [ ] Header: nama grup + hierarki
  - [ ] Tabel anggota
  - [ ] Rekap absensi per rapat
  - [ ] Dark mode support
- [ ] Buat `resources/views/preview/member.blade.php`
  - [ ] Header: foto + nama
  - [ ] Biodata lengkap
  - [ ] Kartu anggota (jika ada)
  - [ ] Riwayat kehadiran
  - [ ] Dark mode support

### Fase 4: Integrasi Filament
- [ ] Tambah header action "Bagikan Kehadiran ke Pendamping" di `ViewGroup`
- [ ] Tambah header action "Bagikan ke Wali" di `ViewMember`
- [ ] Implementasi `clipboard()` + toast notification
- [ ] Tambah opsi "Regenerate Link" dengan konfirmasi

### Fase 5: Testing & Validasi
- [ ] Test akses preview grup dengan token valid → tampil
- [ ] Test akses preview grup dengan token invalid → 404
- [ ] Test akses preview grup tanpa token → 404
- [ ] Test akses preview anggota dengan token valid → tampil
- [ ] Test akses preview anggota dengan token invalid → 404
- [ ] Test regenerate token → link lama mati, link baru jalan
- [ ] Test rate limiting → blokir brute-force
- [ ] Test dark mode di halaman preview
- [ ] Test responsive layout (mobile)

---

## 8. Estimasi Panjang Link

| Koneksi | URL |
|---|---|
| **Grup** | `https://domain.com/p/g/GRUP01?k=a1b2c3d4e5f6g7h8` |
| **Anggota** | `https://domain.com/p/m/01HXYZ2S3V4W5X6Y7Z8A9B0C1D?k=a1b2c3d4e5f6g7h8` |
| **Total (Grup)** | ~50-55 karakter |
| **Total (Anggota)** | ~65-70 karakter |

**Catatan:** Link pendek cukup untuk dikirim via WhatsApp/SMS tanpa terpotong.
