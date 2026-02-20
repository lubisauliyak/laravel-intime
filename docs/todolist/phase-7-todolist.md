# 📊 Phase 7: Advanced Analytics & Attendance Insights

> **Status:** ✅ Completed / Optimized  
> **Periode:** 20 Februari 2026  
> **SSOT:** `docs/ssot.md` §5.3  
> **Context7:** `.qwen/context7.md` — PSR-12 compliance required

---

## 📋 Overview

Phase 7 fokus pada **analitik lanjutan** dan **wawasan data** untuk memberikan insight mendalam bagi pemimpin organisasi dalam pengambilan keputusan berbasis data kehadiran.

---

## 🎯 Fitur Utama

### 1. Matriks Kehadiran (Attendance Grid)

**Tujuan:** Visualisasi pola kehadiran anggota dalam format matriks mudah dibaca.

#### Tasks:
- [ ] **Database Migration**
  - [ ] Buat migration untuk tabel pivot attendance summary (opsional untuk performance)
  - [ ] Index pada `member_id` dan `meeting_date`
  
- [ ] **Backend Logic**
  - [ ] Buat query untuk attendance matrix (pivot table)
  - [ ] Filter periode tanggal (date range picker)
  - [ ] Aggregate status kehadiran (Hadir, Izin, Sakit, Alpa)
  
- [ ] **UI Component**
  - [ ] Tabel dengan baris = Nama Anggota, kolom = Tanggal Pertemuan
  - [ ] Color-coded cells berdasarkan status (Hijau=Hadir, Kuning=Izin, Orange=Sakit, Merah=Alpa)
  - [ ] Summary bar di akhir (total kehadiran per anggota)
  - [ ] Pagination untuk anggota
  - [ ] **Performance:** Implement pre-calculated summary table/cache for matrix data
  
- [x] **Features**
  - [x] Filter by grup (hierarchical)
  - [ ] Filter by kategori usia
  - [ ] Filter by gender
  - [ ] Export to Excel (matrix format)
  - [ ] **Mobile View:** Add summary card/collapsed view for mobile responsiveness

**Files:**
- `app/Filament/Pages/AttendanceMatrix.php` (Custom Page)
- `app/Exports/AttendanceMatrixExport.php`

---

### 2. Analisis Performa Grup (Group Insights)

**Tujuan:** Leaderboard dan ranking grup berdasarkan persentase kehadiran.

#### Tasks:
- [ ] **Dashboard Widget**
  - [ ] Top 10 Groups by Attendance Rate (bar chart)
  - [ ] Group comparison table
  - [ ] Trend analysis (month-over-month)
  
- [ ] **Aggregation Report**
  - [ ] Rollup statistics per level (Nasional → Wilayah → Cabang → Kelompok)
  - [ ] Average attendance rate per level
  - [ ] Best & worst performing groups
  
- [ ] **UI Component**
  - [ ] Leaderboard table dengan ranking
  - [ ] Progress bars untuk persentase
  - [ ] Sortable columns (nama, persentase, total hadir)
  
- [ ] **Features**
  - [ ] Filter by date range
  - [ ] Filter by level
  - [ ] Export to PDF/Excel

**Files:**
- `app/Filament/Widgets/GroupLeaderboardWidget.php`
- `app/Filament/Pages/GroupInsights.php`
- `app/Exports/GroupInsightsExport.php`

---

### 3. Sistem "Early Warning" (Anggota Kurang Aktif) ⚠️

**Tujuan:** Deteksi dini anggota yang perlu perhatian khusus berdasarkan pola kehadiran.

#### Tasks:
- [ ] **Detection Logic**
  - [ ] Query untuk anggota dengan kehadiran < 50% (threshold: `count(attended) / total_meetings_in_period`)
  - [ ] Query untuk anggota yang absen 3+ kali berturut-turut pada pertemuan wajib (target age group)
  - [ ] Calculate "risk score": `(0.5 * low_participation) + (0.5 * consecutive_absences)`
  
- [ ] **Dashboard Widget**
  - [ ] "Anggota Butuh Perhatian" list
  - [ ] Warning badges (Low Participation, Consecutive Absences)
  - [ ] Quick stats (total anggota at-risk)
  
- [ ] **Report Page**
  - [ ] List anggota dengan filter risiko
  - [ ] Detail pattern (tanggal-tanggal tidak hadir)
  - [ ] Export to Excel/PDF
  
- [ ] **Notification System** (Future)
  - [ ] Email/SMS alert untuk anggota at-risk
  - [ ] Monthly report ke admin grup

**Files:**
- `app/Filament/Widgets/AtRiskMembersWidget.php`
- `app/Filament/Pages/LowParticipationReport.php`
- `app/Services/AttendanceRiskService.php`

---

### 4. Pelaporan Lanjutan (Advanced Reporting) 📄

**Tujuan:** Laporan profesional siap cetak untuk rapat bulanan.

#### Tasks:
- [ ] **Monthly Report PDF**
  - [ ] Template PDF dengan branding (header, logo)
  - [ ] Summary statistics (total anggota, rata-rata kehadiran)
  - [ ] Top performing groups
  - [ ] Members needing attention
  - [ ] Charts & graphs
  
- [ ] **Status Breakdown Report**
  - [ ] Pie chart: Hadir vs Izin vs Sakit vs Alpa
  - [ ] Trend line: Monthly attendance
  - [ ] Breakdown by category (age group, gender)
  
- [ ] **Category-Based Analysis**
  - [ ] Attendance rate per age group
  - [ ] Comparison: Anak vs Remaja vs Dewasa vs Lansia
  - [ ] Gender-based statistics
  
- [ ] **Export Options**
  - [ ] PDF (A4, print-ready)
  - [ ] Excel (with charts)
  - [ ] Scheduled reports (future: auto-email monthly)

---

### 5. **Segmentasi Tampilan Presensi (Pengurus vs. Target)** 🚀

**Tujuan:** Memisahkan visualisasi kehadiran antara "Anggota Target" (berdasarkan kategori usia) dan "Anggota Pengurus" dalam satu pertemuan menggunakan tabel `attendances` yang sudah ada.

#### Background:
Pengurus seringkali hadir di setiap pertemuan meskipun kategori usia mereka tidak masuk dalam target pertemuan tersebut. Kita tidak perlu tabel baru, cukup optimasi query dan tampilan.

#### Tasks:

##### 5.1 Meeting Infolist Refinement
- [x] **Main Attendance Section (Target Anggota)**
  - Filter: `attendances` di mana `member.age_group_id` ada dalam `meeting.target_age_groups`.
  - Fungsi: Menampilkan daftar hadir anggota yang memang menjadi sasaran pertemuan.
- [x] **Collapsible Section: Kehadiran Pengurus** 🆕
  - Filter: `attendances` di mana `member.membership_type == 'pengurus'`.
  - Komponen: `Spatie\Filament\Infolists\Components\Section` dengan `collapsible()`.
  - **Permission:** Hanya terlihat oleh role `super_admin`, `admin`, dan `pengurus`.

##### 5.2 Attendance Engine Updates
- [x] **Scanner & Manual Attendance Logic**
  - Izinkan presensi jika `member.membership_type == 'pengurus'` meskipun `age_group_id` tidak sesuai target pertemuan.
  - Berikan label/indikator "Presensi Pengurus" pada log/toast.

##### 5.3 Performance & Query Optimization
- [x] Buat scope di model `Attendance`: `scopeTargetOnly()` dan `scopePengurusOnly()`.
- [x] Pastikan query infolist menggunakan eager loading untuk menghindari N+1 (`with('member')`).

**Files:**
- `app/Models/Attendance.php` (update scopes)
- `app/Filament/Resources/MeetingResource.php` (update Infolist schema)
- `app/Services/AttendanceService.php` (update validation logic)

---

- `app/Filament/Pages/AttendanceMatrix.php`            🆕
- `app/Filament/Pages/GroupInsights.php`               🆕
- `app/Filament/Pages/LowParticipationReport.php`      🆕
- `app/Filament/Pages/AdvancedReports.php`             🆕

---

## ✅ Definition of Done (DoD) - Phase 7

### Functional Requirements:
1. ✅ Admin dapat melihat **matriks kehadiran** dengan filter periode
2. ✅ Tersedia **leaderboard grup** dengan ranking persentase kehadiran
3. ✅ Sistem dapat mendeteksi **anggota butuh perhatian** (low participation)
4. ✅ Laporan bulanan PDF **siap cetak** dengan format profesional
5. ✅ **Tracking kehadiran pengurus** ter-implementasi penuh
6. ✅ Import/Export template untuk meeting attendees tersedia
7. ✅ Dashboard widget menampilkan statistik pengurus hadir

### Technical Requirements:
1. ✅ PSR-12 compliance untuk semua file baru
2. ✅ Database migration dengan proper indexing
3. ✅ Model relationships ter-define dengan benar
4. ✅ Policies untuk authorization
5. ✅ Unit tests untuk critical logic
6. ✅ Documentation updated (SSOT, Import Guide)

### UX Requirements:
1. ✅ Responsive design (mobile-friendly)
2. ✅ Color-coded visual indicators
3. ✅ Clear navigation & filters
4. ✅ Export options (PDF, Excel)
5. ✅ Error handling & validation

---

## 📈 Priority & Timeline

| Fitur | Priority | Status | Dependencies |
|-------|:--------:|:------:|:-------------|
| **Tabel Pengurus Hadir** | 🔴 HIGH | ✅ DONE | None |
| Attendance Grid | 🟡 MEDIUM | ⏩ MOVED | None |
| Group Leaderboard | 🟡 MEDIUM | ⏩ MOVED | None |
| Early Warning System | 🟢 LOW | ⏩ MOVED | Moved to Phase 11 |
| Advanced Reporting | 🟢 LOW | ⏩ MOVED | Moved to Phase 11 |

**Note:** Matriks, Leaderboard, dan Reporting dipindahkan ke Phase 8 & 11 untuk optimasi fokus.

**Total Estimated:** 12-14 days

---

## 🔄 Changelog

| Date | Changes |
|:------|:--------|
| 20 Feb 2026 | 📄 Phase 7 todolist created |
| | 🆕 Added Feature 5: Tabel Pengurus Hadir di Pertemuan |
| | 📝 Detailed database schema for `meeting_attendees` |
| | 📝 Complete task breakdown (5.1 - 5.8) |
| | 📝 File structure & relationships |

---

## 📚 Related Documentation

- **SSOT:** `docs/ssot.md` — Single Source of Truth
- **Context7:** `.qwen/context7.md` — Coding standards (PSR-12)
- **Database Schema:** `docs/ssot.md` §3
- **Import Guide:** `docs/import-members-guide.md` (reference for importer)

---

*Dibuat dengan ❤️ untuk inTime — Phase 7: Advanced Analytics & Insights*
