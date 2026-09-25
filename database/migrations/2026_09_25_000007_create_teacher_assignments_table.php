<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('users')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->foreignId('class_id')
                ->constrained('classes')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->unique(['id', 'class_id'], 'uq_teacher_assignments_id_class');
            $table->index(['teacher_id', 'is_active'], 'idx_teacher_assignments_teacher_active');
            $table->index(['class_id', 'is_active'], 'idx_teacher_assignments_class_active');
            $table->index(['subject_id', 'is_active'], 'idx_teacher_assignments_subject_active');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uq_teacher_assignments_active_assignment '
                . 'ON teacher_assignments (teacher_id, class_id, subject_id) WHERE is_active'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
