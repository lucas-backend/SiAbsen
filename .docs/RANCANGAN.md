# Rancangan Proyek SiAbsen

Dokumen ini merupakan acuan kerja tim Kelompok SiAbsen untuk membangun aplikasi absensi sekolah berbasis QR code. Isi dokumen disusun berdasarkan kondisi nyata repositori (migrasi, seeder, model, dan stack aplikasi), bukan asumsi.

---

## 0. Identitas Kelompok

- **Nama Kelompok:** Kelompok SiAbsen
- **Repositori Git:** <https://github.com/lucas-backend/SiAbsen>

| Nama                    | NPM        |
| ----------------------- | ---------- |
| I Made Dipa Rama Artike | 2415061001 |
| Rifki Yudika Perdana    | 2415061090 |
| Riffa Yudika Perdana    | 2415061091 |
| Arqan Purusa Eryan      | 2415061055 |
| Yostiar Aminudin        | 2415061016 |

---

## 1. Gambaran Umum Proyek

### 1.1 Tema

Pendidikan: sistem absensi berbasis QR code untuk sekolah. Aplikasi mencatat kehadiran siswa pada tiap sesi mata pelajaran, memverifikasi lokasi pemindaian, dan menyajikan rekapitulasi yang dapat difilter per kelas, periode, dan siswa.

### 1.2 Masalah yang Dijawab

Absensi konvensional yang dilakukan dengan memanggil nama atau menandatangani daftar hadir memakan waktu cukup lama di awal jam pelajaran. Setiap menit yang habis untuk pemanggilan siswa adalah waktu yang tidak terpakai untuk kegiatan belajar, sehingga waktu belajar efektif berkurang, terutama pada kelas besar dan jadwal yang padat.

Persoalan berikutnya muncul saat data absensi perlu diolah. Pencatatan dan perhitungan manual rawan terhadap kesalahan manusia (salah catat, salah hitung, atau daftar yang tercecer), dan menyusun rekapitulasi untuk periode tertentu—misalnya satu minggu atau satu bulan—menjadi pekerjaan yang melelahkan dan memakan waktu. Akibatnya, laporan kehadiran sering terlambat dan sulit dipertanggungjawabkan secara konsisten.

Masalah ketiga lebih serius: absensi yang hanya dilakukan sekali di pagi hari tidak mencegah seorang siswa meninggalkan sekolah di tengah jam pelajaran. Sistem absensi harian tidak memiliki cara memastikan siswa benar-benar hadir secara fisik saat pergantian mata pelajaran, sehingga peluang membolos tetap terbuka. SiAbsen menjawabnya dengan absensi per sesi pelajaran yang disertai verifikasi lokasi pemindaian, sehingga kehadiran bukan hanya tercatat, tetapi juga terverifikasi.

### 1.3 Entitas Utama

- **User (Siswa & Guru).** Menyimpan akun seluruh pengguna, mencakup identitas dasar (`name`, `email`, `password`) dan `role`. Kolom `role` membedakan sub-tipe pengguna; siswa dan guru berbagi entitas `users` yang sama, lalu diperkaya oleh tabel profil.
- **Kelas.** Menyimpan rombongan belajar pada suatu jenjang pendidikan (`classes`), dengan identitas unik berupa kombinasi `education_level_id` dan `name`. Kelas menjadi jembatan antara siswa, guru, dan sesi absensi. Kelas juga membawa titik pusat geofence (`latitude`, `longitude`, `radius_meters`).
- **Absensi.** Terdiri atas dua tingkat: `attendance_sessions` (sesi absensi statis yang menyimpan rentang waktu dan payload QR) dan `attendance_records` (hasil pemindaian siswa per sesi, memuat status, waktu, dan hasil verifikasi lokasi). Pengajuan izin/sakit/koreksi disimpan di `attendance_requests`.
- **Pengaturan sekolah.** `school_settings` menyimpan identitas dan geofence global sekolah yang menjadi default saat kelas tidak mendefinisikan geofence sendiri.

Perbedaan Siswa dan Guru terletak pada role dan tabel profil: Siswa memiliki `student_profiles` dengan `student_number` serta `education_level_id`, sedangkan Guru ditandai oleh `teacher_profiles`. Guru memiliki kewenangan mengajar dan mengelola absensi, sementara siswa hanya melakukan absensi dan melihat riwayatnya.

### 1.4 Peran Pengguna

| Peran | Kewenangan                                                                                                                 |
| ----- | -------------------------------------------------------------------------------------------------------------------------- |
| Guru  | Generate QR code absensi, kelola data siswa/kelas/jadwal, merekap absensi, memantau status kehadiran siswa secara realtime |
| Siswa | Melakukan absensi via scan QR code, melihat riwayat absen sendiri                                                          |

### 1.5 Relasi Antar Entitas

| Entitas A    | Entitas B | Jenis Relasi | Implementasi di Repositori                       |
| ------------ | --------- | ------------ | ------------------------------------------------ |
| User (Siswa) | Kelas     | Many to Many | Tabel pivot `class_students`                     |
| User (Guru)  | Kelas     | One to Many  | Tabel `teacher_assignments` (per mata pelajaran) |
| User (Siswa) | Absensi   | One to Many  | `attendance_records.student_id`                  |

**Alasan desain tiap relasi:**

- **Siswa–Kelas sebagai many-to-many.** Satu siswa dapat berpindah kelas sepanjang masa sekolah, dan satu kelas berisi banyak siswa. Karena itu hubungan tidak disimpan langsung di `users`, melainkan pada tabel pivot `class_students`. Pivot ini tidak sekadar memetakan dua id; ia membawa atribut tambahan `is_active` yang menandai penempatan yang sedang berlaku dan `academic_year` untuk membedakan tahun ajaran. Baris historis (`is_active = false`) tetap disimpan agar riwayat penempatan tidak hilang. Partial unique index `uq_class_students_active_student` (`student_id, academic_year WHERE is_active`) menjamin satu siswa hanya memiliki satu penempatan aktif per tahun ajaran.
- **Guru–Kelas sebagai one-to-many (melalui penugasan).** Pada dasarnya seorang guru dapat mengampu beberapa kelas, tetapi konteks mengajar selalu melekat pada pasangan kelas dan mata pelajaran. Karena itu relasi tidak dibuat langsung guru–kelas, melainkan lewat tabel `teacher_assignments` yang menghubungkan `teacher_id`, `class_id`, dan `subject_id`. Dari sudut pandang satu guru, relasi ke kelas-kelas yang diampu bersifat satu-ke-banyak. Partial unique index `uq_teacher_assignments_active_assignment` mencegah duplikasi penugasan aktif untuk kombinasi guru, kelas, dan mata pelajaran yang sama.
- **Siswa–Absensi sebagai one-to-many.** Satu siswa menghasilkan banyak record absensi dari waktu ke waktu, sedangkan setiap record absensi hanya milik satu siswa. Relasi diwakili `attendance_records.student_id` dengan foreign key ke `users.id`. Unique `(session_id, student_id)` memastikan tidak ada record ganda untuk siswa yang sama pada sesi yang sama.

### 1.6 Alur Interaksi yang Dipilih

#### 1.6.1 Pencarian Bertingkat dan Alur Persetujuan

**Konteks.** Data akademik SiAbsen tersusun berjenjang. Pada skema yang ada, jenjang teratas adalah tingkat pendidikan (`education_levels`), lalu kelas (`classes`), lalu siswa (`users` yang berprofil `student_profiles` via `class_students`). Istilah "sekolah" pada hierarki umum dipetakan ke jenjang pendidikan; apabila SiAbsen nantinya dipakai untuk banyak sekolah, tabel `schools` dapat disisipkan di atas `education_levels`.

**Pencarian bertingkat (langkah per langkah):**

1. Guru membuka halaman pencarian data akademik.
2. Guru memilih jenjang pendidikan (mis. SD, SMP, SMA) pada filter tingkat pertama.
3. Sistem memuat daftar kelas (`classes`) yang `education_level_id`-nya sesuai pilihan tersebut, memanfaatkan index `uq_classes_education_level_name`.
4. Guru memilih kelas pada filter tingkat kedua.
5. Sistem memuat daftar siswa aktif kelas tersebut. Daftar diambil dari `class_students` yang `is_active = true`, di-join ke `student_profiles` untuk menampilkan `student_number`.
6. Guru memilih siswa untuk membuka detail (profil, riwayat absensi, dan aksi yang tersedia). Karena satu siswa hanya punya satu penempatan aktif per tahun ajaran, hasil pencarian bersifat tunggal dan tidak ambigu.

**Alur persetujuan.** SiAbsen memerlukan persetujuan agar siswa tidak bisa mengubah status kehadirannya secara sepihak:

1. Siswa mengajukan izin atau sakit, atau guru mengajukan koreksi status absensi yang salah.
2. Pengajuan masuk ke tabel `attendance_requests` dengan status `PENDING`, berisi `requested_status`, alasan, dan bukti pendukung (`evidence_path`).
3. Guru (atau admin) meninjau pengajuan melalui pencarian bertingkat di atas untuk memastikan permintaan memang terkait siswa dan sesi yang benar.
4. Guru menyetujui atau menolak. Bila disetujui, sistem menulis/memperbarui record absensi terkait dengan status `IZIN` atau `SAKIT`, mencatat `reviewed_by` dan `reviewed_at`.
5. Keputusan tersimpan sebagai jejak audit (`reviewed_by`, `reviewed_at`) agar perubahan status dapat dipertanggungjawabkan.

#### 1.6.2 Halaman Rekap

**Alur guru/pihak sekolah mengakses rekap (langkah per langkah):**

1. Guru membuka halaman rekap absensi.
2. Guru menentukan konteks data lewat filter bertingkat: jenjang pendidikan, kelas, mata pelajaran (opsional), dan periode tanggal, serta siswa tertentu bila diperlukan.
3. Sistem menarik `attendance_sessions` pada rentang tanggal yang dipilih, lalu mengumpulkan `attendance_records` dari sesi-sesi tersebut.
4. Untuk setiap entri, sistem menampilkan status kehadiran (`HADIR`, `TERLAMBAT`, `TIDAK_HADIR`, `IZIN`, `SAKIT`) beserta waktu absen (`scanned_at`).
5. Sistem menampilkan hasil verifikasi lokasi tiap entri: `SESUAI`, `DI_LUAR_RADIUS`, atau `TIDAK_TERVERIFIKASI`, beserta `distance_meters`, sehingga guru dapat mengenali anomali.
6. Guru meninjau ringkasan (jumlah hadir, terlambat, tidak hadir, izin, sakit) serta menelusuri detail per siswa.
7. Bila ditemukan ketidaksesuaian, guru dapat membuka pengajuan koreksi (alur persetujuan pada 1.6.1) atau mengekspor data rekap untuk pelaporan.

Halaman rekap dijelaskan lebih rinci pada Bagian 6.

---

## 2. Rancangan Skema Basis Data

Bagian ini memuat skema entitas User, Kelas, Absensi, dan tabel pendukung yang **sudah terimplementasi** di migrasi dan diverifikasi pada PostgreSQL 18 serta SQLite. Detail DDL referensi ada di [`DATABASE.md`](DATABASE.md) dan [`QUERY.sql`](QUERY.sql).

Konvensi dasar: primary key domain memakai `BIGINT` identity, waktu memakai `TIMESTAMPTZ(6)`, nama objek `snake_case`, dan nilai terbatas disimpan sebagai `VARCHAR(20)` dengan CHECK constraint (PostgreSQL) atau trigger (SQLite).

### 2.1 `users`

| Atribut             | Tipe Data       | Kunci  | Nullable | Alasan Pemilihan Tipe Data | Batasan Integritas                             |
| ------------------- | --------------- | ------ | -------- | -------------------------- | ---------------------------------------------- |
| `id`                | BIGINT identity | PK     | Tidak    | Cukup besar dan konsisten  | Auto increment                                 |
| `name`              | VARCHAR(255)    | —      | Tidak    | —                          | Wajib diisi                                    |
| `email`             | VARCHAR(255)    | Unique | Tidak    | Identitas login (Fortify)  | Unik                                           |
| `email_verified_at` | TIMESTAMPTZ(6)  | —      | Ya       | —                          | —                                              |
| `password`          | VARCHAR(255)    | —      | Tidak    | Menyimpan hash             | Selalu hash via cast `hashed`                  |
| `role`              | VARCHAR(20)     | Index  | Tidak    | Nilai terbatas terbagi     | CHECK `chk_users_role`; index `idx_users_role` |
| `phone`             | VARCHAR(30)     | —      | Ya       | —                          | —                                              |
| `birth_date`        | DATE            | —      | Ya       | Hanya butuh tanggal        | —                                              |
| `remember_token`    | VARCHAR(100)    | —      | Ya       | Token "remember me"        | —                                              |
| `created_at`        | TIMESTAMPTZ(6)  | —      | Tidak    | —                          | Default `CURRENT_TIMESTAMP`                    |
| `updated_at`        | TIMESTAMPTZ(6)  | —      | Tidak    | —                          | Dikelola Eloquent                              |

### 2.2 `student_profiles`

| Atribut              | Tipe Data   | Kunci                    | Nullable | Alasan Pemilihan Tipe Data                    | Batasan Integritas   |
| -------------------- | ----------- | ------------------------ | -------- | --------------------------------------------- | -------------------- |
| `user_id`            | BIGINT      | PK, FK `users.id`        | Tidak    | Satu akun maksimal satu profil siswa          | `ON DELETE CASCADE`  |
| `student_number`     | VARCHAR(50) | Unique                   | Tidak    | Nomor induk siswa bisa mengandung huruf/angka | Unik                 |
| `education_level_id` | BIGINT      | FK `education_levels.id` | Tidak    | Menandai jenjang siswa                        | `ON DELETE RESTRICT` |

### 2.3 `teacher_profiles`

| Atribut   | Tipe Data | Kunci             | Nullable | Alasan Pemilihan Tipe Data  | Batasan Integritas  |
| --------- | --------- | ----------------- | -------- | --------------------------- | ------------------- |
| `user_id` | BIGINT    | PK, FK `users.id` | Tidak    | Menandai akun berperan guru | `ON DELETE CASCADE` |

### 2.4 `education_levels`

| Atribut | Tipe Data       | Kunci  | Nullable | Alasan Pemilihan Tipe Data | Batasan Integritas |
| ------- | --------------- | ------ | -------- | -------------------------- | ------------------ |
| `id`    | BIGINT identity | PK     | Tidak    | —                          | Auto increment     |
| `name`  | VARCHAR(100)    | Unique | Tidak    | Nama jenjang (SD/SMP/SMA)  | Unik               |

### 2.5 `classes`

| Atribut              | Tipe Data       | Kunci                    | Nullable | Alasan Pemilihan Tipe Data                                       | Batasan Integritas                                          |
| -------------------- | --------------- | ------------------------ | -------- | ---------------------------------------------------------------- | ----------------------------------------------------------- |
| `id`                 | BIGINT identity | PK                       | Tidak    | —                                                                | Auto increment                                              |
| `name`               | VARCHAR(100)    | —                        | Tidak    | Nama kelas (mis. "Kelas 10 MIPA 1")                              | Unik per jenjang                                            |
| `education_level_id` | BIGINT          | FK `education_levels.id` | Tidak    | Kelas selalu milik satu jenjang                                  | `ON DELETE RESTRICT`; unique (`education_level_id`, `name`) |
| `latitude`           | NUMERIC(10,7)   | —                        | Ya       | Presisi 7 desimal setara akurasi GPS; titik pusat geofence kelas | —                                                           |
| `longitude`          | NUMERIC(10,7)   | —                        | Ya       | Sama seperti latitude                                            | —                                                           |
| `radius_meters`      | INTEGER         | —                        | Ya       | Radius toleransi geofence dalam meter                            | Default `50`                                                |

### 2.6 `subjects`

| Atribut | Tipe Data       | Kunci  | Nullable | Alasan Pemilihan Tipe Data | Batasan Integritas |
| ------- | --------------- | ------ | -------- | -------------------------- | ------------------ |
| `id`    | BIGINT identity | PK     | Tidak    | —                          | Auto increment     |
| `name`  | VARCHAR(150)    | Unique | Tidak    | Nama mata pelajaran        | Unik               |

### 2.7 `class_students` (pivot Siswa–Kelas)

| Atribut         | Tipe Data       | Kunci           | Nullable | Alasan Pemilihan Tipe Data                                     | Batasan Integritas              |
| --------------- | --------------- | --------------- | -------- | -------------------------------------------------------------- | ------------------------------- |
| `id`            | BIGINT identity | PK              | Tidak    | Memudahkan referensi pivot                                     | Auto increment                  |
| `class_id`      | BIGINT          | FK `classes.id` | Tidak    | Kelas penempatan                                               | `ON DELETE RESTRICT`            |
| `student_id`    | BIGINT          | FK `users.id`   | Tidak    | Siswa yang ditempatkan                                         | `ON DELETE RESTRICT`            |
| `is_active`     | BOOLEAN         | —               | Tidak    | Menandai penempatan aktif vs historis                          | Default `TRUE`                  |
| `academic_year` | VARCHAR(9)      | —               | Ya       | Tahun ajaran (mis. "2026/2027") agar riwayat antar tahun jelas | Bagian partial unique keaktifan |

Partial unique index `uq_class_students_active_student` pada `(student_id, academic_year) WHERE is_active` menjamin satu penempatan aktif per tahun ajaran.

### 2.8 `teacher_assignments` (pivot Guru–Kelas–Mata Pelajaran)

| Atribut      | Tipe Data       | Kunci            | Nullable | Alasan Pemilihan Tipe Data  | Batasan Integritas                                                                  |
| ------------ | --------------- | ---------------- | -------- | --------------------------- | ----------------------------------------------------------------------------------- |
| `id`         | BIGINT identity | PK               | Tidak    | Dirujuk sesi absensi        | Auto increment; unique `(id, class_id)` untuk FK komposit                           |
| `teacher_id` | BIGINT          | FK `users.id`    | Tidak    | Guru pengampu               | `ON DELETE RESTRICT`                                                                |
| `class_id`   | BIGINT          | FK `classes.id`  | Tidak    | Kelas yang diampu           | `ON DELETE RESTRICT`                                                                |
| `subject_id` | BIGINT          | FK `subjects.id` | Tidak    | Mata pelajaran yang diampu  | `ON DELETE RESTRICT`                                                                |
| `is_active`  | BOOLEAN         | —                | Tidak    | Penugasan aktif vs historis | Default `TRUE`; partial unique `(teacher_id, class_id, subject_id) WHERE is_active` |

### 2.9 `attendance_requests` (Tabel Pendukung Alur Persetujuan)

Tabel penyimpanan pengajuan izin/sakit/koreksi pada alur 1.6.1.

| Atribut            | Tipe Data       | Kunci                       | Nullable | Alasan Pemilihan Tipe Data                                | Batasan Integritas                                        |
| ------------------ | --------------- | --------------------------- | -------- | --------------------------------------------------------- | --------------------------------------------------------- |
| `id`               | BIGINT identity | PK                          | Tidak    | —                                                         | Auto increment                                            |
| `session_id`       | BIGINT          | FK `attendance_sessions.id` | Tidak    | Sesi yang dikoreksi                                       | `ON DELETE CASCADE`                                       |
| `student_id`       | BIGINT          | FK `users.id`               | Tidak    | Siswa pengaju                                             | `ON DELETE CASCADE`                                       |
| `requested_status` | VARCHAR(20)     | —                           | Tidak    | Status yang diajukan (`IZIN`/`SAKIT`/`HADIR`/`TERLAMBAT`) | CHECK `chk_attendance_requests_req_status`                |
| `reason`           | TEXT            | —                           | Ya       | Alasan pengajuan                                          | —                                                         |
| `evidence_path`    | VARCHAR(255)    | —                           | Ya       | Path bukti pendukung                                      | —                                                         |
| `status`           | VARCHAR(20)     | —                           | Tidak    | `PENDING`/`APPROVED`/`REJECTED`                           | Default `PENDING`; CHECK `chk_attendance_requests_status` |
| `reviewed_by`      | BIGINT          | FK `users.id`               | Ya       | Guru/admin peninjau                                       | `ON DELETE SET NULL`                                      |
| `reviewed_at`      | TIMESTAMPTZ(6)  | —                           | Ya       | Waktu keputusan                                           | —                                                         |
| `created_at`       | TIMESTAMPTZ(6)  | —                           | Tidak    | —                                                         | Default `CURRENT_TIMESTAMP`                               |
| `updated_at`       | TIMESTAMPTZ(6)  | —                           | Tidak    | —                                                         | Dikelola Eloquent                                         |

Index pendukung: `idx_attendance_requests_session`, `idx_attendance_requests_student`, `idx_attendance_requests_status`.

### 2.10 `school_settings`

Penyimpanan identitas dan geofence global sekolah; dipakai saat kelas tidak mendefinisikan geofence sendiri.

| Atribut         | Tipe Data       | Kunci | Nullable | Alasan Pemilihan Tipe Data        | Batasan Integritas |
| --------------- | --------------- | ----- | -------- | --------------------------------- | ------------------ |
| `id`            | BIGINT identity | PK    | Tidak    | —                                 | Auto increment     |
| `name`          | VARCHAR(150)    | —     | Tidak    | Nama sekolah                      | Wajib diisi        |
| `latitude`      | NUMERIC(10,7)   | —     | Tidak    | Pusat koordinat sekolah           | —                  |
| `longitude`     | NUMERIC(10,7)   | —     | Tidak    | Pusat koordinat sekolah           | —                  |
| `radius_meters` | INTEGER         | —     | Tidak    | Nilai radius total (bagian bulat) | Default `50`       |
| `created_at`    | TIMESTAMPTZ(6)  | —     | Ya       | —                                 | —                  |
| `updated_at`    | TIMESTAMPTZ(6)  | —     | Ya       | —                                 | —                  |

### 2.11 `attendance_sessions` (Sesi Absensi)

| Atribut         | Tipe Data       | Kunci           | Nullable | Alasan Pemilihan Tipe Data                          | Batasan Integritas                                                    |
| --------------- | --------------- | --------------- | -------- | --------------------------------------------------- | --------------------------------------------------------------------- |
| `id`            | BIGINT identity | PK              | Tidak    | Dirujuk record absensi                              | Auto increment                                                        |
| `assignment_id` | BIGINT          | FK komposit     | Tidak    | Menentukan guru, kelas, mapel sesi                  | FK `(assignment_id, class_id)` → `teacher_assignments (id, class_id)` |
| `class_id`      | BIGINT          | FK `classes.id` | Tidak    | Kelas sesi                                          | `ON DELETE RESTRICT`; konsisten dengan assignment                     |
| `session_date`  | DATE            | —               | Tidak    | Tanggal kalender sesi (timezone sekolah), tanpa jam | Bagian unique `(assignment_id, session_date, start_at, end_at)`       |
| `start_at`      | TIMESTAMPTZ(6)  | —               | Tidak    | Instan mulai sesi                                   | Harus lebih awal dari `end_at`                                        |
| `end_at`        | TIMESTAMPTZ(6)  | —               | Tidak    | Instan selesai sesi                                 | Check `chk_attendance_sessions_time` (`end_at > start_at`)            |
| `qr_payload`    | VARCHAR(255)    | Unique          | Tidak    | Token payload QR sesi                               | Unik                                                                  |
| `created_by`    | BIGINT          | FK `users.id`   | Tidak    | Guru pembuat sesi                                   | `ON DELETE RESTRICT`                                                  |
| `latitude`      | NUMERIC(10,7)   | —               | Ya       | Titik lokasi sesi saat dibuat                       | —                                                                     |
| `longitude`     | NUMERIC(10,7)   | —               | Ya       | Sama seperti latitude                               | —                                                                     |
| `created_at`    | TIMESTAMPTZ(6)  | —               | Tidak    | —                                                   | Default `CURRENT_TIMESTAMP`                                           |
| `updated_at`    | TIMESTAMPTZ(6)  | —               | Tidak    | —                                                   | Dikelola Eloquent                                                     |

### 2.12 `attendance_records` (Absensi / Hasil Pemindaian)

| Atribut           | Tipe Data       | Kunci                       | Nullable | Alasan Pemilihan Tipe Data                          | Batasan Integritas                                                                       |
| ----------------- | --------------- | --------------------------- | -------- | --------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `id`              | BIGINT identity | PK                          | Tidak    | —                                                   | Auto increment                                                                           |
| `session_id`      | BIGINT          | FK `attendance_sessions.id` | Tidak    | Sesi yang diikuti                                   | `ON DELETE RESTRICT`; unique `(session_id, student_id)`                                  |
| `student_id`      | BIGINT          | FK `users.id`               | Tidak    | Siswa yang hadir                                    | `ON DELETE RESTRICT`                                                                     |
| `scanned_at`      | TIMESTAMPTZ(6)  | —                           | Tidak    | Waktu absen dari server                             | Wajib diisi                                                                              |
| `status`          | VARCHAR(20)     | —                           | Tidak    | Nilai terbatas                                      | CHECK `chk_attendance_records_status` (`HADIR`/`TERLAMBAT`/`TIDAK_HADIR`/`IZIN`/`SAKIT`) |
| `late_minutes`    | INTEGER         | —                           | Ya       | Selisih menit keterlambatan                         | Check `late_minutes IS NULL OR late_minutes >= 0`                                        |
| `latitude`        | NUMERIC(10,7)   | —                           | Ya       | Koordinat siswa saat memindai                       | —                                                                                        |
| `longitude`       | NUMERIC(10,7)   | —                           | Ya       | Koordinat siswa saat memindai                       | —                                                                                        |
| `distance_meters` | INTEGER         | —                           | Ya       | Jarak hasil hitung ke pusat lokasi                  | —                                                                                        |
| `location_status` | VARCHAR(20)     | —                           | Ya       | `SESUAI` / `DI_LUAR_RADIUS` / `TIDAK_TERVERIFIKASI` | Nilai terbatas (validasi Eloquent)                                                       |
| `created_at`      | TIMESTAMPTZ(6)  | —                           | Tidak    | —                                                   | Default `CURRENT_TIMESTAMP`                                                              |
| `updated_at`      | TIMESTAMPTZ(6)  | —                           | Tidak    | —                                                   | Dikelola Eloquent                                                                        |

### 2.13 Diagram ERD

```mermaid
erDiagram
    users ||--o| student_profiles : "punya"
    users ||--o| teacher_profiles : "punya"
    education_levels ||--o{ student_profiles : "menaungi"
    education_levels ||--o{ classes : "menaungi"
    users ||--o{ class_students : "sebagai siswa"
    classes ||--o{ class_students : "berisi"
    users ||--o{ teacher_assignments : "sebagai guru"
    classes ||--o{ teacher_assignments : "diampu"
    subjects ||--o{ teacher_assignments : "diajarkan"
    teacher_assignments ||--o{ attendance_sessions : "menghasilkan"
    classes ||--o{ attendance_sessions : "berlangsung di"
    users ||--o{ attendance_sessions : "dibuat oleh"
    attendance_sessions ||--o{ attendance_records : "memiliki"
    users ||--o{ attendance_records : "milik siswa"
    attendance_sessions ||--o{ attendance_requests : "diajukan koreksi"
    users ||--o{ attendance_requests : "pengaju"
    users ||--o{ attendance_requests : "peninjau"

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        varchar role
        varchar phone
        date birth_date
        timestamptz created_at
        timestamptz updated_at
    }
    student_profiles {
        bigint user_id PK
        varchar student_number UK
        bigint education_level_id FK
    }
    teacher_profiles {
        bigint user_id PK
    }
    education_levels {
        bigint id PK
        varchar name UK
    }
    classes {
        bigint id PK
        varchar name
        bigint education_level_id FK
        numeric latitude
        numeric longitude
        int radius_meters
    }
    subjects {
        bigint id PK
        varchar name UK
    }
    class_students {
        bigint id PK
        bigint class_id FK
        bigint student_id FK
        boolean is_active
        varchar academic_year
    }
    teacher_assignments {
        bigint id PK
        bigint teacher_id FK
        bigint class_id FK
        bigint subject_id FK
        boolean is_active
    }
    attendance_sessions {
        bigint id PK
        bigint assignment_id FK
        bigint class_id FK
        date session_date
        timestamptz start_at
        timestamptz end_at
        varchar qr_payload UK
        bigint created_by FK
        numeric latitude
        numeric longitude
    }
    attendance_records {
        bigint id PK
        bigint session_id FK
        bigint student_id FK
        timestamptz scanned_at
        varchar status
        int late_minutes
        numeric latitude
        numeric longitude
        int distance_meters
        varchar location_status
    }
    attendance_requests {
        bigint id PK
        bigint session_id FK
        bigint student_id FK
        varchar requested_status
        text reason
        varchar evidence_path
        varchar status
        bigint reviewed_by FK
        timestamptz reviewed_at
    }
    school_settings {
        bigint id PK
        varchar name
        numeric latitude
        numeric longitude
        int radius_meters
    }
```

Catatan: seluruh kolom pada diagram sudah terimplementasi di migrasi. Diagram DBML yang lebih detail (index, constraint, FK `ON DELETE`) tersedia di [`schema.dbml`](schema.dbml).

---

## 3. Migrasi & Seeder (Verifikasi)

> Bagian ini bersifat verifikasi. Migrasi dan seeder sudah berjalan dan diverifikasi.

### 3.1 Perintah Migrasi

Perintah standar dari kondisi bersih:

```bash
php artisan migrate:fresh --seed
```

Bila dijalankan bertahap:

```bash
php artisan migrate:fresh
php artisan db:seed
```

### 3.2 Hasil Verifikasi (PostgreSQL 18 + SQLite)

Verifikasi akhir dijalankan pada **PostgreSQL 18** (database lokal `siabsen`, service Windows `postgresql-x64-18`) dan **SQLite in-memory**:

- `migrate:fresh --seed`: **sukses** di kedua driver (17 migrasi running + seeder).
- Probe constraint/index/FK pada PostgreSQL: seluruhnya lulus —
    - CHECK `chk_users_role`, `chk_attendance_records_status`, `chk_attendance_records_late_minutes`, `chk_attendance_sessions_time`, `chk_attendance_requests_req_status`, `chk_attendance_requests_status` menolak nilai invalid (SQLSTATE 23514).
    - Unique `uq_class_students_active_student`, `uq_teacher_assignments_active_assignment`, `uq_attendance_records_session_student`, `uq_attendance_sessions_qr_payload` menolak duplikasi (SQLSTATE 23505).
    - FK `RESTRICT`, `CASCADE` (profile, `attendance_requests`), dan `SET NULL` (`reviewed_by`) berperilaku sesuai rancangan.
- Suite `php artisan test` **hijau** pada kedua driver (68 tests, 60 passed, 8 skipped, 234 assertions).

**Catatan lingkungan lokal (Windows + Herd Lite).** Ekstensi `pdo_pgsql` tidak tersedia pada instalasi PHP Herd Lite (`could not find driver`). Verifikasi PostgreSQL memakai PHP standalone `C:\Program Files\php\php-8.5.11-Win32-vs17-x64\php.exe` yang sudah memiliki `pdo_pgsql` dan `pgsql`, dengan flag `-d extension_dir` untuk mengaktifkan `fileinfo`, `pdo_sqlite`, dan `sqlite3`. Di CI, ekstensi diaktifkan melalui `setup-php` (`extensions: pdo_pgsql, pgsql`) dan job `pgsql` berjalan pada service `postgres:18`.

### 3.3 Kecukupan Data Contoh

`DatabaseSeeder` menghasilkan data yang memadai untuk demo:

| Data                   | Jumlah                                         | Sumber                                 |
| ---------------------- | ---------------------------------------------- | -------------------------------------- |
| Pengaturan sekolah     | 1 (`SMP Negeri 1 Jakarta`, radius 50 m)        | `school_settings`                      |
| Jenjang pendidikan     | 3 (SD, SMP, SMA)                               | `education_levels`                     |
| Kelas                  | 12 (4 per jenjang)                             | `classes`                              |
| Mata pelajaran         | 8                                              | `subjects`                             |
| Admin                  | 1 (`admin@siabsen.com`)                        | `users`                                |
| Guru                   | 10 (`guru1`–`guru10`)                          | `users`                                |
| Siswa                  | 120 (10 per kelas)                             | `users`                                |
| Profil guru / siswa    | 10 / 120                                       | `teacher_profiles`, `student_profiles` |
| Penempatan kelas aktif | 120 (tahun ajaran `2026/2027`)                 | `class_students`                       |
| Penugasan guru         | 24 (2 per kelas)                               | `teacher_assignments`                  |
| Sesi absensi           | 120 (5 per penugasan, selang 2 hari)           | `attendance_sessions`                  |
| Riwayat absensi        | ~960 (siswa yang tidak memindai tidak dicatat) | `attendance_records`                   |

Seluruh akun memakai password `password`. Setiap record absensi menyertakan koordinat, `distance_meters`, dan `location_status` (`~80% SESUAI`), sehingga halaman rekap dapat menampilkan informasi verifikasi lokasi. Data `TIDAK_HADIR` **tidak** disimpan sebagai record—siswa yang tidak memindai dihitung `TIDAK_HADIR` saat pembacaan (konsisten dengan aturan domain).

### 3.4 Catatan Perbaikan (Status: Selesai)

| #   | Temuan di rancangan awal                                                           | Keputusan / Perbaikan yang Diambil                                                                                                                            |
| --- | ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Seeder mengisi `scanned_at = null` untuk siswa tidak hadir tetapi kolom `NOT NULL` | Siswa tidak hadir tidak membuat record; `TIDAK_HADIR` dihitung saat pembacaan                                                                                 |
| 2   | `TIDAK_HADIR` disimpan langsung ke `attendance_records`                            | Dihapus dari alur scan; dihitung on-the-fly di service                                                                                                        |
| 3   | Tidak ada kolom verifikasi lokasi                                                  | Ditambahkan geofence: `classes` (lat/lng/radius), `attendance_sessions` (lat/lng), `attendance_records` (lat/lng/distance/location_status), `school_settings` |
| 4   | Tanggal sesi seeder acak, rawan duplikat unique                                    | Sesi dibuat berurutan `now() - 2*n` hari per assignment sehingga unik per constraint                                                                          |
| 5   | `role`/`status` memakai native enum pada DDL                                       | Keputusan: `VARCHAR(20)` + CHECK (PostgreSQL) dan trigger setara (SQLite) agar perilaku lintas driver identik                                                 |
| 6   | Belum ada tabel pengajuan/persetujuan                                              | `attendance_requests` dibuat (FK CASCADE untuk penulis, SET NULL untuk peninjau, CHECK status)                                                                |
| 7   | `User` belum punya enum cast, relasi, atau helper                                  | Enum `App\Enums\*`, relasi Eloquent, dan policy/otorisasi (Fortify) sudah diterapkan                                                                          |
| 8   | Check constraint `end_at > start_at`, `late_minutes >= 0` masih dikomentari        | Diaktifkan via migrasi `add_constraints_and_enum_checks` dan diverifikasi di PostgreSQL                                                                       |
| 9   | Round-trip `TIMESTAMPTZ` bergeser timezone server                                  | Koneksi `pgsql` menetapkan timezone sesi `DB_TIMEZONE=UTC` di `config/database.php`; `.env.example` diperbarui                                                |
| 10  | 6 test gagal di PostgreSQL sebelum fix timezone                                    | Setelah `DB_TIMEZONE=UTC`, suite hijau identik di SQLite dan PostgreSQL (CI matriks dua driver)                                                               |

---

## 4. Pembagian Peran Tim

Data pembagian peran dipertahankan apa adanya. Karena beberapa anggota memegang lebih dari satu peran, peran utama tiap anggota ditandai tebal agar selaras dengan prinsip satu peran utama per orang; peran lainnya menjadi pendukung.

| Peran                   | Anggota                                                |
| ----------------------- | ------------------------------------------------------ |
| Basis Data & Model      | **Arqan**, **Riffa**, Dipa, Rifki                      |
| Antarmuka & Komponen    | **Dipa**, **Rifki**, Yostiar                           |
| Autentikasi & Otorisasi | **Arqan**, Yostiar                                     |
| Uji Kebergunaan         | **Arqan**, **Dipa**, **Riffa**, **Rifki**, **Yostiar** |
| Dokumentasi             | **Yostiar**, Rifki                                     |

**Ringkasan peran utama per anggota:**

| Anggota                 | Peran Utama          | Peran Pendukung                                                |
| ----------------------- | -------------------- | -------------------------------------------------------------- |
| Arqan Purusa Eryan      | Basis Data & Model   | Autentikasi & Otorisasi, Uji Kebergunaan                       |
| I Made Dipa Rama Artike | Antarmuka & Komponen | Basis Data & Model, Uji Kebergunaan                            |
| Riffa Yudika Perdana    | Basis Data & Model   | Uji Kebergunaan                                                |
| Rifki Yudika Perdana    | Antarmuka & Komponen | Basis Data & Model, Dokumentasi, Uji Kebergunaan               |
| Yostiar Aminudin        | Dokumentasi          | Antarmuka & Komponen, Autentikasi & Otorisasi, Uji Kebergunaan |

Seluruh anggota terlibat pada Uji Kebergunaan sebagai peran bersama.

---

## 5. Calon Pengguna untuk Uji Kebergunaan (Pertemuan 13)

Calon peserta berikut sudah menyatakan bersedia. Nama asli akan dikonfirmasi sebelum pertemuan 13.

| Sebutan                                | Nama Asli (isi sebelum pertemuan 13) | Tanggal Konfirmasi |
| -------------------------------------- | ------------------------------------ | ------------------ |
| Bapak Guru (perwakilan guru laki-laki) | `[ISI_NAMA]`                         |                    |
| Ibu Guru (perwakilan guru perempuan)   | `[ISI_NAMA]`                         |                    |
| Perwakilan Siswa Kelas X               | `[ISI_NAMA]`                         |                    |
| Perwakilan Siswa Kelas XI              | `[ISI_NAMA]`                         |                    |
| Perwakilan Siswa Kelas XII             | `[ISI_NAMA]`                         |                    |

Rencana pelaksanaan: guru mencoba membuat sesi absensi dan membuka halaman rekap, sedangkan siswa mencoba memindai QR dan melihat riwayat absen. Masukan dari kelima peserta menjadi bahan penyempurnaan antarmuka sebelum evaluasi akhir.

---

## 6. Isi Halaman Rekap

Halaman rekap adalah pusat pemantauan kehadiran. Komponen yang akan dibangun:

### 6.1 Tabel Riwayat dan Rekapitulasi Status

- Setiap baris merepresentasikan satu entri absensi: siswa, sesi (tanggal, mata pelajaran, kelas), dan statusnya.
- Status yang ditampilkan: `HADIR`, `TERLAMBAT`, `TIDAK_HADIR`, `IZIN`, dan `SAKIT`.
- Untuk `TERLAMBAT`, ditampilkan `late_minutes` (menit keterlambatan).
- Tersedia ringkasan agregat: jumlah hadir, terlambat, tidak hadir, izin, dan sakit, serta persentase kehadiran per siswa atau per kelas pada periode terpilih.
- Waktu absen (`scanned_at`) ditampilkan dalam zona waktu sekolah.

### 6.2 Informasi Kecocokan Lokasi Verifikasi

- Setiap entri menampilkan status lokasi: **"sesuai lokasi kelas"** (`SESUAI`) atau **"di luar radius"** (`DI_LUAR_RADIUS`), dengan opsi `TIDAK_TERVERIFIKASI` bila data lokasi tidak tersedia.
- Ditampilkan pula `distance_meters` (jarak pemindaian ke pusat lokasi kelas) dan, bila perlu, tautan peta ke koordinat pemindaian.
- Informasi ini memungkinkan guru mengenali entri mencurigakan, misalnya siswa yang statusnya hadir tetapi memindai dari luar radius kelas.
- Data lokasi sudah tersedia di `attendance_records`; penyajiannya tinggal memetakan kolom.

### 6.3 Filter yang Tersedia

- **Per jenjang pendidikan:** SD, SMP, SMA.
- **Per kelas:** mengikuti jenjang yang dipilih (filter bertingkat).
- **Per mata pelajaran** (opsional), mengikuti penugasan guru.
- **Per periode/tanggal:** rentang tanggal atau hari tertentu, memanfaatkan `attendance_sessions.session_date`.
- **Per siswa:** untuk menampilkan riwayat satu siswa.
- **Per status kehadiran:** memfilter hanya entri dengan status tertentu.

### 6.4 Hak Akses

- **Guru:** mengakses rekap untuk kelas dan mata pelajaran yang diampu sesuai `teacher_assignments` aktif.
- **Admin/pihak sekolah:** mengakses seluruh rekap lintas jenjang dan kelas.
- **Siswa:** tidak mengakses halaman rekap; siswa hanya melihat riwayat absennya sendiri pada halaman terpisah.
- Otorisasi ditegakkan di sisi server (policy/controller) berdasarkan `role` dan relasi penugasan, bukan hanya disembunyikan di antarmuka.

---

## 7. Keputusan Arsitektur

### 7.1 Arsitektur yang Dipilih

**Monolit Inertia** (Laravel 13 + Inertia.js 3 + React 19 + TypeScript). Keputusan ini sesuai dengan stack yang sudah ada di repositori:

- `composer.json`: `laravel/framework ^13.17`, `inertiajs/inertia-laravel ^3.0`, `laravel/fortify`, `laravel/wayfinder`.
- `package.json`: `@inertiajs/react ^3.0.0`, `react ^19.2.0`, `vite ^8.0.0`.

Inertia menyatukan backend Laravel dan frontend React dalam satu codebase dan satu proses deploy, tanpa membangun API terpisah.

### 7.2 Dua Alasan Teknis

1. **Kebutuhan antarmuka interaktif (scan QR dan pemantauan) terpenuhi tanpa membangun API terpisah.** Pemindaian QR memerlukan akses kamera, umpan balik instan, dan validasi klien, sementara pemantauan kehadiran menuntut UI yang responsif. Inertia + React memberi komponen interaktif penuh dengan routing, validasi, dan otentikasi server yang sudah terintegrasi (Fortify), sehingga tim tidak perlu mendefinisikan dan memelihara kontrak REST serta autentikasi token terpisah.
2. **Kecepatan pengembangan tim beranggotakan 5 orang.** Dengan satu codebase dan tanpa duplikasi lapisan API, anggota dapat bekerja lintas backend dan frontend memakai route Laravel serta generate Wayfinder. Ini mengurangi overhead koordinasi kontrak API, mempercepat iterasi, dan memanfaatkan kembali komponen UI yang sudah ada (mis. shadcn/ui dan Radix pada `package.json`).

### 7.3 Satu Hal yang Dikorbankan

**Ketiadaan API publik terpisah menyulitkan pemakaian klien non-web (mobile native) dan membatasi SSR/SEO.** Karena halaman dirender sebagai komponen Inertia yang bergantung pada sesi Laravel, aplikasi mobile native atau integrasi pihak ketiga tidak dapat langsung dikonsumsi tanpa menambah lapisan API di kemudian hari. Konsekuensi ini dapat diterima untuk skala proyek sekolah saat ini karena sasaran utama adalah satu aplikasi web dengan kamera browser (bukan aplikasi native) dan halaman rekap yang bersifat internal—bukan konten publik yang butuh SEO—sehingga nilai kecepatan pengembangan lebih besar daripada kebutuhan decoupling frontend-backend.
