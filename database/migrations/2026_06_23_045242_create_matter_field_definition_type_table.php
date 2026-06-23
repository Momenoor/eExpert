<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matter_field_definition_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_field_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing data
        $definitions = DB::table('matter_field_definitions')->whereNotNull('type_id')->get();
        foreach ($definitions as $definition) {
            DB::table('matter_field_definition_type')->insert([
                'type_id' => $definition->type_id,
                'matter_field_definition_id' => $definition->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('matter_field_definitions', function (Blueprint $table) {
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matter_field_definitions', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->constrained()->nullOnDelete();
        });

        $pivots = DB::table('matter_field_definition_type')->get();
        foreach ($pivots as $pivot) {
            DB::table('matter_field_definitions')
                ->where('id', $pivot->matter_field_definition_id)
                ->update(['type_id' => $pivot->type_id]);
        }

        Schema::dropIfExists('matter_field_definition_type');
    }
};
