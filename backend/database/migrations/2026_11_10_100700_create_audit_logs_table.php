<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. The user name and role are copied in so the trail stays readable even if the user is removed.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('role', 20);
            $table->string('action', 50)->index(); // e.g. RESULT_SUBMITTED
            $table->string('entity'); // human label, e.g. CSC301
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->nullableMorphs('auditable'); // the record the action was about (e.g. a result submission)
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
