# 📘 Implementation Plan: inTime Web App

## 1. Gambaran Umum
Web app **inTime** adalah sistem manajemen pendataan anggota dan absensi pertemuan dengan struktur organisasi bertingkat. Sistem ini dirancang untuk menangani ribuan anggota dengan efisiensi tinggi menggunakan scan QR Code.

### Spesifikasi Teknologi
*   **Framework:** Laravel 12
*   **Admin Panel:** Filament PHP 5
*   **Database:** MySQL
*   **Library Utama:** 
    *   `simplesoftwareio/simple-qrcode` (QR Generation)
    *   `bezhansalleh/filament-shield` (Role & Access Control)
    *   Sistem Hierarchy (Single Table Groups with `parent_id`)

---

## 2. Arsitektur Data & Scalability

### 2.1 Struktur Kelompok (Scalable Hierarchy)
Menggunakan satu tabel `groups` untuk fleksibilitas level (Unlimited Depth).
*   **Tingkat 3 (Top):** Contoh: Wilayah / Region.
*   **Tingkat 2 (Mid):** Contoh: Cabang / Area.
*   **Tingkat 1 (Leaf):** Contoh: Kelompok Lokal (Tempat anggota bernaung).
*   **Scalability:** Struktur ini memungkinkan penambahan level (Tingkat 4, dst) tanpa mengubah skema database.

### 2.2 Pemisahan User & Member (Decoupled Architecture)
*   **Users:** Akun sistem untuk LOGIN (Admin/Operator). Tidak wajib berasal dari data anggota.
*   **Members:** Data subjek absensi. Tidak memiliki akses login ke sistem.
*   **Keuntungan:** Keamanan lebih baik, integritas data terjaga, dan manajemen akun yang lebih bersih.

---

## 3. Struktur Database (Schema)

### 3.1 Tabel `groups` (Hierarki Organisasi)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| parent_id | foreign | Nullable, relasi ke `groups.id` |
| name | string | Nama Kelompok |
| level | integer | Indikator level (1, 2, 3...) |
| status | boolean | Aktif / Non-aktif |
| timestamps | | |

### 3.2 Tabel `users` (Akses Sistem)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| name | string | Nama Lengkap User |
| email | string | Unique, untuk login |
| password | string | Bcrypt |
| role | enum | `super_admin`, `admin`, `operator` |
| group_id | foreign | Scoped access (Admin/Operator ini bertugas di kelompok mana) |
| status | boolean | Aktif / Suspend |

### 3.3 Tabel `members` (Data Anggota)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| member_code | string | Unique (Misal: IT-2024-001) |
| full_name | string | Nama Lengkap |
| nick_name | string | Nama Panggilan |
| group_id | foreign | FK ke `groups` |
| birth_date | date | Untuk kalkulasi usia |
| age | integer | Usia otomatis (kalkulasi) |
| age_group_id | foreign | FK ke `age_groups` |
| gender | enum | `male`, `female` |
| status | boolean | Aktif (true) / Non-aktif (false) |
| membership_type | enum | `anggota`, `pengurus` |
| qr_code_path | string | Path file image QR Code |

### 3.4 Tabel `meetings` (Data Pertemuan)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| name | string | Judul Pertemuan |
| meeting_date | date | Tanggal Pelaksanaan |
| start_time | time | Jam Mulai (H:i) |
| end_time | time | Jam Selesai (H:i) |
| group_id | foreign | FK ke `groups` |
| target_gender | enum | `all`, `male`, `female` |
| target_age_groups | json | Array kategori usia (misal: `["Anak", "Remaja"]`) |
| created_by | foreign | FK ke `users.id` |

### 3.5 Tabel `attendances` (Data Kehadiran)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| meeting_id | foreign | FK ke `meetings` |
| member_id | foreign | FK ke `members` |
| checkin_time | datetime | Waktu scan/input |
| method | enum | `manual`, `qr_code` |
| status | string | `hadir`, `izin`, `sakit` |
| notes | text | Catatan/Keterangan |
| evidence_path | string | Bukti foto lampiran |
| ~~attendance_type~~ | ~~enum~~ | ~~`wajib`, `opsional`, `istimewa`~~ ❌ **DIHAPUS** |

---

## 4. Mekanisme Role and Permission (Hierarchy Scoping)

Sistem menggunakan **Query Scoping** dan **Policy** berdasarkan `role` dan `group_id`:

1.  **Super Admin:** `group_id` NULL. Memiliki akses penuh ke seluruh sistem (User, Group, Member, Meeting, Attendance).
2.  **Admin [Tingkat X]:**
    *   **Member Management:** Dapat melihat dan menambah anggota di kelompoknya sendiri dan kelompok-kelompok di bawahnya (subgroups).
    *   **Meeting Management:** **HANYA** dapat membuat pertemuan untuk kelompoknya sendiri (`meeting.group_id` harus sama dengan `user.group_id`). Tidak boleh membuat pertemuan untuk kelompok di bawahnya atau di atasnya.
    *   **Visibility:** Dapat melihat dashboard statistik untuk kelompoknya dan turunannya.
3.  **Operator:**
    *   **Wajib punya `group_id`** (validasi di form).
    *   **Data Scoping:** Hanya bisa melihat/scan pertemuan di grup sendiri + turunannya.
    *   **Navigation:** Menu **Kelompok & Anggota DIHILANGKAN** dari sidebar.
    *   **Action:** Fokus pada Live Scanner dan Dashboard Statistik.

---

## 5. Alur Fitur Utama

### 5.1 QR Code Workflow
1.  Admin input data Anggota baru.
2.  Sistem otomatis generate `member_code` unik.
3.  Sistem generate file QR Code berisi `member_code`.
4.  Admin dapat mencetak kartu anggota (PDF) berisi QR tersebut.

### 5.2 Absensi Terpadu (Single View)
1.  Operator membuka HP/Tablet → Login ke Filament.
2.  Membuka halaman **Presensi**.
3.  Tampilan atas: **Live QR Scanner**. Tampilan bawah: **List Anggota (Manual Search)**.
4.  Sistem validasi (Scan atau Klik Manual): 
    *   Apakah member aktif?
    *   Apakah member cocok dengan target gender/usia pertemuan?
    *   Apakah sudah absen sebelumnya?
5.  Berhasil: Notifikasi sukses & data singkat anggota muncul di layar.

### 5.3 Histori Perpindahan
(Batal diimplementasikan sesuai keputusan QA - 11 Feb 2026)

---

## 6. Development To-Do List (Phased Approach)

### Phase 1: Core Architecture (Week 1)
*   [ ] Setup Laravel 12 & Filament 5.
*   [ ] Migration: `groups` (hierarchy), `users`, `members`.
*   [ ] Models & Breadcrumbs (Recursive relationships for Groups).
*   [ ] Filament Resources: GroupResource (Tree View), MemberResource.

### Phase 2: QR & Membership (Week 2)
*   [ ] Integration `simple-qrcode`.
*   [ ] Logic: Automatic Age Categorization.
*   [ ] Role & Policy implementation (Filament Shield / Spatie).

### Phase 3: Attendance Engine (Week 3)
*   [ ] Migration: `meetings`, `attendances`.
*   [ ] Meeting Resource with Scope filter (Gender & Age).
*   [ ] **Custom Unified Page:** QR Scanner + Manual Search (dalam satu tampilan).
*   [ ] Real-time validation & feedback notifications.
*   [x] **Catatan:** Logika status presensi otomatis ('BELUM HADIR' ke 'TIDAK HADIR') akan diimplementasikan di fase ini atau fase berikutnya.

### Phase 4: Reporting & Mobile UX (Selesai)
*   [x] Statistik per grup turunan (Drill-down).
*   [x] Detail Presensi per grup (Infolist & Table).
*   [x] Logika otomatis 'BELUM HADIR' vs 'TIDAK HADIR' berdasarkan waktu.
*   [x] Sistem Lampiran Bukti Izin (Foto/Keterangan).
*   [x] Optimasi Mobile UX untuk Scanner & Tables.

### Phase 5: QR Management, Deep Reporting & Cards (Current)
*   [ ] **QR Code Management:** Download QR Code PNG (Single/Bulk Zip).
*   [ ] **Live Scanner Enhancements:** Deteksi Terlambat & Filter Target Search.
*   [ ] **Custom Excel Export:** Multi-sheet report (Summary & Member Details).
*   [ ] **Cetak Kartu Anggota (Member Cards):** Bulk print selected members to PDF ready-to-print.
*   [ ] Project documentation & handover preparation.

### Phase 6: Monitoring & Optimization ✅ COMPLETED
*   [x] **Dashboard Optimization:** Lazy loading widgets dan caching.
*   [x] **Hierarchical Dashboard:** Scope data untuk user di level induk.
*   [x] **Role Migration:** Transisi role ke basis string.
*   [x] **Bulk Import:** Import massal via Excel dengan auto-mapping.
*   [x] **Auto-Verified Users:** Pengguna baru otomatis terverifikasi.

### Phase 7: Analytics & System Refinement ✅ COMPLETED
*   [x] **Scanner Vertical Lineage:** Pengurus cabang bisa presensi di induk.
*   [x] **Dynamic Scanner Widget:** Grafik beban scanner dengan sumbu X dinamis.
*   [x] **Attendance Matrix Grid:** Visualisasi pola absensi berbasis tanggal.

### Phase 8: Mobile Responsive Implementation ✅ COMPLETED
*   [x] **CSS-Only Approach:** 405 lines mobile-responsive CSS
*   [x] **Welcome Page:** Full mobile-responsive refactor
*   [x] **Dashboard Widgets:** Responsive (3-col desktop, 2-col tablet, 1-col mobile)
*   [x] **Tables:** Horizontal scroll dengan sticky column
*   [x] **Forms:** Single column, touch-friendly (44px)
*   [x] **Navigation:** Sidebar collapse dengan overlay
*   [x] **Modals:** Full-screen pada mobile
*   [x] **Scanner Page:** Mobile-optimized
*   [x] **Meeting Components:** All responsive (tables, forms, widgets)
*   [x] **Desktop Unchanged:** Layout desktop tetap original

---

## 7. Non-Functional Requirements
*   **Security:** Password hashing, CSRF protection, Scoped Database Queries.
*   **Performance:** Indexing pada `member_code` dan `group_id`.
*   **UX:** Clean UI, **Mobile-friendly** (responsive CSS), dark mode support.
*   **Accessibility:** Touch targets min 44px, font-size 16px untuk iOS.

---

## 8. Mobile Responsive Summary

### Breakpoints
```
Mobile:    < 767px   (1 column, touch-friendly)
Tablet:    768-1024px (2 columns)
Desktop:   > 1024px  (3 columns, unchanged)
```

### Files Modified
- `resources/css/app.css` - 405 lines responsive CSS
- `resources/views/welcome.blade.php` - Full refactor
- `docs/MOBILE-RESPONSIVE-COMPLETE.md` - Complete documentation

### Features Responsive
1. Dashboard Widgets (8 widgets)
2. Data Tables (Members, Groups, Users, Meetings)
3. Forms (All resources)
4. Navigation Sidebar
5. Modals & Dialogs
6. Scanner Page
7. Landing Page

### Testing Status
- ✅ Desktop (> 1024px): Layout unchanged
- ✅ Tablet (768-1024px): 2-column grid
- ✅ Mobile (< 768px): 1-column, touch-friendly
- 🔄 Real Device Testing: Recommended

---
✨ *Dokumen ini telah direfaktorisasi untuk efisiensi maksimal dan scalability organisasi.*
✨ *Mobile responsive implementation completed 20 Februari 2026.*
