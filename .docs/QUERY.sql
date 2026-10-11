-- ============================================================================
-- QUERY.sql - Skema referensi SiAbsen (PostgreSQL 13+)
-- ----------------------------------------------------------------------------
-- DDL referensi yang konsisten dengan hasil `migrate:fresh` pada PostgreSQL
-- (diverifikasi pada PostgreSQL 18 via pg_catalog/pg_dump). Semua tabel,
-- kolom, tipe, CHECK constraint, partial unique index, dan foreign key di
-- bawah ini sudah ada pada skema aktual setelah migrasi.
--
-- Nilai role/status memakai `VARCHAR(20)` + CHECK constraint agar kompatibel
-- lintas database; pada SQLite constraint setara diwujudkan sebagai trigger
-- (lihat migrasi `2026_10_11_120100_add_constraints_and_enum_checks`).
-- Waktu memakai `TIMESTAMPTZ(6)`.
-- ============================================================================

BEGIN;

SET search_path TO public, public;

-- ---------------------------------------------------------------------------
-- Tabel framework (Laravel)
-- ---------------------------------------------------------------------------

CREATE TABLE users (
  id BIGSERIAL NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMPTZ(6),
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL,
  phone VARCHAR(30),
  birth_date DATE,
  remember_token VARCHAR(100),
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  CONSTRAINT pk_users PRIMARY KEY (id),
  CONSTRAINT uq_users_email UNIQUE (email),
  CONSTRAINT chk_users_role CHECK (role IN ('STUDENT', 'TEACHER', 'ADMIN'))
);

CREATE INDEX idx_users_role ON users (role);

CREATE TABLE password_reset_tokens (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP(0),
  CONSTRAINT pk_password_reset_tokens PRIMARY KEY (email)
);

CREATE TABLE sessions (
  id VARCHAR(255) NOT NULL,
  user_id BIGINT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  payload TEXT NOT NULL,
  last_activity INTEGER NOT NULL,
  CONSTRAINT pk_sessions PRIMARY KEY (id)
);

CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
  key VARCHAR(255) NOT NULL,
  value TEXT NOT NULL,
  expiration BIGINT NOT NULL,
  CONSTRAINT pk_cache PRIMARY KEY (key)
);

CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
  key VARCHAR(255) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  expiration BIGINT NOT NULL,
  CONSTRAINT pk_cache_locks PRIMARY KEY (key)
);

CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE jobs (
  id BIGSERIAL NOT NULL,
  queue VARCHAR(255) NOT NULL,
  payload TEXT NOT NULL,
  attempts SMALLINT NOT NULL,
  reserved_at INTEGER,
  available_at INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  CONSTRAINT pk_jobs PRIMARY KEY (id)
);

CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
  id VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  total_jobs INTEGER NOT NULL,
  pending_jobs INTEGER NOT NULL,
  failed_jobs INTEGER NOT NULL,
  failed_job_ids TEXT NOT NULL,
  options TEXT,
  cancelled_at INTEGER,
  created_at INTEGER NOT NULL,
  finished_at INTEGER,
  CONSTRAINT pk_job_batches PRIMARY KEY (id)
);

CREATE TABLE failed_jobs (
  id BIGSERIAL NOT NULL,
  uuid VARCHAR(255) NOT NULL,
  connection VARCHAR(255) NOT NULL,
  queue VARCHAR(255) NOT NULL,
  payload TEXT NOT NULL,
  exception TEXT NOT NULL,
  failed_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_failed_jobs PRIMARY KEY (id),
  CONSTRAINT uq_failed_jobs_uuid UNIQUE (uuid)
);

CREATE INDEX failed_jobs_connection_queue_failed_at_index
  ON failed_jobs (connection, queue, failed_at);

-- ---------------------------------------------------------------------------
-- Domain: jenjang pendidikan
-- ---------------------------------------------------------------------------

CREATE TABLE education_levels (
  id BIGSERIAL NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  CONSTRAINT pk_education_levels PRIMARY KEY (id),
  CONSTRAINT uq_education_levels_name UNIQUE (name)
);

-- ---------------------------------------------------------------------------
-- Domain: profil siswa
-- ---------------------------------------------------------------------------

CREATE TABLE student_profiles (
  user_id BIGINT NOT NULL,
  student_number VARCHAR(50) NOT NULL,
  education_level_id BIGINT NOT NULL,
  CONSTRAINT pk_student_profiles PRIMARY KEY (user_id),
  CONSTRAINT uq_student_profiles_student_number UNIQUE (student_number),
  CONSTRAINT fk_student_profiles_user
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_student_profiles_education_level
    FOREIGN KEY (education_level_id)
    REFERENCES education_levels (id)
    ON DELETE RESTRICT
);

CREATE INDEX idx_student_profiles_education_level
  ON student_profiles (education_level_id);

-- ---------------------------------------------------------------------------
-- Domain: profil guru
-- ---------------------------------------------------------------------------

CREATE TABLE teacher_profiles (
  user_id BIGINT NOT NULL,
  CONSTRAINT pk_teacher_profiles PRIMARY KEY (user_id),
  CONSTRAINT fk_teacher_profiles_user
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- Domain: kelas + geofence
-- ---------------------------------------------------------------------------

CREATE TABLE classes (
  id BIGSERIAL NOT NULL,
  name VARCHAR(100) NOT NULL,
  education_level_id BIGINT NOT NULL,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  latitude NUMERIC(10,7),
  longitude NUMERIC(10,7),
  radius_meters INTEGER DEFAULT 50,
  CONSTRAINT pk_classes PRIMARY KEY (id),
  CONSTRAINT uq_classes_education_level_name UNIQUE (education_level_id, name),
  CONSTRAINT fk_classes_education_level
    FOREIGN KEY (education_level_id)
    REFERENCES education_levels (id)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------------
-- Domain: mata pelajaran
-- ---------------------------------------------------------------------------

CREATE TABLE subjects (
  id BIGSERIAL NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  CONSTRAINT pk_subjects PRIMARY KEY (id),
  CONSTRAINT uq_subjects_name UNIQUE (name)
);

-- ---------------------------------------------------------------------------
-- Domain: pivot siswa–kelas (penempatan)
-- ---------------------------------------------------------------------------

CREATE TABLE class_students (
  id BIGSERIAL NOT NULL,
  class_id BIGINT NOT NULL,
  student_id BIGINT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  academic_year VARCHAR(9),
  CONSTRAINT pk_class_students PRIMARY KEY (id),
  CONSTRAINT fk_class_students_class
    FOREIGN KEY (class_id)
    REFERENCES classes (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_class_students_student
    FOREIGN KEY (student_id)
    REFERENCES users (id)
    ON DELETE RESTRICT
);

-- Satu siswa hanya boleh punya SATU penempatan aktif per tahun ajaran.
CREATE UNIQUE INDEX uq_class_students_active_student
  ON class_students (student_id, academic_year)
  WHERE is_active;

CREATE INDEX idx_class_students_student_active
  ON class_students (student_id, is_active);

CREATE INDEX idx_class_students_class_active
  ON class_students (class_id, is_active);

-- ---------------------------------------------------------------------------
-- Domain: penugasan guru (guru–kelas–mapel)
-- ---------------------------------------------------------------------------

CREATE TABLE teacher_assignments (
  id BIGSERIAL NOT NULL,
  teacher_id BIGINT NOT NULL,
  class_id BIGINT NOT NULL,
  subject_id BIGINT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  CONSTRAINT pk_teacher_assignments PRIMARY KEY (id),
  CONSTRAINT uq_teacher_assignments_id_class UNIQUE (id, class_id),
  CONSTRAINT fk_teacher_assignments_teacher
    FOREIGN KEY (teacher_id)
    REFERENCES users (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_teacher_assignments_class
    FOREIGN KEY (class_id)
    REFERENCES classes (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_teacher_assignments_subject
    FOREIGN KEY (subject_id)
    REFERENCES subjects (id)
    ON DELETE RESTRICT
);

-- Satu kombinasi guru–kelas–mapel hanya boleh aktif sekali.
CREATE UNIQUE INDEX uq_teacher_assignments_active_assignment
  ON teacher_assignments (teacher_id, class_id, subject_id)
  WHERE is_active;

CREATE INDEX idx_teacher_assignments_teacher_active
  ON teacher_assignments (teacher_id, is_active);

CREATE INDEX idx_teacher_assignments_class_active
  ON teacher_assignments (class_id, is_active);

CREATE INDEX idx_teacher_assignments_subject_active
  ON teacher_assignments (subject_id, is_active);

-- ---------------------------------------------------------------------------
-- Domain: sesi absensi
-- ---------------------------------------------------------------------------

CREATE TABLE attendance_sessions (
  id BIGSERIAL NOT NULL,
  assignment_id BIGINT NOT NULL,
  class_id BIGINT NOT NULL,
  session_date DATE NOT NULL,
  start_at TIMESTAMPTZ(6) NOT NULL,
  end_at TIMESTAMPTZ(6) NOT NULL,
  qr_payload VARCHAR(255) NOT NULL,
  created_by BIGINT NOT NULL,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  latitude NUMERIC(10,7),
  longitude NUMERIC(10,7),
  CONSTRAINT pk_attendance_sessions PRIMARY KEY (id),
  CONSTRAINT uq_attendance_sessions_qr_payload UNIQUE (qr_payload),
  CONSTRAINT uq_attendance_sessions_assignment_date_time
    UNIQUE (assignment_id, session_date, start_at, end_at),
  CONSTRAINT chk_attendance_sessions_time CHECK (end_at > start_at),
  CONSTRAINT fk_attendance_sessions_assignment_class
    FOREIGN KEY (assignment_id, class_id)
    REFERENCES teacher_assignments (id, class_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_attendance_sessions_class
    FOREIGN KEY (class_id)
    REFERENCES classes (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_attendance_sessions_created_by
    FOREIGN KEY (created_by)
    REFERENCES users (id)
    ON DELETE RESTRICT
);

CREATE INDEX idx_attendance_sessions_assignment_time
  ON attendance_sessions (assignment_id, start_at, end_at);

CREATE INDEX idx_attendance_sessions_class_date
  ON attendance_sessions (class_id, session_date);

CREATE INDEX idx_attendance_sessions_created_by
  ON attendance_sessions (created_by);

-- ---------------------------------------------------------------------------
-- Domain: record absensi
-- ---------------------------------------------------------------------------

CREATE TABLE attendance_records (
  id BIGSERIAL NOT NULL,
  session_id BIGINT NOT NULL,
  student_id BIGINT NOT NULL,
  scanned_at TIMESTAMPTZ(6) NOT NULL,
  status VARCHAR(20) NOT NULL,
  late_minutes INTEGER,
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  latitude NUMERIC(10,7),
  longitude NUMERIC(10,7),
  distance_meters INTEGER,
  location_status VARCHAR(20),
  CONSTRAINT pk_attendance_records PRIMARY KEY (id),
  CONSTRAINT uq_attendance_records_session_student
    UNIQUE (session_id, student_id),
  CONSTRAINT chk_attendance_records_status
    CHECK (status IN ('HADIR', 'TERLAMBAT', 'TIDAK_HADIR', 'IZIN', 'SAKIT')),
  CONSTRAINT chk_attendance_records_late_minutes
    CHECK (late_minutes IS NULL OR late_minutes >= 0),
  CONSTRAINT fk_attendance_records_session
    FOREIGN KEY (session_id)
    REFERENCES attendance_sessions (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_attendance_records_student
    FOREIGN KEY (student_id)
    REFERENCES users (id)
    ON DELETE RESTRICT
);

CREATE INDEX idx_attendance_records_student_scanned
  ON attendance_records (student_id, scanned_at);

CREATE INDEX idx_attendance_records_session_status
  ON attendance_records (session_id, status);

-- ---------------------------------------------------------------------------
-- Domain: pengajuan izin/sakit (alur persetujuan)
-- ---------------------------------------------------------------------------

CREATE TABLE attendance_requests (
  id BIGSERIAL NOT NULL,
  session_id BIGINT NOT NULL,
  student_id BIGINT NOT NULL,
  requested_status VARCHAR(20) NOT NULL,
  reason TEXT,
  evidence_path VARCHAR(255),
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  reviewed_by BIGINT,
  reviewed_at TIMESTAMPTZ(6),
  created_at TIMESTAMPTZ(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ(6) NOT NULL,
  CONSTRAINT pk_attendance_requests PRIMARY KEY (id),
  CONSTRAINT chk_attendance_requests_req_status
    CHECK (requested_status IN ('IZIN', 'SAKIT', 'HADIR', 'TERLAMBAT')),
  CONSTRAINT chk_attendance_requests_status
    CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
  CONSTRAINT fk_attendance_requests_session
    FOREIGN KEY (session_id)
    REFERENCES attendance_sessions (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_attendance_requests_student
    FOREIGN KEY (student_id)
    REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_attendance_requests_reviewed_by
    FOREIGN KEY (reviewed_by)
    REFERENCES users (id)
    ON DELETE SET NULL
);

CREATE INDEX idx_attendance_requests_session
  ON attendance_requests (session_id);

CREATE INDEX idx_attendance_requests_student
  ON attendance_requests (student_id);

CREATE INDEX idx_attendance_requests_status
  ON attendance_requests (status);

-- ---------------------------------------------------------------------------
-- Domain: pengaturan/sekolah (geofence & identitas)
-- ---------------------------------------------------------------------------

CREATE TABLE school_settings (
  id BIGSERIAL NOT NULL,
  name VARCHAR(150) NOT NULL,
  latitude NUMERIC(10,7) NOT NULL,
  longitude NUMERIC(10,7) NOT NULL,
  radius_meters INTEGER NOT NULL DEFAULT 50,
  created_at TIMESTAMPTZ(6),
  updated_at TIMESTAMPTZ(6),
  CONSTRAINT pk_school_settings PRIMARY KEY (id)
);

COMMIT;