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
        'export' => 'Ekspor',
        'import' => 'Impor',
    ],
];
