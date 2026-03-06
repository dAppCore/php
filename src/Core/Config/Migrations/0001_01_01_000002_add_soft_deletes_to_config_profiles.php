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
     * Add soft deletes to config_profiles for audit trail.
     *
     * Enables tracking of deleted profiles for compliance and debugging.
     */
    public function up(): void
    {
        Schema::table('config_profiles', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('config_profiles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
