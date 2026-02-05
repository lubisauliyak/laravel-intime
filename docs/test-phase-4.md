# 🧪 Manual Test Phase 4: QR Management & Mobile UX

Dokumen ini berisi panduan pengujian manual untuk Phase 4 yang telah disesuaikan.

## 1. Pengujian Manajemen Manual & Lampiran (PASSED ✅)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Set Status Izin/Sakit + Foto | Formulir menampung keterangan & gambar. Data tersimpan di DB. | [x] |
| Klik "Lihat Lampiran" | File terbuka di tab baru (Tanpa 403 Forbidden). | [x] |
| Hapus Presensi | Baris kembali ke status awal & Waktu Kedatangan kosong. | [x] |
| Cek Dropdown Titik Tiga | Aksi tersembunyi dengan rapi di dalam menu ActionGroup. | [x] |

## 2. Pengujian Mobile UX & Responsivitas (PASSED ✅)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Akses via HP (Portrait) | Layout Infolist otomatis menjadi 1 kolom yang rapi. | [x] |
| Cek Tabel di HP | Kolom sekunder (QR, Gender, dll) otomatis tersembunyi. | [x] |
| Buka Scanner di HP | Navbar ringkas, QR box pas di layar, tombol manual besar (Thumb-friendly). | [x] |
| Klik Tombol Manual | Tombol menunjukkan status loading (...) dan tidak bisa diklik ganda. | [x] |
| Cek Radius UI | Seluruh sudut komponen memiliki radius `2xl` yang profesional. | [x] |

## 3. Catatan Penting
- Fitur **Download QR** dan **Deteksi Terlambat** telah dipindahkan ke **Phase 5** atas permintaan USER.
- Seluruh target Phase 4 terkait pelaporan dasar dan responsivitas mobile telah terpenuhi.

---
*Terakhir divalidasi: 5 Feb 2026.*
