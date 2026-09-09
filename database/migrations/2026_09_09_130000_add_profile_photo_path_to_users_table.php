<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The avatar upload field TomatoPHP\FilamentUsers renders (its non-Media-Library
 * fallback, since this app doesn't use Spatie Media Library) is hardcoded to a
 * `profile_photo_path` column — the app's own pre-existing `avatar` column was
 * never what the package reads or writes, so every avatar upload was silently
 * discarded on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('profile_photo_path')->nullable()->after('avatar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_photo_path');
            });
        }
    }
};
