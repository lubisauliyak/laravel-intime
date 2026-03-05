# 📋 Dashboard Upgrade Todo List (Refined 🧠)

Berdasarkan analisis ulang dokumen [dashboard-brain.md](file:///c:/Users/Lubisa/Desktop/Antigravity/inTime/docs/dashboard-brain.md). Fokus utama adalah mengubah dashboard dari **"tampilan data statis"** menjadi **"alat kontrol operasional cerdas"**.

---

## 🏗️ Phase 1: Operational Summary & Context
Mengubah bagian atas dashboard agar admin tahu persis apa yang terjadi **hari ini**.

- [ ] **Upgrade `AttendanceOverview`**
    - [ ] Tambahkan Stat Card **"Belum Hadir"** (Populasi - Hadir).
    - [ ] Tambahkan Stat Card **"Terlambat"** (Ambil dari count presence dgan note 'TERLAMBAT').
    - [ ] Tambahkan **"Sesi Aktif"** (Header atau Stat Card yang menampilkan Nama Meeting yang berlangsung hari ini).
- [ ] **Create `QuickActionsWidget`**
    - [ ] Tombol: `[➕ Sesi Baru]`, `[📥 Export Excel]`, `[📷 Scanner]`, `[🖨️ Print Kartu]`.
    - [ ] Gunakan `Filament\Widgets\Widget` kustom dengan layout grid tombol yang cantik.

---

## 🧠 Phase 2: Intelligence & Insights (The "Brain" Part)
Membuat dashboard bisa "berpikir" dan memberi saran otomatis.

- [ ] **Create `TopInsightWidget`**
    - [ ] **Trend Analysis**: "Kehadiran turun/naik X% dibanding pertemuan sebelumnya".
    - [ ] **Consistency Award**: Highlight Kelompok/Divisi dengan % kehadiran tertinggi.
    - [ ] **Red Flag Alerts**: List member yang tidak hadir ≥ 3x berturut-turut.
- [ ] **Enhance `AttendanceTrend`**
    - [ ] Berikan filter interaktif per Event atau Kelompok Usia (jika memungkinkan via polling atau state).

---

## 🛠️ Phase 3: Real-Time & Device Monitoring (Postponed ⏳)
*Ditunda karena alat portable/hardware belum tersedia.*

- [ ] **Enhance `RecentScansWidget`**
    - [ ] Tambahkan kolom **"Device"** (User Agent atau identifier scanner).
    - [ ] Tambahkan animasi hijau untuk baris data yang baru masuk (< 30 detik).
- [ ] **Create `DeviceStatusWidget`**
    - [ ] Pantau "Last Ping" dari scanner device.
    - [ ] Tampilkan status Online/Offline (berdasarkan timestamp log terakhir).

---

## 🎨 Phase 4: Layout Standardization
Mengatur ulang urutan widget (Sort Priority) sesuai standar Dokumentasi Brain.

1.  `AttendanceOverview` (Sort: 1)
2.  `QuickActionsWidget` (Sort: 2)
3.  `AttendanceTrend` (Sort: 3)
4.  `TopInsightWidget` (Sort: 4)
5.  `PunctualityStatsWidget` & `GenderDistribution` (Sort: 5)
6.  `RecentScansWidget` (Sort: 6)

---

## ✅ Progress Summary
- [x] Audit `dashboard-brain.md` (Deep Re-check)
- [x] Mapping existing widgets vs Gaps
- [x] Implementasi Phase 1 (Operational Summary)
- [x] Implementasi Phase 2 (Intelligence & Insights)
- [ ] Implementasi Phase 3 (Monitoring)
- [x] Implementasi Phase 4 (Polish & Layout)
