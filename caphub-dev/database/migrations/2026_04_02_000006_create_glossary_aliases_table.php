<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('glossary_id')->constrained('glossaries')->cascadeOnDelete();
            $table->string('alias', 191);
            $table->string('match_type', 32)->default('exact');
            $table->timestamps();

            $table->index(['glossary_id', 'alias', 'match_type']);
            $table->unique(['glossary_id', 'alias', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_aliases');
    }
};
