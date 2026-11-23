# 📋 Todolist: Fitur SaaS Multi-Organisasi

> Mengubah inTime dari **single-tenant** menjadi **multi-tenant SaaS** menggunakan **Filament Tenancy** built-in.
> Setiap organisasi memiliki data terisolasi (members, groups, meetings, attendance) dalam **satu database yang sama**.
> User bisa pindah antar organisasi via **tenant switcher** di sidebar Filament.

---

## 🏗️ 1. Model & Database — Entitas Organisasi

### Tabel `organizations` (BARU)
> Entitas tenant utama — setiap organisasi adalah satu "akun SaaS".

- [ ] Buat migration `create_organizations_table`
  ```php
  $table->id();
  $table->string('name');
  $table->string('slug')->unique();
  $table->string('logo_path')->nullable();
  $table->text('description')->nullable();
  $table->boolean('status')->default(true);
  $table->json('settings')->nullable();
  $table->timestamps();
  $table->softDeletes();
  ```
- [ ] Buat model `app/Models/Organization.php`
  - [ ] `$fillable`: `name`, `slug`, `logo_path`, `description`, `status`, `settings`
  - [ ] Relasi: `users()` → belongsToMany via pivot
  - [ ] Relasi: `groups()`, `members()`, `meetings()`, `levels()`, `ageGroups()`, `positionCategories()`
  - [ ] Auto-generate `slug` dari `name` saat creating (via `Str::slug`) di `booted()`

### Tabel Pivot `organization_user` (BARU)
> User bisa bergabung di beberapa organisasi (SaaS pattern).

- [ ] Buat migration `create_organization_user_table`
  ```php
  $table->id();
  $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
  $table->foreignId('user_id')->constrained()->cascadeOnDelete();
  $table->string('role')->nullable(); // Opsional untuk level role per-org
  $table->timestamps();
  
  $table->unique(['organization_id', 'user_id']);
  ```

### Update Model `User`
> Implement contract `HasTenants` dari Filament.

- [ ] Implement `Filament\Models\Contracts\HasTenants` pada `User` model
- [ ] Tambah method `getTenants(Panel $panel): Collection` → return `this->organizations`
- [ ] Tambah method `canAccessTenant(Model $tenant): bool` → return `this->organizations()->whereKey($tenant)->exists()`
- [ ] Tambah relasi `organizations()` → `belongsToMany(Organization::class)`
- [ ] (Opsional) Implement `HasDefaultTenant` dan method `getDefaultTenant(Panel $panel): ?Model`

---

## 🏢 2. Tambah `organization_id` ke Semua Tabel Data

> Setiap record data harus terikat ke satu organisasi agar data terisolasi per tenant.

### Migration: Tambah kolom `organization_id`
- [ ] Buat migration `add_organization_id_to_all_tables`
- [ ] Tambah `organization_id` (FK, nullable dulu untuk migrasi data) ke tabel:
  - [ ] `groups`
  - [ ] `members`
  - [ ] `meetings`
  - [ ] `attendances`
  - [ ] `levels`
  - [ ] `age_groups`
  - [ ] `position_categories`
  - [ ] `member_positions`
- [ ] Tambah index pada `organization_id` di semua tabel di atas

### Update Model — Tambah Relasi `organization()`
- [ ] `Group` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `Member` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `Meeting` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `Attendance` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `Level` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `AgeGroup` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `PositionCategory` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`
- [ ] `MemberPosition` → `belongsTo(Organization::class)` + tambah `organization_id` ke `$fillable`

---

## ⚙️ 3. Konfigurasi Filament Tenancy

### `AdminPanelProvider.php`
> File: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] Tambahkan konfigurasi tenant pada method `panel()`:
  ```php
  ->tenant(Organization::class, slugAttribute: 'slug')
  ```
- [ ] (Opsional) Tambah tenant registration page:
  ```php
  ->tenantRegistration(RegisterOrganization::class)
  ```
- [ ] (Opsional) Tambah tenant profile page:
  ```php
  ->tenantProfile(EditOrganizationProfile::class)
  ```
- [ ] Tambah navigation group baru `'Organisasi'` (opsional)

---

## 🔒 4. Scoping Data per Tenant

### Filament Resource — Tenant Scoping Otomatis
> Filament Tenancy secara otomatis men-scope query jika resource memiliki relasi `organization` yang sesuai.

- [ ] Pastikan relasi di model menggunakan nama yang dikenali Filament (secara default akan mengecek relasi berdasarkan nama model tenant, e.g., `organization()`).

#### Update Semua Resource `getEloquentQuery()`
- [ ] `MemberResource` — Hapus/sesuaikan manual scope `whereIn('group_id', ...)` jika perlu disesuaikan dengan tenancy. Filament secara otomatis akan menambahkan `where('organization_id', getTenant()->id)`.
- [ ] `GroupResource` — Sesuaikan scoping.
- [ ] `MeetingResource` — Sesuaikan scoping.
- [ ] `LevelResource` — Sesuaikan scoping.
- [ ] `AgeGroupResource` — Sesuaikan scoping.
- [ ] `PositionCategoryResource` — Sesuaikan scoping.
- [ ] `UserResource` — Modifikasi `getEloquentQuery()` untuk hanya melihat user yang tergabung di tenant aktif (bisa via relasi `organizations`).

### Trait `BelongsToOrganization` (Rekomendasi)
- [ ] Buat trait `app/Traits/BelongsToOrganization.php`
  ```php
  trait BelongsToOrganization {
      protected static function bootBelongsToOrganization() {
          static::creating(function ($model) {
              if (auth()->check() && \Filament\Facades\Filament::getTenant()) {
                  $model->organization_id = $model->organization_id ?? \Filament\Facades\Filament::getTenant()->id;
              }
          });
      }
  }
  ```
- [ ] Apply trait ke model: `Group`, `Member`, `Meeting`, `Attendance`, `Level`, `AgeGroup`, `PositionCategory`, `MemberPosition`.

---

## 🔀 5. Migrasi Data Existing

> Data yang sudah ada perlu di-assign ke organisasi default.

### Command: Migrasi Data
- [ ] Buat Artisan command `MigrateToMultiTenant` (`php artisan make:command MigrateToMultiTenant`)
- [ ] Implementasikan logic `handle()`:
  - [ ] Buat organisasi default (misal: "Organisasi Utama")
  - [ ] Update semua record existing di tabel `groups`, `members`, `meetings`, dsb → set `organization_id` = ID org default
  - [ ] Attach semua user existing ke organisasi default via pivot `organization_user`

---

## 📝 6. Halaman Tenant (Opsional)

### Halaman Registrasi Organisasi Baru
> File: `app/Filament/Pages/Tenancy/RegisterOrganization.php`

- [ ] Buat class extends `Filament\Pages\Tenancy\RegisterTenant`
- [ ] Define `form(Form $form)` dengan field: `name`, `slug`, `description`, `logo_path`
- [ ] Define `handleRegistration(array $data): Organization` untuk membuat record dan attach ke user pembuat

### Halaman Edit Profil Organisasi
> File: `app/Filament/Pages/Tenancy/EditOrganizationProfile.php`

- [ ] Buat class extends `Filament\Pages\Tenancy\EditTenantProfile`
- [ ] Define form schema untuk update profile tenant

---

## 🛡️ 7. Permission & Policy

- [ ] Pastikan Role/Permission dari Spatie tetap bekerja. Secara default Spatie permissions tidak multi-tenant aware kecuali di-setting khusus. Jika role tetap global, maka user akan punya role yang sama di setiap tenant.
- [ ] Update `viewAny`, `view`, `create`, dll di policy untuk mematuhi tenant (walaupun Filament sudah men-scope data resource).
- [ ] Tambahkan `manage_organization` permission jika tenant profil bisa diedit oleh role tertentu.

---

## 🖥️ 8. Update Komponen Non-Resource

### Dashboard (`app/Filament/Pages/Dashboard.php`)
- [ ] Update `filtersForm()`: `Group::query()` → tambah `where('organization_id', Filament::getTenant()?->id)`
- [ ] Update `filtersForm()`: `Meeting::query()` → tambah `where('organization_id', Filament::getTenant()?->id)`
- [ ] Cek widget stats agar data yang ditarik menggunakan tenant context.

### Live Scanner (`LiveScannerController`)
- [ ] Scope meeting search dan member lookup ke `organization_id` dari meeting terkait.
- [ ] Tambahkan verifikasi `organization_id` saat `store` atau `manualStore`.

### Reports & Export
- [ ] `MeetingReportController` → pastikan logic query menggunakan tenant aktif
- [ ] `ExportMemberReport` command → pertimbangkan bagaimana command berjalan (apakah per-tenant atau bisa cross-tenant jika dijalankan dari console).

---

## 📝 9. Keputusan Desain (Design Decisions)

Berdasarkan kesepakatan:

1. **Role per Organisasi (via Spatie Teams)**: 
   Role akan dibuat spesifik per organisasi (User A bisa jadi Admin di Org 1, dan Operator di Org 2). Kita akan mengaktifkan fitur `teams` bawaan `spatie/laravel-permission`. 
   *Auto-generate*: Saat organisasi baru dibuat, sistem akan otomatis men-generate role standar (Admin, Operator, Pengurus) untuk organisasi tersebut.
2. **Master Data Auto-Generate**:
   Data referensi seperti `levels`, `age_groups`, dan `position_categories` akan bersifat **terpisah per organisasi**.
   *Auto-generate*: Saat organisasi baru dibuat, sistem akan otomatis melakukan *seeding/copy* data template master data default ke organisasi tersebut, sehingga tiap organisasi bisa menyesuaikannya nanti.
3. **Akses Super Admin**:
   Super Admin memiliki "God Mode" dan diizinkan melihat atau masuk ke organisasi mana saja kapan saja, meskipun tidak secara eksplisit diundang ke organisasi tersebut. Ini akan ditangani di pengecekan `canAccessTenant`.
4. **Pembuatan Organisasi Baru**:
   Hanya Super Admin yang bisa membuat organisasi baru melalui Panel Admin. Tidak ada form pendaftaran publik (SaaS public registration) untuk saat ini.

---

## 🧪 10. Testing & Verifikasi

- [ ] `php artisan migrate` dan jalankan command migrasi.
- [ ] Login sebagai user, lihat tenant switcher di header/sidebar.
- [ ] Buat organisasi baru, cek apakah otomatis switch ke organisasi tersebut.
- [ ] Di dalam organisasi baru, tabel `Members`, `Groups`, dll harusnya kosong.
- [ ] Buat Member baru, cek di database apakah `organization_id` terisi.
- [ ] Switch balik ke organisasi awal, pastikan Member dari organisasi kedua tidak muncul.
- [ ] Test form scan KTA (`LiveScanner`), pastikan data yang tersimpan merujuk pada member di organisasi yang benar.
