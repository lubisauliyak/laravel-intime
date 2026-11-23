<?php

namespace App\Support;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\MeetingTargetGender;
use App\Enums\MembershipType;
use App\Enums\UserRole;
use App\Models\AgeGroup;
use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ActivityLogDictionary
{
    protected static array $groupNameCache = [];
    protected static array $levelNameCache = [];
    protected static array $ageGroupNameCache = [];
    protected static array $cardTemplateNameCache = [];

    /**
     * Dapatkan label ramah pengguna (Bahasa Indonesia) untuk nama kolom database.
     */
    public static function getFieldLabel(?string $modelClass, string $field): string
    {
        $shortClass = $modelClass ? class_basename($modelClass) : null;

        $labels = [
            'name' => 'Nama',
            'full_name' => 'Nama Lengkap',
            'nick_name' => 'Nama Panggilan',
            'email' => 'Alamat Email',
            'member_code' => 'ID / Nomor Anggota',
            'code' => 'Kode',
            'gender' => 'Jenis Kelamin',
            'birth_date' => 'Tanggal Lahir',
            'age' => 'Usia',
            'age_group_id' => 'Kelompok Usia',
            'is_permanent' => 'Status Keanggotaan',
            'membership_type' => 'Peran Organisasi',
            'group_id' => 'Kelompok / Wilayah',
            'parent_id' => 'Kelompok Induk',
            'level_id' => 'Tingkat Wilayah',
            'card_template_id' => 'Template KTA',
            'meeting_date' => 'Tanggal Pertemuan',
            'start_time' => 'Jam Mulai',
            'checkin_open_time' => 'Jam Buka Presensi',
            'end_time' => 'Jam Selesai',
            'target_gender' => 'Target Jenis Kelamin',
            'target_age_groups' => 'Target Kelompok Usia',
            'is_permission_enabled' => 'Fitur Perizinan',
            'is_presence_enabled' => 'Fitur Presensi Mandiri',
            'is_permission_template_active' => 'Format Template Izin',
            'permission_opening_text' => 'Teks Pembuka Izin',
            'permission_statement_text' => 'Teks Pernyataan Izin',
            'permission_closing_text' => 'Teks Penutup Izin',
            'method' => 'Metode Presensi',
            'checkin_time' => 'Waktu Presensi',
            'notes' => 'Keterangan',
            'role' => 'Peran Akun',
            'description' => 'Deskripsi',
        ];

        if ($field === 'status') {
            return match ($shortClass) {
                'Member' => 'Status Keaktifan Anggota',
                'Attendance' => 'Status Presensi',
                'Group' => 'Status Kelompok',
                'User' => 'Status Akun Pengguna',
                default => 'Status',
            };
        }

        return $labels[$field] ?? Str::headline($field);
    }

    /**
     * Format nilai database menjadi teks yang mudah dipahami manusia.
     */
    public static function formatValue(?string $modelClass, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $shortClass = $modelClass ? class_basename($modelClass) : null;

        // Boolean formatting
        if (is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1') {
            $boolVal = (bool) $value;

            if ($field === 'is_permanent') {
                return $boolVal ? 'Anggota Tetap' : 'Sementara / Menunggu Verifikasi';
            }

            if ($field === 'status') {
                if ($shortClass === 'Member') {
                    return $boolVal ? 'Aktif' : 'Kurang Aktif';
                }
                return $boolVal ? 'Aktif' : 'Nonaktif';
            }

            if (in_array($field, ['is_permission_enabled', 'is_presence_enabled', 'is_permission_template_active'])) {
                return $boolVal ? 'Aktif / Aktifkan' : 'Nonaktif';
            }
        }

        // Enum formatting
        if ($field === 'gender') {
            if ($value instanceof Gender) return $value->getLabel();
            return Gender::tryFrom((string) $value)?->getLabel() ?? (string) $value;
        }

        if ($field === 'membership_type') {
            if ($value instanceof MembershipType) return $value->getLabel();
            return MembershipType::tryFrom((string) $value)?->getLabel() ?? ucfirst((string) $value);
        }

        if ($field === 'target_gender') {
            if ($value instanceof MeetingTargetGender) return $value->getLabel();
            return MeetingTargetGender::tryFrom((string) $value)?->getLabel() ?? (string) $value;
        }

        if ($field === 'method') {
            if ($value instanceof AttendanceMethod) return $value->getLabel();
            return AttendanceMethod::tryFrom((string) $value)?->getLabel() ?? ucfirst((string) $value);
        }

        if ($field === 'status' && $shortClass === 'Attendance') {
            if ($value instanceof AttendanceStatus) return $value->getLabel();
            return AttendanceStatus::tryFrom((string) $value)?->getLabel() ?? strtoupper((string) $value);
        }

        if ($field === 'role') {
            if ($value instanceof UserRole) return $value->getLabel();
            return UserRole::tryFrom((string) $value)?->getLabel() ?? ucfirst((string) $value);
        }

        // Relation lookups
        if (in_array($field, ['group_id', 'parent_id']) && is_numeric($value)) {
            $id = (int) $value;
            if (!isset(self::$groupNameCache[$id])) {
                self::$groupNameCache[$id] = Group::find($id)?->name ?? "Kelompok #{$id}";
            }
            return self::$groupNameCache[$id];
        }

        if ($field === 'level_id' && is_numeric($value)) {
            $id = (int) $value;
            if (!isset(self::$levelNameCache[$id])) {
                self::$levelNameCache[$id] = Level::find($id)?->name ?? "Tingkat #{$id}";
            }
            return self::$levelNameCache[$id];
        }

        if ($field === 'age_group_id' && is_numeric($value)) {
            $id = (int) $value;
            if (!isset(self::$ageGroupNameCache[$id])) {
                self::$ageGroupNameCache[$id] = AgeGroup::find($id)?->name ?? "Kelompok Usia #{$id}";
            }
            return self::$ageGroupNameCache[$id];
        }

        if ($field === 'card_template_id' && is_numeric($value)) {
            $id = (int) $value;
            if (!isset(self::$cardTemplateNameCache[$id])) {
                self::$cardTemplateNameCache[$id] = CardTemplate::find($id)?->name ?? "Template #{$id}";
            }
            return self::$cardTemplateNameCache[$id];
        }

        // Array / JSON formatting
        if (is_array($value)) {
            return implode(', ', array_map(function ($item) {
                return is_array($item) ? json_encode($item) : (string) $item;
            }, $value));
        }

        // Date / Time formatting
        if (in_array($field, ['meeting_date', 'birth_date'])) {
            try {
                return Carbon::parse($value)->locale('id')->isoFormat('D MMMM Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($field === 'checkin_time') {
            try {
                return Carbon::parse($value)->locale('id')->isoFormat('D MMM Y, HH:mm');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /**
     * Buat narasi kalimat aktif alami Bahasa Indonesia untuk event yang dicatat.
     */
    public static function buildDescription(string $event, mixed $subject, ?array $dirty = null, ?array $old = null): string
    {
        $shortClass = class_basename($subject);

        switch ($shortClass) {
            case 'Member':
                $name = $subject->full_name ?? $subject->name ?? 'Anggota';
                return match ($event) {
                    'created' => "Mendaftarkan anggota baru: {$name}",
                    'updated' => "Memperbarui data anggota: {$name}",
                    'deleted' => "Menghapus data anggota: {$name}",
                    'restored' => "Memulihkan data anggota: {$name}",
                    default => "Mengubah data anggota: {$name}",
                };

            case 'Meeting':
                $name = $subject->name ?? 'Pertemuan';
                return match ($event) {
                    'created' => "Membuat jadwal pertemuan baru: {$name}",
                    'updated' => "Memperbarui informasi pertemuan: {$name}",
                    'deleted' => "Menghapus pertemuan: {$name}",
                    'restored' => "Memulihkan pertemuan: {$name}",
                    default => "Mengubah jadwal pertemuan: {$name}",
                };

            case 'Attendance':
                $memberName = $subject->member?->full_name ?? 'Anggota';
                if ($event === 'updated' && isset($dirty['status'])) {
                    $oldStatus = self::formatValue('Attendance', 'status', $old['status'] ?? null);
                    $newStatus = self::formatValue('Attendance', 'status', $dirty['status']);
                    return "Mengubah status kehadiran {$memberName} dari [{$oldStatus}] menjadi [{$newStatus}]";
                }
                return match ($event) {
                    'created' => "Mencatat kehadiran manual: {$memberName}",
                    'updated' => "Memperbarui catatan kehadiran: {$memberName}",
                    'deleted' => "Membatalkan / Menghapus catatan kehadiran: {$memberName}",
                    default => "Mengubah kehadiran: {$memberName}",
                };

            case 'Group':
                $name = $subject->name ?? 'Kelompok';
                return match ($event) {
                    'created' => "Menambahkan kelompok baru: {$name}",
                    'updated' => "Memperbarui data kelompok: {$name}",
                    'deleted' => "Menghapus kelompok: {$name}",
                    'restored' => "Memulihkan kelompok: {$name}",
                    default => "Mengubah data kelompok: {$name}",
                };

            case 'User':
                $name = $subject->name ?? 'Pengguna';
                return match ($event) {
                    'created' => "Menambahkan akun pengguna baru: {$name}",
                    'updated' => "Memperbarui data akun pengguna: {$name}",
                    'deleted' => "Menghapus akun pengguna: {$name}",
                    'restored' => "Memulihkan akun pengguna: {$name}",
                    default => "Mengubah akun pengguna: {$name}",
                };

            default:
                $label = Str::headline($shortClass);
                $name = $subject->name ?? $subject->id ?? '';
                return match ($event) {
                    'created' => "Menambahkan {$label}: {$name}",
                    'updated' => "Memperbarui {$label}: {$name}",
                    'deleted' => "Menghapus {$label}: {$name}",
                    default => "Aktivitas {$event} pada {$label}: {$name}",
                };
        }
    }

    /**
     * Susun array perbedaan (diff) ramah pengguna untuk ditampilkan pada modal infolist.
     *
     * @return array<int, array{field: string, label: string, old: string, new: string}>
     */
    public static function buildHumanDiff(?string $subjectType, ?array $old, ?array $attributes): array
    {
        $old = $old ?? [];
        $attributes = $attributes ?? [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));
        $diff = [];

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $attributes[$key] ?? null;

            // Jika sama persis, abaikan
            if ($oldVal === $newVal) {
                continue;
            }

            $diff[] = [
                'field' => $key,
                'label' => self::getFieldLabel($subjectType, $key),
                'old' => self::formatValue($subjectType, $key, $oldVal),
                'new' => self::formatValue($subjectType, $key, $newVal),
            ];
        }

        return $diff;
    }
}
