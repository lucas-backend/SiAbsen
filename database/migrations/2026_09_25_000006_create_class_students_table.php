<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->foreignId('student_id')
                ->constrained('users')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->index(['student_id', 'is_active'], 'idx_class_students_student_active');
            $table->index(['class_id', 'is_active'], 'idx_class_students_class_active');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uq_class_students_active_student '
                . 'ON class_students (student_id) WHERE is_active'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
