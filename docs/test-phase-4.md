# 🧪 Manual Test Phase 4: QR Management & Advanced Actions

Dokumen ini berisi panduan pengujian manual untuk Phase 4.

## 1. Pengujian Manajemen Manual & Lampiran (Selesai Validasi ✅)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Set Status Izin/Sakit + Foto | Formulir menampung keterangan & gambar. Data tersimpan di DB. | [x] |
| Klik "Lihat Lampiran" | File terbuka di tab baru (Tanpa 403 Forbidden). | [x] |
| Hapus Presensi | Baris kembali ke status awal (Belum/Tidak Hadir) & Waktu Kedatangan kosong. | [x] |
| Cek Dropdown Titik Tiga | Aksi tersembunyi dengan rapi di dalam menu ActionGroup. | [x] |

## 2. Pengujian Manajemen QR Code (InProgress ⏳)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Single Download QR Code | Berhasil mengunduh 1 file gambar (.png/.jpg) QR Code milik anggota. | [ ] |
| Bulk Download QR Code | Berhasil mengunduh banyak gambar QR Code dalam satu file ZIP. | [ ] |

## 3. Pengujian Penyempurnaan Scanner (Next 📡)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Tes Deteksi Terlambat | Muncul status 'TERLAMBAT' jika scan dilakukan setelah jam mulai. | [ ] |
| Tes Filter Kriteria | Hasil pencarian scanner benar-benar terfilter sesuai target meeting. | [ ] |

---
*Catatan:*
- Pengujian link gambar (403 fix) telah divalidasi dan dipindahkan ke 'Selesai Validasi'.
- Fokus selanjutnya: Fungsionalitas download QR.
