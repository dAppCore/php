<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

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
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255)->index();
            $table->string('query_hash', 32)->index();
            $table->unsignedInteger('result_count')->default(0);
            $table->json('types')->nullable();
            $table->float('duration_ms')->nullable();
            $table->string('session_id', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            // Index for zero-result queries
            $table->index(['result_count', 'created_at']);

            // Index for popular queries analysis
            $table->index(['query_hash', 'created_at']);
        });

        Schema::create('search_analytics_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 32)->index();
            $table->string('result_type', 50)->index();
            $table->string('result_id', 255);
            $table->unsignedSmallInteger('position');
            $table->string('session_id', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();

            // Index for click-through analysis
            $table->index(['query_hash', 'result_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_analytics_clicks');
        Schema::dropIfExists('search_analytics');
    }
};
