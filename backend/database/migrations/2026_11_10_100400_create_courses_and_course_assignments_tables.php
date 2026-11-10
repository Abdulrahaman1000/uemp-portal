<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('title');
            $table->unsignedTinyInteger('units');
            $table->unsignedSmallInteger('level');
            $table->enum('semester', ['First', 'Second']);
            $table->enum('type', ['core', 'elective'])->default('core');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // Used by "available courses for my department, program, level and semester".
            $table->index(['department_id', 'program_id', 'level', 'semester']);
        });

        // One lecturer teaches a course in a given academic session.
        Schema::create('course_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'academic_session_id']);
            $table->index('lecturer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_assignments');
        Schema::dropIfExists('courses');
    }
};
