# 🚀 Panduan Deployment inTime via SSH & ZIP (Hostinger)

Dokumen ini menjelaskan langkah-langkah deployment aplikasi **inTime** menggunakan metode unggah file ZIP dan finalisasi melalui akses SSH.

## 📋 Persyaratan Sistem
*   **PHP Version:** 8.2 atau 8.3 (Pastikan diatur di hPanel Hostinger).
*   **Database:** MySQL.
*   **Akses SSH:** Aktifkan di menu **Advanced -> SSH Access** di hPanel.

---

## 🛠️ Langkah 1: Persiapan di Lokal (Komputer Anda)

Sebelum mengunggah, kita harus menyiapkan aset dan dependensi:

1.  **Build Aset Frontend (Wajib):** 
    ```bash
    npm install
    npm run build
    ```
2.  **Optimasi Dependensi:**
    ```bash
    composer install --optimize-autoloader --no-dev
    ```
3.  **Membuat Archive ZIP:**
    Kompres seluruh folder proyek Anda menjadi `project.zip`.
    *Excludes:* `node_modules`, `tests`, dan folder `.git` agar file tidak terlalu besar.

---

## 🌐 Langkah 2: Setup di Hostinger hPanel

1.  **Database:** Buat database MySQL baru, user, dan password. Catat detailnya.
2.  **Unggah File:**
    *   Buka **File Manager**.
    *   Unggah `project.zip` ke folder **root** proyek Anda (satu tingkat di atas `public_html`).
    *   Jika sudah ada folder `public_html` bawaan dan masih kosong/default, biarkan saja dulu atau hapus jika Anda ingin membuat symlink baru nanti.

---

## 💻 Langkah 3: Eksekusi via SSH (Penyesuaian Proyek)

Buka terminal di komputer Anda, masuk ke SSH Hostinger, lalu jalankan perintah berikut:

### 1. Ekstrak File ZIP
```bash
unzip project.zip -d intime
```
*(Ganti `intime` dengan nama folder tujuan Anda)*.

### 2. Hubungkan Proyek ke Web (Symlink)
Jika Anda meletakkan proyek di folder `intime`, hubungkan folder `public` aplikasi ke `public_html`:
```bash
# Hapus public_html lama jika perlu
rm -rf public_html

# Buat link baru
ln -s intime/public public_html
```

### 3. Konfigurasi Environment (`.env`)
Salin `.env.example` ke `.env` dan edit:
```bash
cp intime/.env.example intime/.env
nano intime/.env
```
Sesuaikan bagian ini:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nama-domain-anda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=u12345_nama_db
DB_USERNAME=u12345_user_db
DB_PASSWORD=password_anda

FILESYSTEM_DISK=public
```

### 4. Finalisasi Proyek (Artisan Commands)
Masuk ke folder proyek dan jalankan perintah awal:
```bash
cd intime

# Generate Key (Jika belum ada di .env)
php artisan key:generate

# Migrasi Database
php artisan migrate --force

# Membuat Link Storage (Penting untuk QR & Lampiran)
php artisan storage:link

# Optimasi Performa
php artisan optimize
```

---

## 🛡️ Langkah 4: Keamanan & Permission

Pastikan folder storage dan cache bisa ditulis oleh server:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## 💡 Troubleshooting via SSH
*   **Versi PHP CLI:** Jika perintah `php` menjalankan versi lama (misal 7.4), gunakan path lengkap sesuai versi PHP Hostinger, contoh: `/usr/local/bin/php8.2 artisan migrate`.
*   **Error 500:** Selalu periksa log di `storage/logs/laravel.log` menggunakan perintah `tail -n 50 storage/logs/laravel.log` via SSH untuk melihat detail error.

---
*Dokumen diperbarui: 5 Feb 2026 (Metode SSH & ZIP).*
