<?php

use App\Models\ExpertType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expert_types', function (Blueprint $table) {
            $table->foreignIdFor(ExpertType::class, 'parent_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expert_types', function (Blueprint $table) {
            $table->dropForeign('expert_types_parent_id_foreign');
            $table->dropIndex('expert_types_parent_id_foreign');
            $table->dropColumn('parent_id');
        });
    }
};
