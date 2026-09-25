<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique('uq_subjects_name');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
