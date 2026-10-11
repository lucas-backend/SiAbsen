<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate partial unique index for class_students with academic_year
        // Avoid NULL issues - PostgreSQL treats NULLs as distinct, but requirement says 'hindari pelanggaran keanggotaan aktif'
        DB::statement('DROP INDEX IF EXISTS uq_class_students_active_student');
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX uq_class_students_active_student ON class_students (student_id, academic_year) WHERE is_active');
        } else {
            // SQLite - recreate trigger-based approach or just index structure
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_class_students_active_student ON class_students (student_id, academic_year) WHERE is_active');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_class_students_active_student');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_class_students_active_student ON class_students (student_id) WHERE is_active');
    }
};
