<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('matter_requests')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                if (Schema::hasColumn('matter_requests', 'comment')) {
                    $table->longText('comment')->change()->comment('Comment for the matter request');
                } else {
                    $table->longText('comment')->nullable()->comment('Comment for the matter request');
                }
                if (Schema::hasColumn('matter_requests', 'approved_comment')) {
                    $table->longText('approved_comment')->nullable()->change()->comment('Comment for the matter request approval');
                } else {
                    $table->longText('approved_comment')->nullable()->comment('Comment for the matter request approval');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matter_requests')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                if (Schema::hasColumn('matter_requests', 'comment')) {
                    $table->string('comment')->change()->comment('Comment for the matter request');
                }
                if (Schema::hasColumn('matter_requests', 'approved_comment')) {
                    $table->string('approved_comment')->nullable()->change()->comment('Comment for the matter request approval');
                }
            });
        }
    }
};
