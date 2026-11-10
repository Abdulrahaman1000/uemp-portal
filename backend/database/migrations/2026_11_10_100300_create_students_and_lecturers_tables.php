<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('matric_no', 30)->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('level'); // 100, 200, 300 ...
            $table->timestamp('graduated_at')->nullable(); // active/inactive/suspended come from users.status
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'level']);
        });

        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('staff_id', 30)->nullable()->unique();
            $table->string('specialization')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturers');
        Schema::dropIfExists('students');
    }
};
