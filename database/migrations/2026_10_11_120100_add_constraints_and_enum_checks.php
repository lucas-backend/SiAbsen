<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('STUDENT','TEACHER','ADMIN'))");
            DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT chk_attendance_sessions_time CHECK (end_at > start_at)");
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_status CHECK (status IN ('HADIR','TERLAMBAT','TIDAK_HADIR','IZIN','SAKIT'))");
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_late_minutes CHECK (late_minutes IS NULL OR late_minutes >= 0)");
            DB::statement("ALTER TABLE attendance_requests ADD CONSTRAINT chk_attendance_requests_req_status CHECK (requested_status IN ('IZIN','SAKIT','HADIR','TERLAMBAT'))");
            DB::statement("ALTER TABLE attendance_requests ADD CONSTRAINT chk_attendance_requests_status CHECK (status IN ('PENDING','APPROVED','REJECTED'))");
        } else {
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_users_role_ins BEFORE INSERT ON users FOR EACH ROW BEGIN SELECT CASE WHEN NEW.role NOT IN ('STUDENT','TEACHER','ADMIN') THEN RAISE(ABORT,'invalid role') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_users_role_upd BEFORE UPDATE ON users FOR EACH ROW BEGIN SELECT CASE WHEN NEW.role NOT IN ('STUDENT','TEACHER','ADMIN') THEN RAISE(ABORT,'invalid role') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_sessions_time_ins BEFORE INSERT ON attendance_sessions FOR EACH ROW BEGIN SELECT CASE WHEN datetime(NEW.end_at) <= datetime(NEW.start_at) THEN RAISE(ABORT,'end_at must be > start_at') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_sessions_time_upd BEFORE UPDATE ON attendance_sessions FOR EACH ROW BEGIN SELECT CASE WHEN datetime(NEW.end_at) <= datetime(NEW.start_at) THEN RAISE(ABORT,'end_at must be > start_at') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_records_status_ins BEFORE INSERT ON attendance_records FOR EACH ROW BEGIN SELECT CASE WHEN NEW.status NOT IN ('HADIR','TERLAMBAT','TIDAK_HADIR','IZIN','SAKIT') THEN RAISE(ABORT,'invalid status') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_records_status_upd BEFORE UPDATE ON attendance_records FOR EACH ROW BEGIN SELECT CASE WHEN NEW.status NOT IN ('HADIR','TERLAMBAT','TIDAK_HADIR','IZIN','SAKIT') THEN RAISE(ABORT,'invalid status') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_records_late_ins BEFORE INSERT ON attendance_records FOR EACH ROW BEGIN SELECT CASE WHEN NEW.late_minutes IS NOT NULL AND NEW.late_minutes < 0 THEN RAISE(ABORT,'late_minutes invalid') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_records_late_upd BEFORE UPDATE ON attendance_records FOR EACH ROW BEGIN SELECT CASE WHEN NEW.late_minutes IS NOT NULL AND NEW.late_minutes < 0 THEN RAISE(ABORT,'late_minutes invalid') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_req_reqstatus_ins BEFORE INSERT ON attendance_requests FOR EACH ROW BEGIN SELECT CASE WHEN NEW.requested_status NOT IN ('IZIN','SAKIT','HADIR','TERLAMBAT') THEN RAISE(ABORT,'invalid requested_status') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_req_reqstatus_upd BEFORE UPDATE ON attendance_requests FOR EACH ROW BEGIN SELECT CASE WHEN NEW.requested_status NOT IN ('IZIN','SAKIT','HADIR','TERLAMBAT') THEN RAISE(ABORT,'invalid requested_status') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_req_status_ins BEFORE INSERT ON attendance_requests FOR EACH ROW BEGIN SELECT CASE WHEN NEW.status NOT IN ('PENDING','APPROVED','REJECTED') THEN RAISE(ABORT,'invalid status') END; END");
            DB::statement("CREATE TRIGGER IF NOT EXISTS chk_att_req_status_upd BEFORE UPDATE ON attendance_requests FOR EACH ROW BEGIN SELECT CASE WHEN NEW.status NOT IN ('PENDING','APPROVED','REJECTED') THEN RAISE(ABORT,'invalid status') END; END");
        }
    }

    public function down(): void
    {
    }
};