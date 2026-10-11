<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestampTz('scanned_at', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Jangan ubah scanned_at kembali menjadi NOT NULL
        // jika masih ada record dengan scanned_at = NULL.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestampTz('scanned_at', 6)->nullable(false)->change();
        });
    }
};
