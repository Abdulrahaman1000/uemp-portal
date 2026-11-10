<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // e.g. 2026/2027
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->enum('name', ['First', 'Second']);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'name']);
        });

        // Database-level guarantee that at most ONE session and ONE semester is current.
        // (Partial unique indexes work on PostgreSQL/Neon and SQLite.)
        // App code must switch the old row to false before setting the new one, inside a transaction.
        DB::statement('CREATE UNIQUE INDEX academic_sessions_one_current ON academic_sessions (is_current) WHERE is_current');
        DB::statement('CREATE UNIQUE INDEX semesters_one_current ON semesters (is_current) WHERE is_current');
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_sessions');
    }
};
