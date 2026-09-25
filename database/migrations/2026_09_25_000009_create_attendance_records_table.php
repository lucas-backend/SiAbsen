<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('attendance_sessions')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->foreignId('student_id')
                ->constrained('users')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->timestampTz('scanned_at', 6);
            $table->string('status', 20);
            $table->unsignedInteger('late_minutes')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->unique(['session_id', 'student_id'], 'uq_attendance_records_session_student');
            // $table->check(
            //     "status IN ('HADIR', 'TERLAMBAT', 'TIDAK_HADIR')",
            //     'chk_attendance_records_status'
            // );
            // $table->check(
            //     'late_minutes IS NULL OR late_minutes >= 0',
            //     'chk_attendance_records_late_minutes'
            // );
            $table->index(['student_id', 'scanned_at'], 'idx_attendance_records_student_scanned');
            $table->index(['session_id', 'status'], 'idx_attendance_records_session_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
