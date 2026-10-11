# Dokumentasi Database SiAbsen

Dokumen ini mendeskripsikan skema database SiAbsen **yang sudah terimplementasi** di repository. Sumber kebenaran adalah migrasi Laravel yang ada di `database/migrations`; skema tersebut sudah diverifikasi berjalan pada **PostgreSQL 18** (`migrate:fresh --seed` + probe constraint/index/FK) dan **SQLite** (suite test). DDL referensi untuk PostgreSQL tersedia di [`QUERY.sql`](QUERY.sql), dan diagram DBML di [`schema.dbml`](schema.dbml).

Runtime aplikasi memakai Laravel 13 + Eloquent. Koneksi default mengikuti `DB_CONNECTION`; fallback tanpa environment adalah SQLite. Koneksi PostgreSQL memakai timezone sesi `UTC` (lihat `config/database.php`, `DB_TIMEZONE`) agar round-trip `TIMESTAMPTZ` konsisten dengan `app.timezone` (UTC).

## 1. Konvensi Database

- Provider target adalah PostgreSQL; SQLite dipakai untuk test otomatis dan development ringan.
- Semua nama tabel, kolom, constraint, dan index memakai `snake_case`.
- Primary key domain memakai `BIGSERIAL` (identity). Tabel profil memakai `user_id` sebagai primary key sekaligus foreign key.
- Nilai waktu memakai `TIMESTAMPTZ(6)` dengan presisi mikrodetik.
- `created_at` memiliki default `CURRENT_TIMESTAMP`; `updated_at` dikelola Eloquent tanpa default database.
- Boolean memakai `BOOLEAN` dengan default `TRUE`.
- **Nilai terbatas tidak memakai native enum**, melainkan `VARCHAR(20)` + CHECK constraint agar perilaku identik antara PostgreSQL (CHECK) dan SQLite (trigger setara). Nilai valid didefinisikan enum Eloquent `App\Enums\UserRole`, `AttendanceStatus`, `AttendanceRequestStatus`, dan `LocationStatus`.
- Primary key tidak diubah setelah dibuat (`ON UPDATE` `NO ACTION`).
- Penghapusan `users` yang memiliki profile mengikuti `CASCADE`; relasi akademik mengikuti `RESTRICT`; `attendance_requests` memakai `CASCADE` untuk penulis dan `SET NULL` untuk peninjau.
- Database runtime memakai schema `public`.
- Unique constraint `users.email` sudah menjadi index pencarian email; tidak dibuat index kedua.
- Suite test memakai SQLite in-memory **dan** PostgreSQL di CI.

## 2. Ringkasan Tabel

Skema terdiri dari 7 tabel framework Laravel, `users`, dan 11 tabel domain (total 19):

| Tabel                                | Fungsi                                                        |
| ------------------------------------ | ------------------------------------------------------------- |
| `users`                              | Akun, autentikasi, dan role seluruh pengguna SiAbsen.         |
| `password_reset_tokens`              | Token reset password (framework).                             |
| `sessions`                           | Sesi login (framework).                                       |
| `cache`, `cache_locks`               | Cache (framework).                                            |
| `jobs`, `job_batches`, `failed_jobs` | Antrean job (framework).                                      |
| `education_levels`                   | Master jenjang pendidikan.                                    |
| `student_profiles`                   | Data akademik siswa dan nomor induk.                          |
| `teacher_profiles`                   | Penanda akun guru.                                            |
| `classes`                            | Master kelas + titik geofence dan radius.                     |
| `subjects`                           | Master mata pelajaran.                                        |
| `class_students`                     | Riwayat penempatan siswa ke kelas (pivot).                    |
| `teacher_assignments`                | Penugasan guru per kelas dan mata pelajaran.                  |
| `attendance_sessions`                | Sesi absensi: rentang waktu, payload QR, titik lokasi.        |
| `attendance_records`                 | Record hasil pemindaian siswa + hasil evaluasi geofence.      |
| `attendance_requests`                | Pengajuan izin/sakit (alur persetujuan).                      |
| `school_settings`                    | Identitas sekolah: nama, pusat koordinat, dan radius default. |

## 3. Diagram Relasi

Kode DBML lengkap tersedia di [`schema.dbml`](schema.dbml) dan dapat ditempel ke dbdiagram.io. Karena DBML adalah diagram logis, partial unique index, CHECK constraint, dan composite foreign key dijelaskan melalui catatan pada tabel terkait. Berikut ringkasannya:

- `users` 1–0..1 `student_profiles` (CASCADE), 1–0..1 `teacher_profiles` (CASCADE).
- `education_levels` 1–N `student_profiles`, 1–N `classes` (RESTRICT).
- `users` 1–N `class_students` (RESTRICT); `classes` 1–N `class_students` (RESTRICT).
- `users` 1–N `teacher_assignments`; `classes` 1–N `teacher_assignments`; `subjects` 1–N `teacher_assignments` (RESTRICT).
- `teacher_assignments` 1–N `attendance_sessions` via FK komposit `(id, class_id)`; `classes` 1–N `attendance_sessions`; `users` 1–N `attendance_sessions` (RESTRICT).
- `attendance_sessions` 1–N `attendance_records`; `users` 1–N `attendance_records` (RESTRICT).
- `attendance_sessions` 1–N `attendance_requests` (CASCADE); `users` 1–N `attendance_requests` (CASCADE); `users` (peninjau) 0..1–N `attendance_requests` (SET NULL).

## 4. Detail Struktur Tabel

### 4.1 `users`

Menyimpan akun, autentikasi, dan role. Kolom `password` selalu menyimpan hash.

| Kolom               | Tipe             | Null  | Default/Constraint                |
| ------------------- | ---------------- | ----- | --------------------------------- |
| `id`                | `BIGINT`         | Tidak | Identity, primary key             |
| `name`              | `VARCHAR(255)`   | Tidak | Wajib diisi                       |
| `email`             | `VARCHAR(255)`   | Tidak | Unique (`users_email_unique`)     |
| `email_verified_at` | `TIMESTAMPTZ(6)` | Ya    | —                                 |
| `password`          | `VARCHAR(255)`   | Tidak | Hash password                     |
| `role`              | `VARCHAR(20)`    | Tidak | CHECK `STUDENT`/`TEACHER`/`ADMIN` |
| `phone`             | `VARCHAR(30)`    | Ya    | —                                 |
| `birth_date`        | `DATE`           | Ya    | —                                 |
| `remember_token`    | `VARCHAR(100)`   | Ya    | —                                 |
| `created_at`        | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`               |
| `updated_at`        | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent                 |

Model `User` memiliki enum cast `role` (UserRole), relasi profil, dan helper otorisasi via policy.

### 4.2 `student_profiles`

`user_id` sekaligus primary key dan foreign key ke `users` (`CASCADE`).

| Kolom                | Tipe          | Null  | Default/Constraint                  |
| -------------------- | ------------- | ----- | ----------------------------------- |
| `user_id`            | `BIGINT`      | Tidak | PK, FK `users.id` (CASCADE)         |
| `student_number`     | `VARCHAR(50)` | Tidak | Unique                              |
| `education_level_id` | `BIGINT`      | Tidak | FK `education_levels.id` (RESTRICT) |

### 4.3 `teacher_profiles`

| Kolom     | Tipe     | Null  | Default/Constraint          |
| --------- | -------- | ----- | --------------------------- |
| `user_id` | `BIGINT` | Tidak | PK, FK `users.id` (CASCADE) |

### 4.4 `education_levels`

| Kolom        | Tipe             | Null  | Default/Constraint  |
| ------------ | ---------------- | ----- | ------------------- |
| `id`         | `BIGINT`         | Tidak | Identity, PK        |
| `name`       | `VARCHAR(100)`   | Tidak | Unique              |
| `created_at` | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP` |
| `updated_at` | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent   |

### 4.5 `classes`

Kelas di bawah satu jenjang; nama unik per jenjang. Kolom geofence bersifat nullable (turun dari `school_settings` bila kelas tidak menentukan sendiri).

| Kolom                | Tipe             | Null  | Default/Constraint                  |
| -------------------- | ---------------- | ----- | ----------------------------------- |
| `id`                 | `BIGINT`         | Tidak | Identity, PK                        |
| `name`               | `VARCHAR(100)`   | Tidak | Wajib diisi                         |
| `education_level_id` | `BIGINT`         | Tidak | FK `education_levels.id` (RESTRICT) |
| `created_at`         | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`                 |
| `updated_at`         | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent                   |
| `latitude`           | `NUMERIC(10,7)`  | Ya    | Pusat geofence kelas                |
| `longitude`          | `NUMERIC(10,7)`  | Ya    | Pusat geofence kelas                |
| `radius_meters`      | `INTEGER`        | Ya    | Default `50`                        |

### 4.6 `subjects`

| Kolom        | Tipe             | Null  | Default/Constraint  |
| ------------ | ---------------- | ----- | ------------------- |
| `id`         | `BIGINT`         | Tidak | Identity, PK        |
| `name`       | `VARCHAR(150)`   | Tidak | Unique              |
| `created_at` | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP` |
| `updated_at` | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent   |

### 4.7 `class_students`

Pivot penempatan siswa–kelas. Baris `is_active = false` adalah penempatan historis.

| Kolom           | Tipe             | Null  | Default/Constraint         |
| --------------- | ---------------- | ----- | -------------------------- |
| `id`            | `BIGINT`         | Tidak | Identity, PK               |
| `class_id`      | `BIGINT`         | Tidak | FK `classes.id` (RESTRICT) |
| `student_id`    | `BIGINT`         | Tidak | FK `users.id` (RESTRICT)   |
| `is_active`     | `BOOLEAN`        | Tidak | Default `TRUE`             |
| `created_at`    | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`        |
| `updated_at`    | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent          |
| `academic_year` | `VARCHAR(9)`     | Ya    | Contoh `2026/2027`         |

Partial unique index `uq_class_students_active_student` pada `(student_id, academic_year) WHERE is_active` memastikan satu siswa hanya memiliki satu penempatan aktif per tahun ajaran.

### 4.8 `teacher_assignments`

| Kolom        | Tipe             | Null  | Default/Constraint          |
| ------------ | ---------------- | ----- | --------------------------- |
| `id`         | `BIGINT`         | Tidak | Identity, PK                |
| `teacher_id` | `BIGINT`         | Tidak | FK `users.id` (RESTRICT)    |
| `class_id`   | `BIGINT`         | Tidak | FK `classes.id` (RESTRICT)  |
| `subject_id` | `BIGINT`         | Tidak | FK `subjects.id` (RESTRICT) |
| `is_active`  | `BOOLEAN`        | Tidak | Default `TRUE`              |
| `created_at` | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`         |
| `updated_at` | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent           |

Partial unique index `uq_teacher_assignments_active_assignment` pada `(teacher_id, class_id, subject_id) WHERE is_active`. Unique `(id, class_id)` mendukung composite foreign key sesi.

### 4.9 `attendance_sessions`

| Kolom           | Tipe             | Null  | Default/Constraint                                                                    |
| --------------- | ---------------- | ----- | ------------------------------------------------------------------------------------- |
| `id`            | `BIGINT`         | Tidak | Identity, PK                                                                          |
| `assignment_id` | `BIGINT`         | Tidak | Bagian FK komposit `(assignment_id, class_id)` → `teacher_assignments (id, class_id)` |
| `class_id`      | `BIGINT`         | Tidak | FK `classes.id` (RESTRICT)                                                            |
| `session_date`  | `DATE`           | Tidak | Tanggal kalender pada timezone sekolah                                                |
| `start_at`      | `TIMESTAMPTZ(6)` | Tidak | Instan mulai sesi                                                                     |
| `end_at`        | `TIMESTAMPTZ(6)` | Tidak | Instan selesai sesi                                                                   |
| `qr_payload`    | `VARCHAR(255)`   | Tidak | Unique                                                                                |
| `created_by`    | `BIGINT`         | Tidak | FK `users.id` (RESTRICT)                                                              |
| `created_at`    | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`                                                                   |
| `updated_at`    | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent                                                                     |
| `latitude`      | `NUMERIC(10,7)`  | Ya    | Titik lokasi sesi                                                                     |
| `longitude`     | `NUMERIC(10,7)`  | Ya    | Titik lokasi sesi                                                                     |

Check `end_at > start_at` ditegakkan (`chk_attendance_sessions_time`). Unique `(assignment_id, session_date, start_at, end_at)` mencegah duplikasi sesi.

### 4.10 `attendance_records`

Satu record per pasangan (sesi, siswa). Status `TIDAK_HADIR` **tidak disimpan**; dihitung saat pembacaan.

| Kolom             | Tipe             | Null  | Default/Constraint                                     |
| ----------------- | ---------------- | ----- | ------------------------------------------------------ |
| `id`              | `BIGINT`         | Tidak | Identity, PK                                           |
| `session_id`      | `BIGINT`         | Tidak | FK `attendance_sessions.id` (RESTRICT)                 |
| `student_id`      | `BIGINT`         | Tidak | FK `users.id` (RESTRICT)                               |
| `scanned_at`      | `TIMESTAMPTZ(6)` | Tidak | Waktu server                                           |
| `status`          | `VARCHAR(20)`    | Tidak | CHECK `HADIR`/`TERLAMBAT`/`TIDAK_HADIR`/`IZIN`/`SAKIT` |
| `late_minutes`    | `INTEGER`        | Ya    | CHECK NULL atau `>= 0`                                 |
| `created_at`      | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`                                    |
| `updated_at`      | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent                                      |
| `latitude`        | `NUMERIC(10,7)`  | Ya    | Koordinat siswa saat scan                              |
| `longitude`       | `NUMERIC(10,7)`  | Ya    | Koordinat siswa saat scan                              |
| `distance_meters` | `INTEGER`        | Ya    | Jarak ke pusat lokasi (hasil hitung)                   |
| `location_status` | `VARCHAR(20)`    | Ya    | `SESUAI` / `DI_LUAR_RADIUS` / `TIDAK_TERVERIFIKASI`    |

Unique `(session_id, student_id)` menjadikan scan idempoten.

### 4.11 `attendance_requests`

Pengajuan izin/sakit dan koreksi status; wajib ditinjau guru/admin.

| Kolom              | Tipe             | Null  | Default/Constraint                                       |
| ------------------ | ---------------- | ----- | -------------------------------------------------------- |
| `id`               | `BIGINT`         | Tidak | Identity, PK                                             |
| `session_id`       | `BIGINT`         | Tidak | FK `attendance_sessions.id` (CASCADE)                    |
| `student_id`       | `BIGINT`         | Tidak | FK `users.id` (CASCADE)                                  |
| `requested_status` | `VARCHAR(20)`    | Tidak | CHECK `IZIN`/`SAKIT`/`HADIR`/`TERLAMBAT`                 |
| `reason`           | `TEXT`           | Ya    | Alasan pengajuan                                         |
| `evidence_path`    | `VARCHAR(255)`   | Ya    | Bukti pendukung                                          |
| `status`           | `VARCHAR(20)`    | Tidak | Default `PENDING`; CHECK `PENDING`/`APPROVED`/`REJECTED` |
| `reviewed_by`      | `BIGINT`         | Ya    | FK `users.id` (SET NULL)                                 |
| `reviewed_at`      | `TIMESTAMPTZ(6)` | Ya    | Waktu keputusan                                          |
| `created_at`       | `TIMESTAMPTZ(6)` | Tidak | `CURRENT_TIMESTAMP`                                      |
| `updated_at`       | `TIMESTAMPTZ(6)` | Tidak | Dikelola Eloquent                                        |

### 4.12 `school_settings`

Identitas dan pengaturan geofence sekolah (global, dipakai saat kelas tidak mendefinisikan geofence sendiri).

| Kolom           | Tipe             | Null  | Default/Constraint      |
| --------------- | ---------------- | ----- | ----------------------- |
| `id`            | `BIGINT`         | Tidak | Identity, PK            |
| `name`          | `VARCHAR(150)`   | Tidak | Nama sekolah            |
| `latitude`      | `NUMERIC(10,7)`  | Tidak | Pusat koordinat sekolah |
| `longitude`     | `NUMERIC(10,7)`  | Tidak | Pusat koordinat sekolah |
| `radius_meters` | `INTEGER`        | Tidak | Default `50`            |
| `created_at`    | `TIMESTAMPTZ(6)` | Ya    | —                       |
| `updated_at`    | `TIMESTAMPTZ(6)` | Ya    | —                       |

## 5. Nilai Terbatas (Enum Eloquent + CHECK)

Enum **bukan** tipe native PostgreSQL; nilai disimpan sebagai `VARCHAR(20)` dengan CHECK constraint (PostgreSQL) atau trigger (SQLite), dan di-cast ke enum Eloquent.

- `users.role` → `App\Enums\UserRole`: `STUDENT`, `TEACHER`, `ADMIN`.
- `attendance_records.status` → `App\Enums\AttendanceStatus`: `HADIR`, `TERLAMBAT`, `TIDAK_HADIR`, `IZIN`, `SAKIT`.
- `attendance_requests.requested_status` → subset `AttendanceStatus`: `IZIN`, `SAKIT`, `HADIR`, `TERLAMBAT`.
- `attendance_requests.status` → `App\Enums\AttendanceRequestStatus`: `PENDING`, `APPROVED`, `REJECTED`.
- `attendance_records.location_status` → `App\Enums\LocationStatus`: `SESUAI`, `DI_LUAR_RADIUS`, `TIDAK_TERVERIFIKASI`.

Menurut aturan domain, scan hanya membuat `HADIR` atau `TERLAMBAT`; `TIDAK_HADIR`, `IZIN`, dan `SAKIT` dihasilkan oleh pembacaan (`computeAbsent`/`sessionSummary`/`studentHistory`) atau hasil persetujuan pengajuan.

## 6. Unique Constraint dan Index

### Unique constraint / unique index

- `users.email`
- `student_profiles.student_number`
- `education_levels.name`
- `classes (education_level_id, name)`
- `subjects.name`
- `teacher_assignments (id, class_id)`
- `attendance_sessions.qr_payload`
- `attendance_sessions (assignment_id, session_date, start_at, end_at)`
- `attendance_records (session_id, student_id)`

### Partial unique index

- `class_students (student_id, academic_year) WHERE is_active`
- `teacher_assignments (teacher_id, class_id, subject_id) WHERE is_active`

### Index pendukung

- `users.role`
- `student_profiles.education_level_id`
- `class_students (student_id, is_active)`
- `class_students (class_id, is_active)`
- `teacher_assignments (teacher_id, is_active)`
- `teacher_assignments (class_id, is_active)`
- `teacher_assignments (subject_id, is_active)`
- `attendance_sessions (assignment_id, start_at, end_at)`
- `attendance_sessions (class_id, session_date)`
- `attendance_sessions.created_by`
- `attendance_records (student_id, scanned_at)`
- `attendance_records (session_id, status)`
- `attendance_requests.session_id`
- `attendance_requests.student_id`
- `attendance_requests.status`

## 7. Foreign Key

| Tabel anak            | Kolom                       | Tabel induk                          | `ON DELETE` | `ON UPDATE` |
| --------------------- | --------------------------- | ------------------------------------ | ----------- | ----------- |
| `teacher_profiles`    | `user_id`                   | `users`                              | `CASCADE`   | `NO ACTION` |
| `student_profiles`    | `user_id`                   | `users`                              | `CASCADE`   | `NO ACTION` |
| `student_profiles`    | `education_level_id`        | `education_levels`                   | `RESTRICT`  | `NO ACTION` |
| `classes`             | `education_level_id`        | `education_levels`                   | `RESTRICT`  | `NO ACTION` |
| `class_students`      | `class_id`                  | `classes`                            | `RESTRICT`  | `NO ACTION` |
| `class_students`      | `student_id`                | `users`                              | `RESTRICT`  | `NO ACTION` |
| `teacher_assignments` | `teacher_id`                | `users`                              | `RESTRICT`  | `NO ACTION` |
| `teacher_assignments` | `class_id`                  | `classes`                            | `RESTRICT`  | `NO ACTION` |
| `teacher_assignments` | `subject_id`                | `subjects`                           | `RESTRICT`  | `NO ACTION` |
| `attendance_sessions` | `(assignment_id, class_id)` | `teacher_assignments (id, class_id)` | `RESTRICT`  | `NO ACTION` |
| `attendance_sessions` | `class_id`                  | `classes`                            | `RESTRICT`  | `NO ACTION` |
| `attendance_sessions` | `created_by`                | `users`                              | `RESTRICT`  | `NO ACTION` |
| `attendance_records`  | `session_id`                | `attendance_sessions`                | `RESTRICT`  | `NO ACTION` |
| `attendance_records`  | `student_id`                | `users`                              | `RESTRICT`  | `NO ACTION` |
| `attendance_requests` | `session_id`                | `attendance_sessions`                | `CASCADE`   | `NO ACTION` |
| `attendance_requests` | `student_id`                | `users`                              | `CASCADE`   | `NO ACTION` |
| `attendance_requests` | `reviewed_by`               | `users`                              | `SET NULL`  | `NO ACTION` |

## 8. Aturan Integritas Absensi

Aturan sudah diimplementasikan pada `App\Services\AttendanceService` dan diverifikasi di dua driver:

1. Jendela scan adalah `start_at - 15 menit` sampai `end_at`.
2. Scan dari awal jendela sampai `start_at + 15 menit` menghasilkan `HADIR`, `late_minutes = 0`.
3. Scan setelah `start_at + 15 menit` sampai `end_at` menghasilkan `TERLAMBAT` dengan selisih menit.
4. Scan di luar jendela ditolak (validasi `scan`) tanpa membuat record.
5. Unique `(session_id, student_id)` dipakai `firstOrCreate` sehingga scan berulang idempoten.
6. Unique `(assignment_id, session_date, start_at, end_at)` mencegah sesi duplikat untuk assignment yang sama.
7. `TIDAK_HADIR` dihitung saat pembacaan untuk siswa aktif yang belum memindai.
8. Check `end_at > start_at` dan composite foreign key `(assignment_id, class_id)` ditegakkan database.
9. Partial unique `class_students (student_id, academic_year) WHERE is_active`: satu penempatan aktif per tahun ajaran.
10. Partial unique `teacher_assignments (teacher_id, class_id, subject_id) WHERE is_active`: satu assignment aktif per kombinasi.

## 9. Timezone dan Tanggal

- Semua `TIMESTAMPTZ(6)` menyimpan instants.
- `config/app.php` memakai `UTC`. Koneksi `pgsql` menetapkan timezone sesi `DB_TIMEZONE` (default `UTC`) via `config/database.php`, sehingga round-trip `TIMESTAMPTZ` konsisten tanpa bergantung pada timezone server PostgreSQL.
- `session_date` adalah `DATE` yang ditafsirkan pada timezone sekolah (`config/attendance.php`, `ATTENDANCE_TIMEZONE` default `Asia/Jakarta`).
- `start_at`, `end_at`, dan `scanned_at` diolah sebagai instans; batas dan tampilan response API memakai `toIso8601String()` agar frontend menampilkan zona lokal.
- Waktu scan berasal dari server (`CarbonImmutable::now()`); timestamp client tidak dipercaya.

## 10. Verifikasi di PostgreSQL

Flow verifikasi yang dipakai sebagai bukti di F9:

```bash
# 1. Migrasi + seeder
php artisan migrate:fresh --seed

# 2. Probe constraint/index/FK (transaksi, ekspektasi gagal/tolak)
#    chk_users_role, chk_attendance_records_status,
#    chk_attendance_records_late_minutes, chk_attendance_sessions_time,
#    uq_class_students_active_student, uq_teacher_assignments_active_assignment,
#    uq_attendance_records_session_student, uq_attendance_sessions_qr_payload,
#    FK RESTRICT/CASCADE/SET NULL.

# 3. Suite test lintas database
DB_CONNECTION=sqlite php artisan test
DB_CONNECTION=pgsql php artisan test
```

`.docs/QUERY.sql` adalah DDL transaksional konsisten dengan hasil migrasi tersebut; tidak membuat database, role, atau data awal. Eksekusi manual:

```bash
psql --set=ON_ERROR_STOP=1 --dbname=<nama_database> --file=.docs/QUERY.sql
```

## 11. Database Test / CI

- PHPUnit default memakai SQLite in-memory (`:memory:`).
- CI `.github/workflows/tests.yml` menjalankan matriks `sqlite` dan `pgsql`; job `pgsql` menyediakan service `postgres:18` dan menjalankan migrasi, seeder, PHPStan level 7, Pint, serta suite test pada PostgreSQL.
- Migrasi bersifat lintas driver: constraint `CHECK` (PostgreSQL) dan trigger setara (SQLite) dihasilkan oleh migrasi `2026_10_11_120100_add_constraints_and_enum_checks`.

## 12. Kebijakan Perubahan Schema

1. Perlakukan migrasi sebagai sumber kebenaran; `QUERY.sql` dan `schema.dbml` mengikuti hasil `migrate:fresh`.
2. Ubah migrasi, model/enum, `QUERY.sql`, `schema.dbml`, dan `DATABASE.md` secara bersamaan.
3. Setiap perubahan schema harus tetap hijau pada SQLite **dan** PostgreSQL (CI).
4. Pertahankan `DB_TIMEZONE=UTC` pada koneksi `pgsql` agar waktu lintas driver konsisten.
5. Jaga konsistensi `RANCANGAN.md` dengan `DATABASE.md`: seluruh kolom yang sudah ada di migrasi tidak lagi ditandai `[rencana]`.
