# Outline & To-Do List Fitur Blog (Laravel + Filament)

Dokumen ini berisi perencanaan dan checklist pengembangan fitur blog untuk aplikasi inTime, mulai dari struktur database, halaman admin (menggunakan Filament), hingga tampilan publik (frontend).

## 1. Persiapan Database & Model
- [ ] Membuat migrasi dan model `Category` (Kategori blog: name, slug, description).
- [ ] Membuat migrasi dan model `Tag` serta tabel pivot `post_tag` (Tag: name, slug).
- [ ] Membuat migrasi dan model `Post` (Artikel blog: title, slug, excerpt, content, featured_image, published_at, is_published, category_id, user_id).
- [ ] Membuat migrasi dan model `Comment` (Komentar blog: post_id, user_id, content, is_hidden).
- [ ] Mengatur relasi antar model:
  - `Post` belongsTo `Category`
  - `Post` belongsTo `User` (Author - sementara diset Admin)
  - `Post` belongsToMany `Tag`
  - `Post` hasMany `Comment`
  - `Comment` belongsTo `User`
  - `Comment` belongsTo `Post`

## 2. Halaman Admin (Filament Resources)
- [ ] Membuat `CategoryResource` untuk manajemen kategori (Create, Read, Update, Delete).
- [ ] Membuat `TagResource` untuk manajemen tag (Create, Read, Update, Delete).
- [ ] Membuat `PostResource` untuk manajemen artikel:
  - Form input dengan validasi (Title, Slug otomatis dari title, Content dengan **Rich Editor** bawaan Filament, Upload Featured Image, Pilih Kategori, Multi-select Tags, Status Publish & Tanggal Publish).
  - Tabel list artikel dengan fitur pencarian, filter (berdasarkan status publish & kategori), dan pagination.
- [ ] Membuat fitur manajemen komentar (bisa melalui `CommentResource` atau *RelationManager* pada `PostResource`):
  - Menampilkan daftar komentar yang masuk.
  - Fitur admin untuk "Hide / Unhide" komentar (`is_hidden` toggle).
- [ ] Mengatur hak akses (Role): Sementara hanya **Admin** yang bisa mengelola fitur blog.

## 3. Halaman Publik (Frontend / Tampilan)
- [ ] Membuat controller `BlogController` untuk menghandle request publik.
- [ ] **Modifikasi Landing Page** (`landing.blade.php`):
  - Menambahkan section "Artikel Terbaru" (Latest Posts) yang menampilkan 3-4 artikel terakhir yang sudah berstatus *published*.
- [ ] **Halaman Daftar Blog** (`/blog`):
  - Membuat halaman untuk menampilkan seluruh artikel dengan pagination.
  - Menambahkan fitur pencarian artikel berdasarkan judul atau isi.
  - Menambahkan sidebar/navigasi filter berdasarkan Kategori dan Tags.
- [ ] **Halaman Detail Blog** (`/blog/{slug}`):
  - Menampilkan konten artikel secara utuh beserta gambar.
  - Menampilkan meta informasi (penulis, tanggal rilis, kategori, dan tags).
  - Menampilkan section **Komentar**: Menampilkan komentar-komentar yang tidak disembunyikan (*not hidden*).
  - Menambahkan Form Komentar: Pengguna harus login untuk bisa memposting komentar.
  - (Opsional) Menampilkan section "Artikel Terkait" (Related Posts).

## 4. Finishing & Optimasi
- [ ] Menambahkan dinamis Meta tags (Title, Description) pada halaman blog detail untuk keperluan SEO.
- [ ] Testing alur secara menyeluruh dari admin membuat post hingga komentar masuk dan tampil di publik.
