<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('radius_meters')->nullable()->default(50);
        });

        Schema::table('class_students', function (Blueprint $table) {
            $table->string('academic_year', 9)->nullable();
        });

        // Drop the old partial unique index and recreate it with academic_year
        DB::statement('DROP INDEX IF EXISTS uq_class_students_active_student');
        DB::statement(
            'CREATE UNIQUE INDEX uq_class_students_active_student '
                . 'ON class_students (student_id, academic_year) WHERE is_active'
        );

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('distance_meters')->nullable();
            $table->string('location_status', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'distance_meters', 'location_status']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        // Restore the old partial unique index
        DB::statement('DROP INDEX IF EXISTS uq_class_students_active_student');
        DB::statement(
            'CREATE UNIQUE INDEX uq_class_students_active_student '
                . 'ON class_students (student_id) WHERE is_active'
        );

        Schema::table('class_students', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_meters']);
        });
    }
};
