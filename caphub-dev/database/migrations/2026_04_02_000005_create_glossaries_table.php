<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossaries', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 191);
            $table->string('source_lang', 16);
            $table->string('target_lang', 16);
            $table->string('standard_translation', 191);
            $table->string('domain', 64)->default('chemical_news');
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_lang', 'target_lang']);
            $table->index(['domain', 'status']);
            $table->unique(['term', 'source_lang', 'target_lang', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossaries');
    }
};
