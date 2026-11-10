<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One submission per course per semester: it carries the workflow status for the whole class list.
        Schema::create('result_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'reopened'])->default('draft');
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'semester_id']);
            $table->index('status');
            $table->index('lecturer_id');
        });

        // Per-student scores. Total, grade and grade point are computed by the GradingService when saving,
        // using the configurable scale in config/grading.php (CA and exam limits are validated in the app).
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->decimal('ca_score', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('grade', 2)->nullable();
            $table->decimal('grade_point', 3, 1)->nullable();
            $table->timestamps();

            $table->unique(['result_submission_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
        Schema::dropIfExists('result_submissions');
    }
};
