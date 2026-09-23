<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('email', 191)->unique();
            $table->string('display_name', 191)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 191);
            $table->enum('gender', ['male', 'female'])->default('male');
            $table->string('category', 191)->default('staff');
            // Legacy avatar field: superseded by profile_photo_path (the column
            // TomatoPHP\FilamentUsers' avatar upload actually reads/writes) but
            // kept because other code still references it.
            $table->string('avatar', 191)->default('user.jpg');
            $table->string('profile_photo_path', 191)->nullable()->default('user.jpg');
            $table->timestamp('last_seen_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->enum('language', ['ar', 'en'])->default('en');
            $table->json('ui_preferences')->nullable();
            $table->integer('font_size')->nullable();
            $table->boolean('notify_by_whatsapp')->default(false);
            $table->boolean('notify_by_email')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
