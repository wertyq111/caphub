<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_glossary_hits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->nullable()->constrained('translation_jobs')->cascadeOnDelete();
            $table->foreignId('glossary_id')->constrained('glossaries')->cascadeOnDelete();
            $table->string('source_term');
            $table->string('chosen_translation');
            $table->text('match_text');
            $table->json('match_position')->nullable();
            $table->string('hit_source')->default('system');
            $table->timestamps();

            $table->index(['job_id', 'glossary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_glossary_hits');
    }
};
