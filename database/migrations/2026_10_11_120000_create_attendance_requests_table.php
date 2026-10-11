<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->onUpdate('no action')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onUpdate('no action')->onDelete('cascade');
            $table->string('requested_status', 20);
            $table->text('reason')->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onUpdate('no action')->onDelete('set null');
            $table->timestampTz('reviewed_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->index('session_id', 'idx_attendance_requests_session');
            $table->index('student_id', 'idx_attendance_requests_student');
            $table->index('status', 'idx_attendance_requests_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_requests');
    }
};