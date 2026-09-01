<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns this application actually filters and sorts on.
 *
 * Chosen from the real query patterns rather than guessed: matter_requests had
 * no index on matter_id at all despite $matter->requests() running on every
 * matter view, and none on status despite the navigation badge counting pending
 * rows on every single page load. The incentive engine filters fees by
 * (matter_id, type, date) constantly, and matter_party by (role, type) across
 * ~15k rows on every assistant query.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private array $indexes = [
        'matters' => [
            'matters_final_report_at_index' => ['final_report_at'],
            'matters_initial_report_at_index' => ['initial_report_at'],
            'matters_distributed_at_index' => ['distributed_at'],
            'matters_next_session_date_index' => ['next_session_date'],
            'matters_collection_status_index' => ['collection_status'],
            'matters_deleted_at_index' => ['deleted_at'],
        ],
        'fees' => [
            'fees_matter_id_type_date_index' => ['matter_id', 'type', 'date'],
            'fees_date_index' => ['date'],
        ],
        'allocations' => [
            'allocations_date_index' => ['date'],
        ],
        'matter_party' => [
            'matter_party_role_type_index' => ['role', 'type'],
        ],
        'matter_requests' => [
            'matter_requests_matter_id_index' => ['matter_id'],
            'matter_requests_status_index' => ['status'],
        ],
        'incentive_lines' => [
            'incentive_lines_calculation_matter_index' => ['incentive_calculation_id', 'matter_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes) {
                foreach ($indexes as $indexName => $columns) {
                    if (! $this->indexExists($tableName, $indexName)) {
                        $table->index($columns, $indexName);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes) {
                foreach (array_keys($indexes) as $indexName) {
                    if ($this->indexExists($tableName, $indexName)) {
                        $table->dropIndex($indexName);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return array_key_exists(
            $index,
            Schema::getConnection()->getSchemaBuilder()->getIndexes($table) === []
                ? []
                : collect(Schema::getConnection()->getSchemaBuilder()->getIndexes($table))
                    ->keyBy('name')
                    ->all()
        );
    }
};
