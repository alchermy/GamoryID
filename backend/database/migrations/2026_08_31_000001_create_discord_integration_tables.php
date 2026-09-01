<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guild_id', 32)->unique();
            $table->string('guild_name', 120);
            $table->string('status', 32)->default('connected')->index();
            $table->json('bot_permissions')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('discord_channel_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_installation_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('channel_id', 32);
            $table->string('channel_name', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['discord_installation_id', 'purpose'], 'discord_channel_purpose_unique');
        });

        Schema::create('discord_setup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'used_at']);
        });

        Schema::create('discord_link_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'user_id', 'used_at'], 'discord_link_code_lookup');
        });

        Schema::create('discord_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('discord_user_id', 32);
            $table->string('discord_username', 120)->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
            $table->unique(['shop_id', 'discord_user_id']);
        });

        Schema::create('discord_command_logs', function (Blueprint $table) {
            $table->id();
            $table->string('interaction_id', 32)->unique();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discord_user_id', 32)->nullable();
            $table->string('command', 80);
            $table->string('status', 32)->index();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_command_logs');
        Schema::dropIfExists('discord_user_links');
        Schema::dropIfExists('discord_link_codes');
        Schema::dropIfExists('discord_setup_codes');
        Schema::dropIfExists('discord_channel_bindings');
        Schema::dropIfExists('discord_installations');
    }
};
