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
        Schema::create('ai_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->nullable()->constrained('translation_jobs')->nullOnDelete();
            $table->string('agent_name');
            $table->string('skill_version')->nullable();
            $table->json('request_payload');
            $table->json('response_payload_summary')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('token_usage_estimate')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
    }
};
