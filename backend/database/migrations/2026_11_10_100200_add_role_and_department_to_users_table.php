<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'lecturer', 'dept_admin', 'super_admin'])->default('student')->index();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            // Used to scope department admins. Students and lecturers get their department from their own profile row.
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamp('last_active_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'status', 'last_active_at']);
        });
    }
};
