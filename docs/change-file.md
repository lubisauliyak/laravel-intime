# Daftar File yang Diubah (Pembaruan Pengecekan Policy Permission)

Berikut ini adalah daftar file yang telah diperbarui untuk mengganti pengecekan _raw string_ permission (seperti `auth()->user()->can('View:ScanAttendance')`) menjadi pengecekan berbasis _policy method_ bawaan Laravel (seperti `auth()->user()->can('openScanner', $record)`).

Pembaruan ini sangat penting karena pengecekan dengan _raw string_ **akan mengabaikan (bypass)** logika kustom (custom logic) yang ada di dalam Policy, misalnya pengecekan hierarki grup untuk pengurus (`$this->canManageMeeting($authUser, $meeting)`). Dengan pembaruan ini, seluruh logika authorization menjadi lebih seragam dan aman, serta menghindari duplikasi manajemen role.

### Pertemuan (Meetings) & Scanner
1. `app/Filament/Resources/Meetings/Pages/ViewMeeting.php`
   - Mengubah tombol 'Buka Scanner' untuk mengecek `openScanner` dari policy MeetingResource alih-alih `View:ScanAttendance`.
2. `app/Http/Controllers/LiveScannerController.php`
   - Mengubah keamanan endpoint scanner untuk menggunakan `openScanner` berdasarkan instance Meeting ($meeting) dari parameter URL.

### Anggota (Members)
3. `app/Filament/Resources/Members/Tables/MembersTable.php`
   - Memperbaiki pengecekan Action pada setiap baris data anggota:
     - Tombol KTA -> `downloadKta`
     - Tombol QR -> `downloadQr`
     - Tombol Anggota Permanen -> `makePermanent`
   - *(Catatan: Bulk Actions pada tabel ini tetap dipertahankan menggunakan raw string karena tidak memiliki referensi ke sebuah record spesifik).*
4. `app/Filament/Resources/Members/Schemas/MemberInfolist.php`
   - Tombol pada detail lampiran/KTA/QR di Infolist diperbarui menggunakan policy `downloadKta` dan `downloadQr`.
5. `app/Filament/Resources/Members/Pages/ViewMember.php`
   - Tombol Generate Card di bagian _header_ diperbarui menggunakan policy `generateCard`.

### Grup (Groups)
6. `app/Filament/Resources/Groups/Pages/GroupMemberActivity.php`
   - Memperbaiki tombol Action "Riwayat Pertemuan Anggota" untuk mengecek policy `viewMemberMeetingHistory` dengan `$this->getRecord()` sebagai parameter konteks grup.
7. `app/Filament/Resources/Groups/Pages/GroupMeetingHistory.php`
   - Memperbaiki tombol Action "Detail Kehadiran" untuk mengecek policy `openAttendanceDetails` dengan `$this->getRecord()` sebagai parameter konteks grup.

### Template KTA (Card Templates)
8. `app/Filament/Resources/CardTemplates/Pages/ViewCardTemplate.php`
   - Tombol "Buka Editor" diperbarui untuk menggunakan policy `openEditor` terhadap instance CardTemplate yang bersangkutan.
