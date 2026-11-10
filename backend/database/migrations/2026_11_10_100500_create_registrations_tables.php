<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One registration record per student per semester; the courses are its items.
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
            $table->index('semester_id');
        });

        // The unique pair below is what makes duplicate course registration impossible at the database level.
        Schema::create('registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['registration_id', 'course_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_items');
        Schema::dropIfExists('registrations');
    }
};
