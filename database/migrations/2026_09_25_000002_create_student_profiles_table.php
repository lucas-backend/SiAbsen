<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->primary()
                ->constrained('users')
                ->onUpdate('no action')
                ->onDelete('cascade');
            $table->string('student_number', 50)->unique('uq_student_profiles_student_number');
            $table->foreignId('education_level_id')
                ->constrained('education_levels')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->index('education_level_id', 'idx_student_profiles_education_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
