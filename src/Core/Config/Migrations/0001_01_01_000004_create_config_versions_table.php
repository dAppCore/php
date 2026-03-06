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
     * Config versions table - stores point-in-time snapshots for rollback.
     */
    public function up(): void
    {
        Schema::create('config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('config_profiles')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('label');
            $table->longText('snapshot'); // JSON snapshot of all config values
            $table->string('author')->nullable();
            $table->timestamp('created_at');

            $table->index(['profile_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_versions');
    }
};
