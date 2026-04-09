<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('translation_job_id')->unique()->constrained('translation_jobs')->cascadeOnDelete();
            $table->json('translated_document_json')->nullable();
            $table->json('risk_payload')->nullable();
            $table->json('notes_payload')->nullable();
            $table->json('meta_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_results');
    }
};
