# Laporan Tinjauan Proyek SiAbsen

Dokumen ini merangkum hasil pemeriksaan kondisi nyata repositori SiAbsen: apa yang
**belum ada (kurang)**, apa yang **tidak nyambung** antar lapisan (skema, model,
backend, frontend, dokumentasi), serta bukti verifikasi. Fokus laporan adalah
kesenjangan, bukan penilaian ulang rancangan yang sudah ada di `.docs/RANCANGAN.md`
dan `.docs/DATABASE.md`.

Tanggal tinjauan: 11 Oktober 2026. Lingkungan: Windows, PHP 8.5 (ekstensi `pdo_pgsql`
tidak aktif, hanya `pdo_sqlite`), Composer 2.8, Node/npm belum terpasang.

---

## 1. Ringkasan Eksekutif

SiAbsen **baru sebatas "starter kit Laravel + React" yang sudah dilengkapi skema
database dan data contoh**, tetapi **belum memiliki lapisan domain aplikasi sama
sekali**. Skema 10 tabel dan seeder sudah berjalan, namun tidak ada model Eloquent,
tidak ada controller/route, dan tidak ada halaman frontend untuk absensi, rekap,
maupun pencarian bertingkat. Akibatnya aplikasi hanya bisa login dan membuka
dashboard kosong (placeholder).

Temuan paling kritis: **seluruh test bawaan gagal** karena `UserFactory` belum
mengisi kolom `users.role` yang `NOT NULL`. Ini menandakan perubahan skema domain
(tambah `role`) belum diikuti perubahan model/factory.

| Aspek | Status |
| --- | --- |
| Migrasi database (10 tabel + geofencing) | Baik, berjalan |
| Seeder data contoh | Baik, berjalan (1.013 record absensi) |
| Model Eloquent domain | **Belum ada** (hanya `User`) |
| Controller / Route domain | **Belum ada** |
| Halaman frontend domain (scan, rekap, cari) | **Belum ada** |
| Enum/cast & relasi Eloquent | **Belum ada** |
| Test domain | **Belum ada** |
| Test bawaan (`php artisan test`) | **Gagal** (16 error `users.role`) |
| Lint backend (`pint --test`) | **Gagal** (4 berkas) |
| Sinkronisasi dokumen vs kode | **Tidak sinkron** |

---

## 2. Metodologi

Pemeriksaan dilakukan dengan membaca langsung kode dan menjalankan tool yang
tersedia. Perintah yang dijalankan dan hasilnya:

| Perintah | Hasil |
| --- | --- |
| `composer install` | Sukses (101 paket) |
| `php artisan test` (tanpa `.env`) | 24 test, 1 lulus, 20 error (kunci enkripsi kosong) |
| `php artisan test` (setelah `key:generate`) | 24 test, **16 error `NOT NULL constraint failed: users.role`** |
| `php artisan migrate:fresh --seed` (SQLite) | **Sukses**, 12 migrasi + seed |
| Hitung tabel via tinker | users=131, levels=3, classes=12, subjects=8, assignments=24, sessions=120, **records=1.013** |
| `./vendor/bin/pint --test` | **Gagal**, 4 berkas perlu diformat |
| `./vendor/bin/phpstan analyse` | Gagal karena masalah lingkungan (DLL turbo-ext PHP 8.5), bukan kode |

> Catatan: `pdo_pgsql` belum aktif di lingkungan tinjauan, sehingga verifikasi
> PostgreSQL tidak dapat dijalankan (sesuai catatan lingkungan di `RANCANGAN.md`).
> Verifikasi migrasi/seeder memakai SQLite.

---

## 3. Apa yang Kurang (Belum Ada)

### 3.1 Lapisan domain backend belum dibangun

Yang ada hanya `app/Models/User.php`. Sembilan tabel domain **tidak punya model
Eloquent**: `StudentProfile`, `TeacherProfile`, `EducationLevel`, `ClassRoom`,
`Subject`, `ClassStudent`, `TeacherAssignment`, `AttendanceSession`,
`AttendanceRecord`. Konsekuensinya:

- Tidak ada **enum** `UserRole` dan `AttendanceStatus`, tidak ada **cast** pada
  `User` (`role`), sehingga nilai `STUDENT/TEACHER/ADMIN` dan
  `HADIR/TERLAMBAT/TIDAK_HADIR` hanya berupa string bebas (`app/Models/User.php:39`).
- Tidak ada **relasi** Eloquent (`hasOne` profil, `hasMany` sesi/record, `belongsTo`
  kelas/mapel, `belongsToMany` siswa–kelas). Rancangan relasi di
  `RANCANGAN.md` bagian 1.5 belum diimplementasikan.
- Tidak ada **policy/otorisasi** berbasis peran, padahal `RANCANGAN.md` bagian 6.4
  menuntut otorisasi di sisi server.

### 3.2 Controller, route, request, dan action domain belum ada

- `routes/web.php` hanya mendefinisikan `home` (welcome) dan `dashboard`
  (`routes/web.php:5-9`). Tidak ada route untuk absensi, sesi, rekap, pencarian
  bertingkat, maupun pengajuan persetujuan.
- `app/Http/Controllers` hanya berisi `Settings\ProfileController` dan
  `Settings\SecurityController`. Tidak ada controller domain.
- Tidak ada form request domain, job, event, listener, notification, atau scheduler
  (mis. penutupan sesi/penghitungan `TIDAK_HADIR`).

### 3.3 Frontend domain belum ada

- Halaman di `resources/js/pages` hanya: `welcome`, `dashboard`, `auth/*`,
  `settings/*`. Dashboard masih **placeholder kosong**
  (`resources/js/pages/dashboard.tsx:10-23`).
- Tidak ada halaman **scan QR**, **halaman rekap** (bagian 6 dokumen rancangan),
  maupun **pencarian bertingkat** (bagian 1.6.1).
- Navigasi samping hanya "Dashboard" (`resources/js/components/app-sidebar.tsx:19-25`).
- Tidak ada pustaka pemindai QR/kamera di `package.json`, tidak ada pemakaian
  `navigator.geolocation` untuk verifikasi lokasi (pencarian pada `resources/js`
  tidak menemukan `qr`, `scanner`, atau `geolocation`).

### 3.4 Test domain dan factory domain belum ada

- Folder `tests` hanya berisi test bawaan (auth, settings, dashboard, example).
  Tidak ada test untuk absensi, geofencing, aturan jendela scan, atau rekap.
- Hanya ada `UserFactory`. Tidak ada factory untuk kelas, sesi, atau record.

### 3.5 Tabel pendukung alur persetujuan belum ada

`RANCANGAN.md` bagian 2.9 mengusulkan tabel `attendance_requests`
(pengajuan izin/koreksi). Tabel ini **tidak ada** di migrasi mana pun, sehingga alur
persetujuan bagian 1.6.1 belum punya tempat penyimpanan (masih berstatus rencana).

### 3.6 Fitur autentikasi belum lengkap untuk kebutuhan aplikasi

`config/fortify.php:145-147` hanya mengaktifkan `resetPasswords`. Tidak ada
`registration` maupun `emailVerification`, padahal route dashboard memakai middleware
`verified` (`routes/web.php:7`) dan `UserFactory` mengeset `email_verified_at`.
Pembuatan akun hanya lewat seeder.

---

## 4. Apa yang Tidak Nyambung

### 4.1 `UserFactory` tidak mengisi `role` → semua test gagal (Kritis)

Kolom `users.role` bersifat `NOT NULL` (`database/migrations/0001_01_01_000000_create_users_table.php:20`),
tetapi `UserFactory` tidak mendefinisikan `role`
(`database/factories/UserFactory.php:27-33`) dan `User` tidak memasukkan `role` ke
`Fillable`/cast (`app/Models/User.php:27,39-45`). Akibatnya setiap
`User::factory()->create()` gagal:

```
SQLSTATE[23000]: Integrity constraint violation:
NOT NULL constraint failed: users.role
```

Ini menyebabkan 16 error pada suite test bawaan. **Perbaikan:** tambahkan `role`
ke `definition()` factory dan ke cast/fillable model.

### 4.2 Tipe data migrasi ≠ DDL target (enum)

- `QUERY.sql:5-15` memakai native enum PostgreSQL `user_role` dan
  `attendance_status`.
- Migrasi memakai `VARCHAR(20)`: `role` (`...000000_...users_table.php:20`) dan
  `status` (`...000009_...attendance_records_table.php:22`).

Perbedaan ini membuat validasi nilai berbeda antara runtime Eloquent dan database,
dan tidak ada check/constraint yang menegakkan nilai sah.

### 4.3 Check constraint masih dikomentari

Aturan integritas yang menjadi kontrak dokumen belum ditegakkan database:

| Aturan | Lokasi | Status |
| --- | --- | --- |
| `role IN ('STUDENT','TEACHER','ADMIN')` | `...users_table.php:26` | dikomentari |
| `end_at > start_at` | `...attendance_sessions_table.php:29` | dikomentari |
| `status IN (...)` | `...attendance_records_table.php:27-30` | dikomentari |
| `late_minutes >= 0` | `...attendance_records_table.php:31-34` | dikomentari |

`DATABASE.md` bagian 8 dan `RANCANGAN.md` catatan #8 menargetkan constraint ini aktif.

### 4.4 Partial unique index dan `academic_year` yang nullable (risiko integritas)

Migrasi `...000010_add_geofencing_and_academic_year_fields.php:27-30` membuat ulang:

```sql
CREATE UNIQUE INDEX uq_class_students_active_student
  ON class_students (student_id, academic_year) WHERE is_active
```

`academic_year` nullable (`:22`). Pada PostgreSQL, `NULL` dianggap berbeda, sehingga
**dua baris aktif dengan `academic_year` NULL untuk siswa yang sama tetap boleh ada** —
aturan "satu siswa satu keanggotaan aktif" (`DATABASE.md` bagian 4.7) bisa dilanggar
bila kolom tidak diisi. Seeder selalu mengisi `'2026/2027'`, sehingga masalah ini
laten (belum terlihat) tetapi nyata.

### 4.5 Dokumentasi tidak sinkron dengan kode

| Dokumen | Isi dokumen | Kenyataan kode |
| --- | --- | --- |
| `RANCANGAN.md` bagian 3.2 | Seeder **gagal** (`scanned_at` NULL untuk `TIDAK_HADIR`) | Seeder **sudah diperbaiki** (`continue` untuk `TIDAK_HADIR`) dan **berjalan sukses** (1.013 record) |
| `RANCANGAN.md` bagian 2.5, 2.10, 2.11 | Kolom `latitude/longitude/radius_meters/academic_year/distance_meters/location_status` ditandai **[rencana]** | **Sudah ada** di migrasi `...000010...` |
| `DATABASE.md` bagian 2 | Menganggap status implementasi "hanya model `User`" | Benar untuk model, tetapi mengabaikan kolom geofencing yang sudah ditambahkan |
| `QUERY.sql` | DDL 10 tabel tanpa kolom geofencing | Migrasi **sudah punya** kolom geofencing → `QUERY.sql` bukan lagi representasi skema aktual |
| `DATABASE.md` bagian 12 | "Perbarui `QUERY.sql`, DBML, dan `DATABASE.md` bersamaan" | Belum dilakukan setelah migrasi geofencing ditambah |

### 4.6 Identitas aplikasi masih milik starter kit

- `composer.json:3` name `laravel/react-starter-kit`, `:4` description "skeleton application".
- `.env.example:1` `APP_NAME=Laravel`.
- `resources/js/components/app-sidebar.tsx:29-36` menautkan "Repository" ke
  `github.com/laravel/react-starter-kit` dan "Documentation" ke starter kit, bukan SiAbsen.
- `resources/js/types/auth.ts` tipe `User` tidak memuat `role`, `phone`, atau
  `birth_date`, padahal `HandleInertiaRequests` membagikan model user penuh
  (`app/Http/Middleware/HandleInertiaRequests.php:41-43`). Frontend tidak bisa
  memakai `role` secara bertipe untuk navigasi/otorisasi.

### 4.7 README menjanjikan fitur yang belum ada

`README.md:3` menyatakan aplikasi "mengelola data akademik ... dan mencatat kehadiran
siswa melalui sesi absensi". Saat ini hanya login/dashboard/settings yang berfungsi;
fitur absensi belum ada di backend maupun frontend.

### 4.8 CI berpotensi merah

`.github/workflows/tests.yml` menjalankan `composer ci:check`, yang di dalamnya
menjalankan `composer test` → `@lint:check` (Pint) dan `@php artisan test`. Karena
Pint gagal pada 4 berkas dan test gagal pada `users.role`, pipeline CI akan gagal.

---

## 5. Kualitas Kode & Konfigurasi

- **Pint gagal** pada:
  - `database/migrations/2026_09_25_000006_create_class_students_table.php`
  - `database/migrations/2026_09_25_000007_create_teacher_assignments_table.php`
  - `database/migrations/2026_09_25_000010_add_geofencing_and_academic_year_fields.php`
  - `database/seeders/DatabaseSeeder.php`
  - Perbaikan cepat: `composer lint`.
- **PHPStan** (level 7) tidak dapat dijalankan di lingkungan tinjauan karena DLL
  turbo-ext untuk PHP 8.5 tidak ditemukan; perlu dicoba di lingkungan/CI lain untuk
  memastikan status sebenarnya.
- **Test butuh aset terbuilt**: `ExampleTest`/render Inertia memerlukan
  `public/build/manifest.json` (Vite). Tanpa `npm run build`, test render gagal
  (`Vite manifest not found`). CI menjalankan `composer setup` yang mem-build aset,
  tetapi developer lokal perlu menjalankan `npm install && npm run build` lebih dulu.
- `.env.example` memakai `DB_CONNECTION=pgsql`, sedangkan default
  `config/database.php:20` adalah `sqlite`. Perbedaan ini wajar, tetapi perlu
  didokumentasikan agar tidak membingungkan.
- Tidak ada `.env` ter-commit (benar), namun `phpunit.xml` tidak menetapkan
  `APP_KEY`, sehingga `php artisan test` gagal jika `.env` belum dibuat.

---

## 6. Rekomendasi Berurutan (Prioritas)

| # | Prioritas | Tindakan | Dampak |
| --- | --- | --- | --- |
| 1 | Kritis | Tambah `role` ke `UserFactory` dan cast/fillable `User` | Test bawaan hijau kembali |
| 2 | Kritis | Buat model Eloquent + enum + relasi untuk 9 tabel domain | Fondasi seluruh fitur |
| 3 | Tinggi | Buat controller, request, route, dan policy domain | Fitur dapat diakses |
| 4 | Tinggi | Bangun halaman frontend: sesi/QR, scan, rekap, pencarian bertingkat | Fitur dapat dipakai |
| 5 | Tinggi | Jalankan `composer lint` (Pint) lalu pertahankan CI hijau | Kualitas & pipeline |
| 6 | Sedang | Aktifkan check constraint (`role`, `end_at > start_at`, `late_minutes >= 0`) | Integritas database |
| 7 | Sedang | Selaraskan strategi enum (native enum PostgreSQL vs `VARCHAR`) antara migrasi dan `QUERY.sql` | Konsistensi runtime/DDL |
| 8 | Sedang | Tangani `academic_year` NULL pada partial unique index | Cegah pelanggaran keanggotaan aktif |
| 9 | Sedang | Sinkronkan `QUERY.sql`, `DATABASE.md`, dan `RANCANGAN.md` dengan migrasi geofencing | Dokumen tepercaya |
| 10 | Rendah | Rapikan identitas (nama app, `APP_NAME`, tautan sidebar, tipe `User`) | Kerapian & UX |
| 11 | Rendah | Tambah tabel `attendance_requests` bila alur persetujuan dikerjakan | Kelengkapan fitur |

---

## 7. Yang Sudah Baik (agar tidak "diperbaiki" keliru)

- 10 migrasi domain berjalan mulus di SQLite (dan rancangan setara untuk PostgreSQL),
  termasuk composite foreign key `(assignment_id, class_id)`, partial unique index,
  dan dirilis dengan `onUpdate('no action')`/`onDelete` sesuai `DATABASE.md`.
- Seeder konsisten dengan skema terbaru: mengisi `academic_year`, geofencing kelas,
  koordinat & `location_status` per record, serta melewati `TIDAK_HADIR` (sesuai
  aturan "dihitung saat baca").
- Struktur starter kit (Inertia + React + Fortify + Wayfinder) sudah sesuai pilihan
  arsitektur di `RANCANGAN.md` bagian 7.

---

## 8. Lampiran: Bukti Ringkas

```
$ php artisan migrate:fresh --seed   # DB_CONNECTION=sqlite
... 12 migrasi DONE ...
INFO Seeding database.                # sukses, tanpa error

$ php artisan tinker --execute="...count()..."
users=131 levels=3 classes=12 subjects=8
assignments=24 sessions=120 records=1013

$ php artisan test
Failures/Errors: NOT NULL constraint failed: users.role   (16 error)

$ ./vendor/bin/pint --test
{"result":"fail","files":[ ...6, ...7, ...10, DatabaseSeeder.php ]}

$ grep -R "Attendance|StudentProfile|TeacherAssignment" app/
(no files found)   # lapisan domain belum ada
```
