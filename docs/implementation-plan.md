# 📘 Implementation Plan: inTime Web App

## 1. Gambaran Umum
Web app **inTime** adalah sistem manajemen pendataan anggota dan absensi pertemuan dengan struktur organisasi bertingkat. Sistem ini dirancang untuk menangani ribuan anggota dengan efisiensi tinggi menggunakan scan QR Code.

### Spesifikasi Teknologi
*   **Framework:** Laravel 12
*   **Admin Panel:** Filament PHP 5
*   **Database:** MySQL / PostgreSQL
*   **Library Utama:** 
    *   `simplesoftwareio/simple-qrcode` (QR Generation)
    *   `spatie/laravel-permission` (Role & Access Control)
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
| group_id | foreign | FK ke `groups` (Biasanya level terendah/T1) |
| birth_date | date | Untuk kalkulasi kategori usia |
| age_group | string | Indikator kategori (misal: REMAJA, DEWASA, dst) |
| gender | enum | `male`, `female` |
| status | enum | `active`, `inactive`, `moved` |
| membership_type | enum | `anggota`, `pengurus` |
| qr_code_path | string | Path file image QR Code |

### 3.4 Tabel `meetings` (Data Pertemuan)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| name | string | Judul Pertemuan |
| meeting_date | date | Tanggal Pelaksanaan |
| group_id | foreign | FK ke `groups` (Scope kelompok yang mengadakan) |
| target_gender | enum | `all`, `male`, `female` (Filter target peserta) |
| target_age_groups | json | Array kategori usia (misal: `["REMAJA", "DEWASA"]`) |
| created_by | foreign | FK ke `users.id` |

### 3.5 Tabel `attendances` (Data Kehadiran)
| Field | Type | Description |
| :--- | :--- | :--- |
| id | bigint | Primary Key |
| meeting_id | foreign | FK ke `meetings` |
| member_id | foreign | FK ke `members` |
| checkin_time | datetime | Waktu scan/input |
| method | enum | `manual`, `qr_code` |
| attendance_type | enum | `wajib`, `opsional`, `istimewa` |

---

## 4. Mekanisme Role and Permission (Hierarchy Scoping)

Sistem menggunakan **Query Scoping** dan **Policy** berdasarkan `role` dan `group_id`:

1.  **Super Admin:** `group_id` NULL. Memiliki akses penuh ke seluruh sistem (User, Group, Member, Meeting, Attendance).
2.  **Admin [Tingkat X]:**
    *   **Member Management:** Dapat melihat dan menambah anggota di kelompoknya sendiri dan kelompok-kelompok di bawahnya (subgroups).
    *   **Meeting Management:** **HANYA** dapat membuat pertemuan untuk kelompoknya sendiri (`meeting.group_id` harus sama dengan `user.group_id`). Tidak boleh membuat pertemuan untuk kelompok di bawahnya atau di atasnya.
    *   **Visibility:** Dapat melihat dashboard statistik untuk kelompoknya dan turunannya.
3.  **Operator:**
    *   **Attendance Only:** Tidak bisa mengelola Member atau Group.
    *   **Action:** Hanya bisa login untuk membuka halaman scanner dan mencatat kehadiran pada pertemuan yang sudah dibuat oleh Admin.

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

### 5.3 Migrasi Anggota (Scalable Management)
Tabel `member_migrations` (Optional but Recommended) untuk mencatat histori perpindahan anggota antar kelompok tanpa merusak data absensi lama.

---

## 6. Development To-Do List (Phased Approach)

### Phase 1: Core Architecture (Week 1)
*   [ ] Setup Laravel 12 & Filament 5.
*   [ ] Migration: `groups` (hierarchy), `users`, `members`.
*   [ ] Models & Breadcrumbs (Recursive relationships for Groups).
*   [ ] Filament Resources: GroupResource (Tree View), MemberResource.

### Phase 2: QR & Membership (Week 2)
*   [ ] Integration `simple-qrcode`.
*   [ ] Custom Action: Generate Member Cards (PDF).
*   [ ] Logic: Automatic Age Categorization.
*   [ ] Role & Policy implementation (Filament Shield / Spatie).

### Phase 3: Attendance Engine (Week 3)
*   [ ] Migration: `meetings`, `attendances`.
*   [ ] Meeting Resource with Scope filter (Gender & Age).
*   [ ] **Custom Unified Page:** QR Scanner + Manual Search (dalam satu tampilan).
*   [ ] Real-time validation & feedback notifications.

### Phase 4: Reporting & Dashboard (Week 4)
*   [ ] Dashboard Stats: Attendance rate per Group.
*   [ ] Export Report: Excel per Meeting / Per Month.
*   [ ] UI/UX Polishing & Performance Optimization.

---

## 7. Non-Functional Requirements
*   **Security:** Password hashing, CSRF protection, Scoped Database Queries.
*   **Performance:** Indexing pada `member_code` dan `group_id`.
*   **UX:** Clean UI, Mobile-friendly scan page, dark mode support.

---
✨ *Dokumen ini telah direfaktorisasi untuk efisiensi maksimal dan scalability organisasi.*
