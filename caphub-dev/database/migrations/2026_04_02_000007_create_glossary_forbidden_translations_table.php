<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_forbidden_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('glossary_id')->constrained('glossaries')->cascadeOnDelete();
            $table->string('forbidden_translation', 191);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['glossary_id', 'forbidden_translation']);
            $table->unique(['glossary_id', 'forbidden_translation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_forbidden_translations');
    }
};
