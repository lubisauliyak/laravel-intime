# 🧪 Manual Test Phase 3: Attendance & Reporting

Dokumen ini berisi panduan pengujian manual untuk fitur-fitur operasional di Phase 3.

## 1. Pengujian Manajemen Pertemuan (Meetings)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Buat Pertemuan (Meeting) baru | Pertemuan tersimpan dan terkait dengan Grup tertentu. | [x] |
| Cek daftar pertemuan | Hanya menampilkan pertemuan di grup yang sesuai dengan akses Admin. | [x] |

## 2. Pengujian Scanner & Presensi
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Buka Scanner Station | Kamera aktif dan siap melakukan scanning. | [x] |
| Scan QR Code Anggota valid | Muncul notifikasi "Berhasil" dan nama anggota tampil. | [x] |
| Scan QR Code yang sama (Duplicate) | Muncul peringatan "Sudah Absen" atau "Data Ganda". | [x] |
| Scan QR Code tidak valid/luar sistem | Muncul pesan error "Data Tidak Dikenali". | [x] |
| Scan QR Code Anggota dari grup berbeda | Muncul pesan warning "bukan anggota [grup]". | [x] |
| Cari manual anggota dari grup berbeda | Anggota dari grup lain tidak muncul di hasil pencarian. | [x] |
| Update status: hadir → izin/sakit | Muncul pesan "diubah dari HADIR → IZIN/SAKIT". | [x] |
| Update status dengan status sama | Muncul warning "sudah tercatat [STATUS]". | [x] |
| Scan di hari selain meeting_date | Muncul error "Pertemuan ini dijadwalkan pada [tanggal]". | [x] |

## 3. Pengujian Dashboard & Statistik
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Lakukan beberapa scan presensi | Angka statistik di Dashboard bertambah secara real-time. | [x] |
| Lihat Chart Kehadiran | Grafik menampilkan tren sesuai data yang masuk. | [x] |

## 4. Pengujian Pelaporan & Detail (Selesai)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Lihat Detail Presensi per Grup | Menampilkan daftar seluruh nama anggota di grup tersebut (dan sub-grupnya). | [x] |
| Cek Statistik Drill-down | Menampilkan tabel statistik per sub-grup secara hierarkis. | [x] |
| Set Status Manual (Susulan) | Admin bisa mengubah status anggota menjadi Izin/Sakit setelah pertemuan selesai. | [x] |
| Unggah Lampiran Bukti Izin | Foto bukti terunggah dan dapat dilihat oleh Admin via tombol "Lihat Lampiran". | [x] |
| Batalkan Presensi (Hapus) | Data kehadiran terhapus dan status kembali ke "Belum Hadir / Tidak Hadir". | [x] |
| Cek Status Otomatis (Waktu) | Status otomatis menjadi "TIDAK HADIR" jika jam selesai pertemuan sudah lewat. | [x] |

---
*Catatan:*
- Fitur Ekspor Excel Terpadu dan Cetak Kartu dipindahkan ke Phase 5 sesuai prioritas terbaru.
