# Analisis Kesenjangan Proyek SiAbsen

Dokumen ini memetakan **apa yang kurang** pada repositori SiAbsen: bagian yang belum
dibangun, bagian yang tidak sinkron antar-lapisan (migrasi, model, backend, frontend,
dokumen), serta status kualitas kode. Semua temuan dilandasi pembacaan kode langsung
dan eksekusi perintah pada lingkungan proyek, bukan asumsi.

- **Tanggal tinjauan:** 11 Oktober 2026 (pembaruan setelah `git pull`)
- **Lingkungan:** Windows, PHP 8.5.0 (ekstensi `pdo_pgsql` **tidak aktif**, hanya
  `pdo_sqlite`), Composer 2, Node v26.7.0, npm 12.0.2.
- **Acuan rancangan:** [`RANCANGAN.md`](RANCANGAN.md), [`DATABASE.md`](DATABASE.md),
  [`QUERY.sql`](QUERY.sql).
- **Dokumen terkait:** [`RENCANA_IMPLEMENTASI.md`](RENCANA_IMPLEMENTASI.md) (rencana
  perbaikan berfase) dan [`LAPORAN_TINJAUAN.md`](LAPORAN_TINJAUAN.md) (tinjauan awal).

> **Riwayat pembaruan.** Sebelumnya repositori hanya berisi model `User`. Setelah
> `git pull`, masuk dua komit baru: `7708c36 Models` (menambah 10 model Eloquent +
> migrasi `school_settings`) dan `c156ebe update views` (menambah halaman mockup:
> dashboard guru/siswa, scanner, form izin, login baru). Analisis di bawah sudah
> memperhitungkan keadaan terbaru ini.

---

## 1. Ringkasan Eksekutif

SiAbsen kini telah memiliki **lapisan model Eloquent domain** (10 model) dan
**halaman frontend mockup**, namun **lapisan backend domain masih belum ada**: tidak
ada controller, route data, policy, service, form request, maupun test domain. Halaman
frontend yang baru bersifat **statis (hardcoded)** — scanner QR menyimulasikan hasil
acak, data siswa/kelas di-hardcode, dan tidak ada kamera/geolokasi sungguhan. Selain
itu muncul **regresi keamanan**: route `dashboard` dan route domain baru **tidak lagi
dilindungi middleware `auth`/`verified`**, sehingga dashboard dapat diakses tanpa login.

| Aspek | Status | Keterangan |
| --- | --- | --- |
| Migrasi database (14 migrasi) | **Baik** | Berjalan di SQLite; PostgreSQL belum terverifikasi |
| Seeder data contoh | **Baik** | Berjalan; ±1.000 record absensi |
| Model Eloquent domain | **Sebagian** | 10 model ada; belum ada enum/cast & sebagian relasi |
| Enum & cast (`UserRole`, `AttendanceStatus`) | **Kosong** | `role`/`status` masih string bebas |
| Relasi Eloquent | **Sebagian** | Relasi navigasi ada; `belongsToMany` siswa–kelas belum |
| Policy / otorisasi | **Kosong** | Tidak ada folder `app/Policies` |
| Controller / route data domain | **Kosong** | Hanya route Inertia tanpa data |
| Halaman frontend domain | **Mockup statis** | Ada UI, belum terhubung backend/kamera |
| Factory & test domain | **Kosong** | Hanya `UserFactory` |
| Middleware proteksi route | **Regresi** | `dashboard`/domain tanpa `auth`/`verified` |
| Test suite (`php artisan test`) | **Gagal** | 4 lulus, 1 gagal, 16 error (`users.role`), 3 skip |
| Lint backend (`pint --test`) | **Gagal** | 5 berkas perlu diformat |
| Static analysis (`phpstan`) | **Tak terverifikasi** | Crash PHP memory limit 128M |
| Sinkronisasi dokumen vs kode | **Tidak sinkron** | `school_settings` & geofencing belum terdokumentasi |
| Identitas aplikasi | **Masih starter kit** | Tautan sidebar, tipe `User`, `APP_NAME` |

**Temuan paling kritis:**
1. **Regresi keamanan** — `routes/web.php` tak lagi memakai `middleware(['auth','verified'])`;
   dashboard dan semua halaman domain dapat diakses tanpa autentikasi, dan test
   `DashboardTest` gagal.
2. **`UserFactory` tidak mengisi `users.role`** yang `NOT NULL`, sehingga 16 test error.

---

## 2. Metodologi & Bukti Eksekusi

| Perintah | Hasil pada lingkungan ini |
| --- | --- |
| `php -m` (filter driver DB) | Hanya `pdo_sqlite`, `sqlite3`. `pdo_pgsql` **tidak ada** |
| `php artisan key:generate --force` | Sukses |
| `php artisan test` | **Gagal**: `tests: 24`, `passed: 4`, `failed: 1`, `errors: 16`, `skipped: 3` |
| `vendor\bin\pint --test` | **Gagal**, 5 berkas (`User.php` + 3 migrasi + `DatabaseSeeder.php`) |
| `vendor\bin\phpstan analyse` | **Gagal lingkungan**: `PHPStan process crashed ... memory limit: 128M` |
| `node -v` / `npm -v` | `v26.7.0` / `12.0.2` |
| `Test-Path public/build/manifest.json` | (Belum dibangun) |
| Struktur `app/` | Hanya `Actions, Concerns, Http, Models, Providers` — **tanpa** `Enums/Policies/Services` |
| `app/Http/Controllers` | Hanya `Controller`, `Settings\ProfileController`, `Settings\SecurityController` |

> Catatan: karena `pdo_pgsql` tidak aktif, verifikasi PostgreSQL (partial index,
> composite FK, `CHECK`) belum dapat dijalankan pada lingkungan ini. Status "baik"
> hanya berlaku untuk SQLite in-memory.

---

## 3. Bagian yang Belum Ada (Kurang)

### 3.1 Model Eloquent domain — **sebagian selesai**

Komit `7708c36 Models` menambahkan 10 model berikut:

| Model | Tabel | Catatan |
| --- | --- | --- |
| `StudentProfile` | `student_profiles` | PK `user_id`, `timestamps = false` |
| `TeacherProfile` | `teacher_profiles` | PK `user_id`, `timestamps = false` |
| `EducationLevel` | `education_levels` | relasi `classes`, `studentProfiles` |
| `SchoolClass` | `classes` (`$table`) | relasi jenjang, `classStudents`, `teacherAssignments`, `attendanceSessions` |
| `Subject` | `subjects` | relasi `teacherAssignments` |
| `ClassStudent` | `class_students` | relasi kelas & siswa |
| `TeacherAssignment` | `teacher_assignments` | relasi guru, kelas, mapel, sesi |
| `AttendanceSession` | `attendance_sessions` | relasi assignment, kelas, creator, records |
| `AttendanceRecord` | `attendance_records` | relasi session, student |
| `SchoolSetting` | `school_settings` | **tabel baru**, belum dirujuk siapa pun |

Yang **masih kurang** pada lapisan ini:

- **Enum Eloquent belum ada** (`app/Enums` tidak eksis). `role` dan `status` tetap
  string bebas; tidak ada cast enum (lihat §3.2).
- **Relasi `belongsToMany` siswa–kelas belum ada.** `SchoolClass` hanya punya
  `classStudents()` (`hasMany`), belum `students()` yang melewati pivot dan menghormati
  `is_active`/`academic_year`. Rancangan `RANGANCAN.md` §1.5 menuntut akses siswa aktif
  per kelas secara ringkas.
- **Scope belum ada** (mis. `AttendanceSession::onDate`, `ClassStudent::active`,
  `TeacherAssignment::active`).
- **`SchoolSetting` menganggur**: tidak dipakai di seeder, controller, atau config.

### 3.2 Enum, cast, dan fillable

- `app/Enums` tidak ada; tidak ada `UserRole` (`STUDENT/TEACHER/ADMIN`) maupun
  `AttendanceStatus` (`HADIR/TERLAMBAT/TIDAK_HADIR`).
- `User` masih `#[Fillable(['name', 'email', 'password'])]` (`app/Models/User.php:30`)
  dan `casts()` hanya `email_verified_at`, `password` (`app/Models/User.php:42-48`).
  `role`, `phone`, `birth_date` **tidak** di-cast/di-fill.
- `AttendanceRecord::casts()` **tidak** meng-cast `status` ke enum
  (`app/Models/AttendanceRecord.php:22-33`).
- Catatan bug potensial: `User::classMemberships()` menamai relasi "memberships"
  (`app/Models/User.php:61-64`) sementara relasi lain memakai `hasMany` konsisten.

### 3.3 Otorisasi berbasis peran

Tidak ada folder `app/Policies`, `Gate`, maupun middleware peran. Rancangan
`RANCANGAN.md` §6.4 menuntut otorisasi server (guru hanya mengakses kelas/mapel yang
diampu; siswa hanya melihat riwayat sendiri; admin lintas jenjang).

### 3.4 Controller, route data, form request, dan service domain

- `app/Http/Controllers` hanya berisi `Settings\ProfileController` dan
  `Settings\SecurityController`. Tidak ada controller domain.
- `routes/web.php:5-10` memakai `Route::inertia(...)` **tanpa controller dan tanpa data**:

  ```php
  Route::inertia('/', 'auth/login')->name('home');
  Route::inertia('dashboard', 'dashboard')->name('dashboard');
  Route::inertia('teacher/class', 'teacher/class-view')->name('teacher.class');
  Route::inertia('student/scan', 'student/scanner')->name('student.scan');
  Route::inertia('student/permission', 'student/permission')->name('student.permission');
  ```

  Tidak ada route untuk membuat sesi, memproses pemindaian, rekap, pencarian
  bertingkat, atau persetujuan izin.
- Tidak ada form request, service (`AttendanceService`, `GeofenceService`,
  `ReportService`), policy, job, event, listener, notification, maupun scheduler.

### 3.5 Halaman frontend domain — **mockup statis**

Komit `c156ebe update views` menambahkan halaman, tetapi **belum fungsional**:

| Halaman | Keadaan |
| --- | --- |
| `pages/dashboard.tsx` | Jadwal & status di-hardcode; tombol ganti peran (`student`/`teacher`) lokal, bukan dari `role`; bandingkan `role` huruf kecil vs nilai DB `STUDENT/TEACHER` → selalu jatuh ke 'student' (`dashboard.tsx:12-16`) |
| `pages/auth/login.tsx` | Splash 2 detik + form login; memakai URL hardcoded (`post('/login')`), bukan Wayfinder |
| `pages/student/scanner.tsx` | **Simulasi acak** `Math.random() > 0.3` (`scanner.tsx:14-23`); **tidak ada kamera/QR**, tidak ada `navigator.geolocation` |
| `pages/student/permission.tsx` | Hanya `alert()` lalu `router.visit('/dashboard')`; tidak memanggil backend; data siswa di-hardcode |
| `pages/teacher/class-view.tsx` | Token QR & daftar siswa di-hardcode; "Ganti QR" hanya `alert()`; tombol edit menuju `/student/permission` (salah arahan) |
| `components/modal-peringatan.tsx` | Dialog peringatan geofence; teks contoh belum dibersihkan |

Masalah pendukung:

- **Tidak ada pustaka pemindai QR/kamera** pada `package.json` (tidak ada
  `html5-qrcode`, `@zxing/*`, dsb.).
- **Tidak ada pemakaian `navigator.geolocation`** di seluruh `resources/js`.
- **Penyimpangan Wayfinder**: halaman baru memakai URL hardcoded, padahal proyek
  menyediakan `laravel/wayfinder` dan `@/routes` (dipakai `settings/*`, `welcome`,
  `auth/forgot-password`). Ini mematahkan type-safety route.
- `resources/js/app.tsx:13-29` memetakan `dashboard`, `teacher/*`, `student/*`,
  `auth/*` ke **layout `null`** (tanpa app shell/sidebar). Untuk mockup mobile wajar,
  tetapi harus ditinjau saat mengintegrasikan shell desktop.

### 3.6 Factory, seeder domain, dan test

- Hanya ada `UserFactory`; tidak ada factory untuk kelas, mapel, sesi, atau record.
- `tests/` hanya berisi test bawaan. Tidak ada test untuk aturan absensi (jendela scan,
  keterlambatan, geofencing, rekap, otorisasi).
- `DatabaseSeeder` masih memakai query builder langsung (tidak memakai factory baru).

### 3.7 Tabel pendukung alur persetujuan

`RANCANGAN.md` §2.9 mengusulkan tabel `attendance_requests` (pengajuan izin/koreksi).
Tabel ini **tetap tidak ada**, sehingga alur persetujuan §1.6.1 belum punya penyimpanan.
Halaman `student/permission.tsx` hanya `alert()`.

### 3.8 Kelengkapan autentikasi

`config/fortify.php:145-147` hanya mengaktifkan `resetPasswords`. **Registrasi dan
verifikasi email tidak aktif**, padahal route dashboard dahulu memakai `verified`.
Ditambah regresi §4.1: middleware proteksi justru dihapus dari `routes/web.php`.

### 3.9 Layanan domain yang belum dibangun

1. **Perhitungan geofencing** (Haversine) → `distance_meters` & `location_status`
   (`school_settings`/`classes` menyimpan pusat & radius, tetapi tidak ada hitungannya).
2. **Aturan jendela scan** (`start_at - 15m` s.d. `end_at`) dan penentuan
   `HADIR`/`TERLAMBAT` + `late_minutes` (`DATABASE.md` §8).
3. **Komputasi `TIDAK_HADIR` saat pembacaan** untuk siswa aktif yang belum scan.
4. **Idempotensi pemindaian** (`(session_id, student_id)` unik; `ON CONFLICT DO NOTHING`).
5. **Keamanan payload QR** (kini hanya `Str::random(40)`; belum ada TTL/rotasi/tanda
   tangan, padahal mockup menjanjikan "QR berubah tiap 15 menit, screenshot tidak valid").
6. **Zona waktu sekolah** (`Asia/Jakarta`) yang dapat dikonfigurasi (`DATABASE.md` §9).
7. **Ekspor rekap** (CSV/PDF) dan **jejak audit** keputusan persetujuan.

---

## 4. Bagian yang Tidak Nyambung (Inkonsistensi)

### 4.1 Regresi keamanan: route tidak lagi dilindungi (Kritis, baru)

`routes/web.php` sekarang mendaftarkan `dashboard`, `teacher/class`, `student/scan`,
dan `student/permission` **tanpa middleware `auth`/`verified`**, dan `/` diarahkan ke
`auth/login` sebagai halaman Inertia. Padahal sebelumnya:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});
```

Akibatnya:
- Dashboard dan halaman domain **dapat dibuka tanpa login**.
- Test `DashboardTest::guests_are_redirected_to_the_login_page` **gagal**
  (`expected redirect, received 200`).
- `HandleInertiaRequests` tetap membagikan `auth.user` (bisa `null`), sehingga halaman
  yang mengasumsikan user login akan salah perilaku.

**Perbaikan:** lindungi kembali grup route domain dengan `auth`/`verified` dan
tambahkan middleware peran setelah policy tersedia.

### 4.2 `UserFactory` tidak mengisi `role` → 16 error (Kritis)

Kolom `users.role` `NOT NULL` (`..._create_users_table.php:20`), tetapi
`UserFactory::definition()` (`database/factories/UserFactory.php:25-34`) tidak
mendefinisikan `role`. Setiap `User::factory()->create()` gagal:

```
SQLSTATE[23000]: Integrity constraint violation:
NOT NULL constraint failed: users.role
```

Terverifikasi: `errors: 16`. Model `User` juga belum memasukkan `role` ke
`Fillable`/cast, sehingga perbaikan factory harus disertai perbaikan model.

### 4.3 `dashboard.tsx` salah memetakan `role`

`dashboard.tsx:12-16` membandingkan `auth?.user?.role` dengan literal huruf kecil
`'student'`/`'teacher'`, sedangkan nilai basis data adalah `STUDENT`/`TEACHER`/`ADMIN`.
Karena `types/auth.ts` belum mendeklarasikan `role`, pembacaan dilakukan lewat
`[key: string]: unknown`, tanpa jaminan tipe. Akibatnya deteksi peran selalu gagal.

### 4.4 Tipe data migrasi ≠ DDL target (enum)

| Aspek | Migrasi | `QUERY.sql` |
| --- | --- | --- |
| `users.role` | `VARCHAR(20)` | native enum `user_role` |
| `attendance_records.status` | `VARCHAR(20)` | native enum `attendance_status` |

Validasi nilai berbeda antara runtime Eloquent dan database, dan tidak ada constraint
yang menegakkan nilai sah.

### 4.5 Check constraint masih dikomentari

| Aturan | Lokasi | Status |
| --- | --- | --- |
| `role IN ('STUDENT','TEACHER','ADMIN')` | `..._users_table.php:26` | dikomentari |
| `end_at > start_at` | `..._attendance_sessions_table.php:29` | dikomentari |
| `status IN (...)` | `..._attendance_records_table.php:27-30` | dikomentari |
| `late_minutes IS NULL OR late_minutes >= 0` | `..._attendance_records_table.php:31-34` | dikomentari |

### 4.6 `academic_year` nullable pada partial unique index (risiko integritas)

`..._000010_...:22,27-30`:

```sql
CREATE UNIQUE INDEX uq_class_students_active_student
  ON class_students (student_id, academic_year) WHERE is_active
```

Pada PostgreSQL, `NULL` dianggap berbeda, sehingga **dua baris aktif dengan
`academic_year` NULL untuk siswa yang sama tetap diizinkan** — aturan "satu siswa satu
keanggotaan aktif" (`DATABASE.md` §4.7) dapat dilanggar. Seeder selalu mengisi
`'2026/2027'`, jadi masalah ini laten.

### 4.7 Tabel & model `school_settings` belum terdokumentasi

Migrasi `2026_10_11_110255_create_school_settings_table.php` + model `SchoolSetting`
baru ditambahkan, tetapi **tidak muncul** di `DATABASE.md`, `QUERY.sql`, maupun
`RANCANGAN.md`, dan belum dipakai kode mana pun. Dokumen menjadi tidak akurat.

### 4.8 Dokumentasi tidak sinkron dengan kode

| Dokumen | Isi dokumen | Kenyataan kode |
| --- | --- | --- |
| `RANCANGAN.md` §3.2–3.4 | Seeder **gagal** (`scanned_at` NULL untuk `TIDAK_HADIR`) | Seeder **sudah diperbaiki** dan berjalan sukses |
| `RANCANGAN.md` §2.5, §2.10, §2.11 | Kolom `latitude/longitude/radius_meters/academic_year/distance_meters/location_status` ditandai **[rencana]** | **Sudah ada** di migrasi `..._000010` |
| `DATABASE.md` §2 | Menganggap implementasi "hanya model `User`" | Kini **10 model** + tabel `school_settings` |
| `QUERY.sql` | DDL 10 tabel **tanpa** kolom geofencing/`academic_year`, tanpa `school_settings` | Migrasi sudah berbeda |
| `DATABASE.md` §12 | "Perbarui `QUERY.sql`, DBML, dan `DATABASE.md` bersamaan" | Belum dilakukan |

### 4.9 Identitas aplikasi masih milik starter kit

- `composer.json:3` nama `laravel/react-starter-kit`, description "skeleton application".
- `.env.example:1` `APP_NAME=Laravel`; `resources/js/app.tsx:9` fallback appName `'Laravel'`.
- `resources/js/components/app-sidebar.tsx:29-35` menautkan "Repository" ke
  `github.com/laravel/react-starter-kit` dan "Documentation" ke starter kit.
- `resources/js/types/auth.ts` tipe `User` tidak memuat `role`, `phone`, `birth_date`,
  padahal `HandleInertiaRequests` membagikan model user penuh.
- Halaman mockup memakai merek "EduPresence", bukan "SiAbsen" — perlu disatukan.

### 4.10 README menjanjikan fitur yang belum ada

`README.md:3` menyatakan aplikasi "mengelola data akademik ... dan mencatat kehadiran
siswa melalui sesi absensi". Backend belum ada; halaman masih mockup statis.

### 4.11 CI berpotensi merah

`.github/workflows/tests.yml` → `composer ci:check` → (`composer test`: Pint + PHPUnit).
Pint gagal pada 5 berkas dan test gagal (1 failure + 16 error), sehingga CI merah.

---

## 5. Kualitas Kode & Konfigurasi

- **Pint gagal pada 5 berkas:**
  - `app/Models/User.php` (baru masuk daftar),
  - `..._create_class_students_table.php`,
  - `..._create_teacher_assignments_table.php`,
  - `..._add_geofencing_and_academic_year_fields.php`,
  - `database/seeders/DatabaseSeeder.php`.
  Perbaikan cepat: `composer lint`.
- **PHPStan** belum dapat dijalankan pada lingkungan ini (crash 128M); perlu
  `--memory-limit=1G` atau CI.
- **`public/build/manifest.json` belum ada**, sehingga test yang merender Inertia
  gagal (`Vite manifest not found`). Jalankan `npm install && npm run build` sebelum tes.
- **PostgreSQL belum terverifikasi** (`pdo_pgsql` tidak aktif).
- **`phpunit.xml` tidak menetapkan `APP_KEY`**; `php artisan test` gagal bila `.env`
  belum ada (dimmitigasi `composer setup` di CI).
- **Kualitas frontend** belum tervalidasi karena aset belum dibangun; `npm run check`
  dan `npm run types:check` belum dijalankan pada lingkungan ini.

---

## 6. Yang Sudah Baik (agar tidak "diperbaiki" keliru)

- **14 migrasi berjalan** di SQLite, termasuk composite FK `(assignment_id, class_id)`,
  partial unique index, dan `onUpdate('no action')`/`onDelete` sesuai `DATABASE.md`.
- **10 model Eloquent domain** kini tersedia dengan relasi navigasi dasar
  (`BelongsTo`/`HasMany`), mempercepat fase berikutnya.
- **Halaman UI mockup** (dashboard guru/siswa, scanner, form izin, login) sudah
  menangkap kebutuhan visual/tata letak aplikasi — berguna sebagai acuan integrasi.
- **Seeder konsisten dengan skema terbaru** (geofencing, `academic_year`, `location_status`).
- **Struktur starter kit** (Inertia + React + Fortify + Wayfinder) sesuai pilihan
  arsitektur `RANCANGAN.md` §7.

---

## 7. Daftar Kesenjangan Berprioritas

| # | Prioritas | Kesenjangan | Dampak | Rujukan |
| --- | --- | --- | --- | --- |
| 1 | **Kritis** | Route domain tanpa `auth`/`verified` | Aplikasi dapat diakses tanpa login; test gagal | §4.1 |
| 2 | **Kritis** | `UserFactory` tak isi `role`; cast/fillable `User` kurang | 16 test error; CI merah | §4.2 |
| 3 | **Kritis** | Enum `UserRole`/`AttendanceStatus` & cast belum ada | Domain tak type-safe; validasi lemah | §3.2 |
| 4 | **Tinggi** | Controller/service/route data absensi belum ada | Fitur inti tak berjalan | §3.4, §3.9 |
| 5 | **Tinggi** | Halaman frontend masih mockup statis (kamera/lokasi/backend) | Fitur tak dapat dipakai | §3.5 |
| 6 | **Tinggi** | Policy/middleware peran belum ada | Otorisasi belum ditegakkan | §3.3 |
| 7 | **Sedang** | Factory & test domain belum ada | Regresi tak terdeteksi | §3.6 |
| 8 | **Sedang** | Tabel `attendance_requests` belum ada | Alur persetujuan tanpa storage | §3.7 |
| 9 | **Sedang** | Check constraint & enum tidak selaras migrasi↔DDL | Integritas DB lemah | §4.4–4.5 |
| 10 | **Sedang** | `academic_year` NULL pada partial unique index | Risiko pelanggaran keanggotaan aktif | §4.6 |
| 11 | **Sedang** | `school_settings` & geofencing tak terdokumentasi | Dokumen tak tepercaya | §4.7–4.8 |
| 12 | **Sedang** | Pint gagal 5 berkas; PHPStan & PostgreSQL belum terverifikasi | CI merah; kualitas tak terjamin | §5 |
| 13 | **Rendah** | Wayfinder diabaikan; tipe `User`/branding starter kit | Kerapian, konsistensi, UX | §3.5, §4.9 |
| 14 | **Rendah** | README menjanjikan fitur yang belum ada | Ekspektasi tidak akurat | §4.10 |

---

## 8. Lampiran: Ringkasan Bukti

```
$ php artisan test
  tests: 24  passed: 4  failed: 1  errors: 16  skipped: 3
  - DashboardTest: expected redirect (dashboard tidak lagi diproteksi)
  - NOT NULL constraint failed: users.role

$ .\vendor\bin\pint --test
  fail: User.php, ...006, ...007, ...010, DatabaseSeeder.php

$ .\vendor\bin\phpstan analyse
  Child process error: PHPStan process crashed (memory limit: 128M)

$ php -m   # filter
  pdo_sqlite, sqlite3        # pdo_pgsql tidak ada
```

Struktur repositori terbaru:

```
app/Models/               -> 10 model domain + User
app/Http/Controllers/     -> hanya Settings/*
app/Policies/             -> tidak ada
app/Enums/                -> tidak ada
app/Services/             -> tidak ada
routes/web.php            -> 5 route Inertia tanpa middleware & tanpa controller
resources/js/pages/       -> dashboard, auth/login, student/{scanner,permission},
                             teacher/class-view (semuanya mockup)
database/migrations/      -> + create_school_settings_table
.docs/Front End/          -> salinan referensi desain (bukan kode aktif)
```
