<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kehadiran - {{ $meeting->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #000; text-transform: uppercase; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .info td { padding: 3px 0; }
        .label { font-weight: bold; width: 150px; }
        
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        table.data th { background-color: #f2f2f2; font-weight: bold; }
        
        .footer { margin-top: 30px; text-align: right; font-style: italic; font-size: 10px; }
        .summary { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN KEHADIRAN PERTEMUAN</h1>
        <p>Aplikasi Presensi inTime</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td class="label">Nama Pertemuan</td>
                <td>: {{ $meeting->name }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td>: {{ $meeting->meeting_date->format('d F Y') }}</td>
            </tr>
            <tr>
                <td class="label">Grup Penyelenggara</td>
                <td>: {{ $meeting->group->name }}</td>
            </tr>
            <tr>
                <td class="label">Dibuat Oleh</td>
                <td>: {{ $meeting->creator->name }}</td>
            </tr>
            <tr>
                <td class="label">Target Peserta</td>
                <td>: 
                    Gender: {{ $meeting->target_gender === 'all' ? 'Semua' : ($meeting->target_gender === 'male' ? 'Laki-laki' : 'Perempuan') }}
                    @if($meeting->target_age_groups)
                        | Usia: {{ implode(', ', $meeting->target_age_groups) }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>ID Anggota</th>
                <th>Nama Lengkap</th>
                <th>Waktu Absen</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>
            @forelse($meeting->attendances as $index => $attendance)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $attendance->member->member_code }}</td>
                    <td>{{ $attendance->member->full_name }}</td>
                    <td>{{ $attendance->checkin_time?->format('H:i:s') ?? '-' }}</td>
                    <td>{{ ucfirst($attendance->method) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Belum ada data kehadiran.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        Total Kehadiran: {{ $meeting->attendances->count() }} Anggota
    </div>

    <div class="footer">
        Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
