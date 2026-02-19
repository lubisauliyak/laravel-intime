# 📊 Phase 7: Advanced Analytics & Attendance Insights

> **Status:** ⏳ Planned / In Progress  
> **Periode:** 20 Februari 2026 ~  
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
  
- [x] **Features**
  - [x] Filter by grup (hierarchical)
  - [ ] Filter by kategori usia
  - [ ] Filter by gender
  - [ ] Export to Excel (matrix format)

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
  - [ ] Query untuk anggota dengan kehadiran < 50% (configurable threshold)
  - [ ] Query untuk anggota yang absen 3+ kali berturut-turut
  - [ ] Calculate "risk score" berdasarkan trend
  
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

**Files:**
- `app/Exports/MonthlyReportPdf.php`
- `app/Filament/Pages/AdvancedReports.php`
- `resources/views/exports/monthly-report.blade.php`

---

### 5. **Tabel Pengurus Hadir di Pertemuan** 🆕

**Tujuan:** Tracking khusus untuk kehadiran pengurus (pemimpin organisasi) di setiap pertemuan.

#### Background:
Pengurus memiliki peran penting dalam kepemimpinan organisasi. Tracking kehadiran pengurus membantu:
- Memantau komitmen kepemimpinan
- Evaluasi performa pengurus
- Data untuk musyawarah/evaluasi periode

#### Tasks:

##### 5.1 Database Schema
- [ ] **Migration: Create `meeting_attendees` Table**
  ```sql
  CREATE TABLE meeting_attendees (
      id BIGINT PRIMARY KEY,
      meeting_id BIGINT UNSIGNED,
      user_id BIGINT UNSIGNED,        -- Pengurus yang hadir
      group_id BIGINT UNSIGNED,       -- Snapshopt grup saat attend
      attendance_type ENUM('hadir', 'izin', 'sakit'),
      checkin_time DATETIME,
      notes TEXT NULL,
      created_at TIMESTAMP,
      updated_at TIMESTAMP,
      
      FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
      
      UNIQUE KEY unique_meeting_user (meeting_id, user_id)
  );
  
  CREATE INDEX idx_meeting_attendees_meeting ON meeting_attendees(meeting_id);
  CREATE INDEX idx_meeting_attendees_user ON meeting_attendees(user_id);
  ```

- [ ] **Migration: Add `is_attendee_tracking` to `meetings` Table**
  ```sql
  ALTER TABLE meetings 
  ADD COLUMN is_attendee_tracking BOOLEAN DEFAULT TRUE,
  ADD COLUMN attendee_notes TEXT NULL;
  ```

##### 5.2 Model & Relationships
- [ ] **Create `MeetingAttendee` Model**
  ```php
  app/Models/MeetingAttendee.php
  ```
  - Relationships: belongsTo(Meeting), belongsTo(User), belongsTo(Group)
  - Scopes: scopeHadir(), scopeIzin(), scopeSakit()
  
- [ ] **Update `Meeting` Model**
  ```php
  // Add relationship
  public function attendees(): HasMany
  {
      return $this->hasMany(MeetingAttendee::class);
  }
  
  // Helper method
  public function getAttendeesCount(): int
  {
      return $this->attendees()->where('attendance_type', 'hadir')->count();
  }
  ```

- [ ] **Update `User` Model**
  ```php
  // Add relationship
  public function meetingAttendances(): HasMany
  {
      return $this->hasMany(MeetingAttendee::class);
  }
  
  // Helper method
  public function getAttendanceRate(): float
  {
      // Calculate percentage
  }
  ```

##### 5.3 Filament Resource
- [ ] **Create `MeetingAttendeeResource`**
  ```
  app/Filament/Resources/MeetingAttendeeResource.php
  ├── Schemas/MeetingAttendeeForm.php
  └── Tables/MeetingAttendeesTable.php
  ```
  
- [ ] **Form Schema**
  - Meeting selector (dropdown)
  - User selector (filter: role = admin/pengurus)
  - Attendance type (Hadir, Izin, Sakit)
  - Check-in time (datetime picker)
  - Notes (textarea)
  
- [ ] **Table Schema**
  - Meeting name
  - User name (with badge role)
  - Group name
  - Attendance type (badge color-coded)
  - Check-in time
  - Actions (Edit, Delete)
  
- [ ] **Filters**
  - Filter by meeting
  - Filter by user
  - Filter by group
  - Filter by attendance type
  - Date range filter

##### 5.4 Integration with Meeting View
- [ ] **Update MeetingInfolist**
  - Add section "Kehadiran Pengurus"
  - Table/Repeater showing attendees
  - Quick stats (total hadir, izin, sakit)
  
- [ ] **Update Meeting Page**
  - Tab/section untuk tracking pengurus
  - Button "Add Attendee"
  - Bulk import untuk attendance pengurus

##### 5.5 Bulk Import/Export
- [ ] **Create `MeetingAttendeeImporter`**
  ```php
  app/Filament/Imports/MeetingAttendeeImporter.php
  ```
  - Import dari Excel: meeting_name, user_email, attendance_type, notes
  - Validation: meeting exists, user exists, unique constraint
  
- [ ] **Create `MeetingAttendeeTemplateExport`**
  ```php
  app/Exports/MeetingAttendeeTemplateExport.php
  ```
  - Template dengan 2 sheets (Template + Panduan)
  - Sample data
  
- [ ] **Bulk Import Page**
  - Similar to Member import flow
  - Modal with template download
  - File upload
  - Progress indicator

##### 5.6 Reports & Analytics
- [ ] **Dashboard Widget**
  - `MeetingAttendeesOverviewWidget`
  - Stats: Total meetings, avg attendance rate, top attendees
  
- [ ] **User Attendance Report**
  - List semua meeting dengan attendance user
  - Attendance rate per user
  - Export to PDF/Excel
  
- [ ] **Meeting Attendance Summary**
  - Per meeting: list pengurus hadir
  - Percentage attendance per meeting
  - Trend analysis

##### 5.7 Permissions & Policies
- [ ] **Create `MeetingAttendeePolicy`**
  ```php
  app/Policies/MeetingAttendeePolicy.php
  ```
  - viewAny, view, create, update, delete
  - Scope by group hierarchy
  
- [ ] **Update Shield Permissions**
  - Register new resource
  - Assign permissions to roles

##### 5.8 UI/UX Enhancements
- [ ] **Quick Attendance Form**
  - Modal form untuk add attendance cepat
  - Search user by name/email
  - Auto-fill check-in time
  
- [ ] **Attendance Badge**
  - Visual indicator di user list
  - Color-coded by attendance rate
  
- [ ] **Notifications**
  - Reminder untuk pengurus yang belum absen
  - Monthly attendance summary

**Files:**
- `app/Models/MeetingAttendee.php`
- `app/Filament/Resources/MeetingAttendeeResource.php`
- `app/Filament/Imports/MeetingAttendeeImporter.php`
- `app/Exports/MeetingAttendeeTemplateExport.php`
- `app/Policies/MeetingAttendeePolicy.php`

---

## 📊 Database Schema Updates

### New Tables

#### `meeting_attendees`
Tracking khusus untuk kehadiran pengurus di pertemuan.

| Field | Type | Constraint | Description |
|-------|------|------------|-------------|
| `id` | bigint | PK, auto-increment | — |
| `meeting_id` | bigint | FK → `meetings.id`, cascade | Pertemuan |
| `user_id` | bigint | FK → `users.id`, cascade | Pengurus yang hadir |
| `group_id` | bigint | FK → `groups.id`, set null | Grup (snapshot saat attend) |
| `attendance_type` | enum | `hadir`, `izin`, `sakit` | Status kehadiran |
| `checkin_time` | datetime | — | Waktu check-in |
| `notes` | text | nullable | Catatan/keterangan |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:**
- `unique_meeting_user` (meeting_id, user_id)
- `idx_meeting_attendees_meeting`
- `idx_meeting_attendees_user`

### Modified Tables

#### `meetings`
| Field | Type | Constraint | Description |
|-------|------|------------|-------------|
| `is_attendee_tracking` | boolean | default: true | Enable/disable tracking |
| `attendee_notes` | text | nullable | Catatan umum attendance |

---

## 📁 File Structure

```
app/
├── Models/
│   ├── MeetingAttendee.php                 🆕
│   └── Meeting.php (update)
│   └── User.php (update)
│
├── Policies/
│   └── MeetingAttendeePolicy.php           🆕
│
├── Services/
│   └── AttendanceRiskService.php           🆕
│
├── Exports/
│   ├── MeetingAttendeeTemplateExport.php   🆕
│   ├── AttendanceMatrixExport.php          🆕
│   ├── GroupInsightsExport.php             🆕
│   └── MonthlyReportPdf.php                🆕
│
├── Filament/
│   ├── Resources/
│   │   └── MeetingAttendeeResource/        🆕
│   │       ├── MeetingAttendeeResource.php
│   │       ├── Schemas/
│   │       │   └── MeetingAttendeeForm.php
│   │       └── Tables/
│   │           └── MeetingAttendeesTable.php
│   │
│   ├── Pages/
│   │   ├── AttendanceMatrix.php            🆕
│   │   ├── GroupInsights.php               🆕
│   │   ├── LowParticipationReport.php      🆕
│   │   └── AdvancedReports.php             🆕
│   │
│   └── Widgets/
│       ├── GroupLeaderboardWidget.php      🆕
│       ├── AtRiskMembersWidget.php         🆕
│       └── MeetingAttendeesOverviewWidget.php 🆕
│
└── Imports/
    └── MeetingAttendeeImporter.php         🆕

resources/
└── views/
    └── exports/
        └── monthly-report.blade.php        🆕
```

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

| Fitur | Priority | Estimated Effort | Dependencies |
|-------|:--------:|:----------------:|:-------------|
| **Tabel Pengurus Hadir** | 🔴 HIGH | 3-4 days | None |
| Attendance Grid | 🟡 MEDIUM | 2-3 days | None |
| Group Leaderboard | 🟡 MEDIUM | 2 days | None |
| Early Warning System | 🟢 LOW | 2 days | Attendance Grid |
| Advanced Reporting | 🟢 LOW | 3 days | All above |

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
