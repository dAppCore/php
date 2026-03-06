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
     * Core bouncer tables - IP blocking, rate limiting, redirects.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Blocked IPs
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('ip_range', 18)->nullable();
            $table->string('reason')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->unique(['ip_address', 'ip_range']);
            $table->index(['status', 'expires_at']);
            $table->index('ip_address');
        });

        // 2. Rate Limit Buckets
        Schema::create('rate_limit_buckets', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('bucket_type', 32);
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('max_tokens');
            $table->timestamp('last_refill_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['key', 'bucket_type']);
            $table->index('expires_at');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('rate_limit_buckets');
        Schema::dropIfExists('blocked_ips');
        Schema::enableForeignKeyConstraints();
    }
};
