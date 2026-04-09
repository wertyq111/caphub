<?php

use App\Enums\TranslationJobStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->string('mode');
            $table->enum('status', array_map(
                static fn (TranslationJobStatus $status): string => $status->value,
                TranslationJobStatus::cases(),
            ));
            $table->string('input_type');
            $table->string('document_type')->nullable();
            $table->string('source_lang');
            $table->string('target_lang');
            $table->longText('source_text')->nullable();
            $table->text('source_title')->nullable();
            $table->text('source_summary')->nullable();
            $table->longText('source_body')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_jobs');
    }
};
