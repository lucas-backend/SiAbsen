<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('education_level_id')
                ->constrained('education_levels')
                ->onUpdate('no action')
                ->onDelete('restrict');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
            $table->unique(['education_level_id', 'name'], 'uq_classes_education_level_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
