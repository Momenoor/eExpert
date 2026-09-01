<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catch-up migration for columns that exist in production but were never created
 * by any migration — they were added directly against the live database.
 *
 * Found by replaying every migration into a scratch database and diffing the
 * resulting schema against live MySQL. Without this, `migrate:fresh` produces a
 * database where saving a Party throws "Unknown column 'fax'", and because
 * MatterResource::getEloquentQuery() reads $user->party (parties.user_id), the
 * Matters page 500s for every user on a clean install.
 *
 * Every column is guarded, so this is a no-op against the existing production
 * database and only does work on a fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parties')) {
            Schema::table('parties', function (Blueprint $table) {
                if (! Schema::hasColumn('parties', 'fax')) {
                    $table->string('fax')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('parties', 'address')) {
                    $table->text('address')->nullable()->after('fax');
                }
                if (! Schema::hasColumn('parties', 'black_list')) {
                    $table->enum('black_list', ['true', 'false'])->default('false');
                }
                if (! Schema::hasColumn('parties', 'extra')) {
                    $table->text('extra')->nullable();
                }
                if (! Schema::hasColumn('parties', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable();
                }
                if (! Schema::hasColumn('parties', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('courts')) {
            Schema::table('courts', function (Blueprint $table) {
                if (! Schema::hasColumn('courts', 'phone')) {
                    $table->string('phone')->nullable();
                }
                if (! Schema::hasColumn('courts', 'email')) {
                    $table->string('email')->nullable();
                }
                if (! Schema::hasColumn('courts', 'address')) {
                    $table->text('address')->nullable();
                }
                if (! Schema::hasColumn('courts', 'active')) {
                    $table->enum('active', ['active', 'inactive'])->default('active');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'gender')) {
                    $table->enum('gender', ['male', 'female'])->default('male');
                }
                if (! Schema::hasColumn('users', 'category')) {
                    $table->string('category')->default('staff');
                }
                if (! Schema::hasColumn('users', 'avatar')) {
                    $table->string('avatar')->default('user.jpg');
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally not reversible: these columns hold production data that
        // predates this migration, and dropping them on a rollback would destroy
        // it. The migration only ever adds what is missing.
    }
};
