<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nama Peran',
    'column.guard_name' => 'Guard',
    'column.roles' => 'Peran',
    'column.permissions' => 'Hak Akses',
    'column.updated_at' => 'Diperbarui',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nama Peran',
    'field.guard_name' => 'Guard',
    'field.permissions' => 'Hak Akses',
    'field.select_all.name' => 'Pilih Semua',
    'field.select_all.message' => 'Aktifkan semua hak akses yang <span class="text-primary font-medium">Tersedia</span> untuk Peran ini.',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Akses Pengguna',
    'nav.role.label' => 'Peran',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Peran',
    'resource.label.roles' => 'Peran',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entitas',
    'resources' => 'Data Master',
    'widgets' => 'Widget Dasbor',
    'pages' => 'Halaman',
    'custom' => 'Hak Akses Khusus',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Anda tidak memiliki izin akses',

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions & Pages
    |--------------------------------------------------------------------------
    */
    'SetExcusedAttendance' => 'Input Izin dan Sakit',
    'view_ScanAttendance' => 'Gunakan Scanner',
    'View:ScanAttendance' => 'Gunakan Scanner', // Just in case it checks with colon prefix

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Lihat',
        'view_any' => 'Lihat Semua',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'delete_any' => 'Hapus Semua',
        'force_delete' => 'Hapus Permanen',
        'force_delete_any' => 'Hapus Permanen Semua',
        'restore' => 'Pulihkan',
        'replicate' => 'Duplikasi',
        'reorder' => 'Urutkan Ulang',
        'restore_any' => 'Pulihkan Semua',
        'export' => 'Tombol - Ekspor Data',
        'import' => 'Tombol - Impor Data',

        // Custom Prefixes for Members & Meetings
        'view_meeting_history' => 'Sub - Riwayat Pertemuan',
        'view_member_activity' => 'Sub - Aktivitas Anggota',
        'view_member_meeting_history' => 'Tombol - Riwayat Pertemuan Anggota',
        'generate_card' => 'Tombol - Buat KTA',
        'make_permanent' => 'Tombol - Jadikan Permanen',
        'deactivate' => 'Tombol - Nonaktifkan',
        'download_kta' => 'Tombol - Unduh KTA',
        'download_qr' => 'Tombol - Unduh QR',
        'open_editor' => 'Tombol - Buka Editor',
        'view_stats' => 'Sub - Data Statistik',
        'view_attendance_details' => 'Sub - Detail Kehadiran Anggota',
        'open_attendance_details' => 'Tombol - Detail Kehadiran Anggota',
        'edit_presences' => 'Tombol - Ubah Data Kehadiran',
        'edit_permissions' => 'Tombol - Kelola Hak Akses Khusus',
        'manage_self_service' => 'Sub - Pengaturan Mandiri',
        'edit_self_service' => 'Tombol - Ubah Pengaturan Mandiri',
        'open_scanner' => 'Tombol - Buka Scanner',
        'export_excel' => 'Tombol - Ekspor Excel',
        'export_pdf' => 'Tombol - Cetak PDF',
        'refresh_member_age' => 'Tombol - Refresh Usia Anggota',
    ],
];
