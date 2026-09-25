<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id');
            $table->foreignId('class_id');
            $table->date('session_date');
            $table->timestampTz('start_at', 6);
            $table->timestampTz('end_at', 6);
            $table->string('qr_payload', 255)->unique('uq_attendance_sessions_qr_payload');
            $table->foreignId('created_by')
                ->constrained('users')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->unique(
                ['assignment_id', 'session_date', 'start_at', 'end_at'],
                'uq_attendance_sessions_assignment_date_time'
            );
            $table->check('end_at > start_at', 'chk_attendance_sessions_time');
            $table->foreign(['assignment_id', 'class_id'], 'fk_attendance_sessions_assignment_class')
                ->references(['id', 'class_id'])
                ->on('teacher_assignments')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->foreign('class_id', 'fk_attendance_sessions_class')
                ->references('id')
                ->on('classes')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->index(['assignment_id', 'start_at', 'end_at'], 'idx_attendance_sessions_assignment_time');
            $table->index(['class_id', 'session_date'], 'idx_attendance_sessions_class_date');
            $table->index('created_by', 'idx_attendance_sessions_created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
