<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique('uq_education_levels_name');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_levels');
    }
};
