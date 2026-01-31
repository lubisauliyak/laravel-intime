# 🧪 Manual Test Phase 1: Core Architecture [REFINED]

Dokumen ini berisi panduan pengujian manual untuk Phase 1, termasuk validasi perbaikan minor pada UI/UX.

## 1. Pengujian Akses & Autentikasi
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Akses URL `/admin/login` | Halaman login muncul dalam Bahasa Indonesia. | [x] |
| Login dengan Akun Admin | Masuk ke Dashboard dengan menu berbahasa Indonesia. | [x] |
| Cek Sidebar | Terbagi menjadi grup **Keanggotaan** dan **Data Master**. | [x] |

## 2. Pengujian Data Master (Level & Age Group)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Input Level baru dengan huruf kecil | Nama dan Kode otomatis berubah menjadi **HURUF KAPITAL** (Auto-Uppercase). | [x] |
| Cek kolom **Angka Hirarki** | Menolak nilai duplikat (Unique Constraint). | [x] |
| Buka **Kategori Usia** | Usia Maksimum menampilkan simbol `∞` jika dikosongkan. | [x] |

## 3. Pengujian Struktur Grup (Hierarchical Logic)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Tambah Grup Baru | Dropdown **Induk Grup** menampilkan format `(KODE) NAMA`. | [x] |
| Lihat Tabel Grup | Data disortir otomatis: Tingkat tertinggi di atas, lalu Nama secara alfabet. | [x] |
| Cek Filter Tabel Grup | Terdapat filter **Tingkat** dan **Status Aktif**. | [x] |

## 4. Pengujian Data Anggota (Dynamic Columns & Logic)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Lihat Tabel Anggota | Muncul kolom dinamis sesuai nama Tingkat (misal: "Wilayah", "Cabang"). | [x] |
| Cek Label Kolom Dinamis | Label diformat ke **Title Case** (contoh: "TINGKAT PUSAT" -> "Tingkat Pusat"). | [x] |
| Klik **Tambah Anggota** | Status Aktif menggunakan **Toggle** (Hijau untuk Aktif). | [x] |
| Isi Tanggal Lahir | **Usia** dan **Kategori** ter-update secara *real-time* saat tanggal dipilih. | [x] |

## 5. Sinkronisasi & Keamanan Minor
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Edit Role User di tabel | Role Spatie di database ikut tersinkronisasi otomatis. | [x] |
| Hapus data (Soft Delete) | Data tidak hilang permanen, muncul di filter **Tempat Sampah**. | [x] |
| Aksi Massal | Tombol Pulihkan/Permanen hanya muncul di menu Aksi Massal (Toolbar), bukan di baris. | [x] |

---
**Catatan Perbaikan Minor Terakhir:**
- [x] Perbaikan namespace `BulkActionGroup` untuk mencegah Internal Server Error.
- [x] Optimasi sorting query pada `GroupsTable`.
- [x] Integrasi `TrashedFilter` pada seluruh modul Data Master.
