<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('action');
            $table->foreignId('job_id')->nullable()->constrained('translation_jobs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ip_hash', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_access_logs');
    }
};
