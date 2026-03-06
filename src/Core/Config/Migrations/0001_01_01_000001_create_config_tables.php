<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core config tables - hierarchical configuration management.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Config Keys (definitions)
        Schema::create('config_keys', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()
                ->constrained('config_keys')
                ->nullOnDelete();
            $table->string('type')->default('string');
            $table->string('category')->index();
            $table->string('description')->nullable();
            $table->json('default_value')->nullable();
            $table->timestamps();

            $table->index(['category', 'code']);
        });

        // 2. Config Profiles (scope containers)
        Schema::create('config_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope_type')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->foreignId('parent_profile_id')->nullable()
                ->constrained('config_profiles')
                ->nullOnDelete();
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->unique(['scope_type', 'scope_id', 'priority']);
        });

        // 3. Config Values
        Schema::create('config_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('config_profiles')
                ->cascadeOnDelete();
            $table->foreignId('key_id')
                ->constrained('config_keys')
                ->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->boolean('locked')->default(false);
            $table->foreignId('inherited_from')->nullable()
                ->constrained('config_profiles')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['profile_id', 'key_id']);
            $table->index(['key_id', 'locked']);
        });

        // 4. Config Channels
        Schema::create('config_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('notification');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // 5. Config Resolved Cache
        Schema::create('config_resolved', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->string('key_code');
            $table->json('resolved_value')->nullable();
            $table->foreignId('source_profile_id')->nullable()
                ->constrained('config_profiles')
                ->nullOnDelete();
            $table->timestamp('resolved_at');
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'key_code'], 'config_resolved_unique');
            $table->index(['scope_type', 'scope_id']);
            $table->index('key_code');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('config_resolved');
        Schema::dropIfExists('config_channels');
        Schema::dropIfExists('config_values');
        Schema::dropIfExists('config_profiles');
        Schema::dropIfExists('config_keys');
        Schema::enableForeignKeyConstraints();
    }
};
