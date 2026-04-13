<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'glossaries';

    protected string $index = 'glossaries_term_source_lang_target_lang_domain_unique';

    public function up(): void
    {
        if (! Schema::hasTable($this->table) || $this->indexExists($this->index)) {
            return;
        }

        $this->shrinkIndexedColumnsForMysql();

        Schema::table($this->table, function (Blueprint $table): void {
            $table->unique(['term', 'source_lang', 'target_lang', 'domain'], $this->index);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table) || ! $this->indexExists($this->index)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            $table->dropUnique($this->index);
        });
    }

    protected function indexExists(string $indexName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select(sprintf("PRAGMA index_list('%s')", $this->table));

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return (bool) DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $this->table)
            ->where('index_name', $indexName)
            ->exists();
    }

    protected function shrinkIndexedColumnsForMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $lengths = DB::table($this->table)
            ->selectRaw('MAX(CHAR_LENGTH(term)) as max_term')
            ->selectRaw('MAX(CHAR_LENGTH(source_lang)) as max_source_lang')
            ->selectRaw('MAX(CHAR_LENGTH(target_lang)) as max_target_lang')
            ->selectRaw('MAX(CHAR_LENGTH(domain)) as max_domain')
            ->first();

        if (($lengths->max_term ?? 0) > 191
            || ($lengths->max_source_lang ?? 0) > 16
            || ($lengths->max_target_lang ?? 0) > 16
            || ($lengths->max_domain ?? 0) > 64) {
            throw new RuntimeException('Existing glossary data exceeds the indexed column limits required by the current schema.');
        }

        DB::statement(<<<'SQL'
ALTER TABLE `glossaries`
    MODIFY `term` VARCHAR(191) COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `source_lang` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `target_lang` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `domain` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'chemical_news'
SQL);
    }
};
