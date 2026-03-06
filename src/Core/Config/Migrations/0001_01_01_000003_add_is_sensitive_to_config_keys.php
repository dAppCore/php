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
     * Add is_sensitive flag to config_keys for encryption support.
     *
     * When is_sensitive is true, values for this key will be encrypted
     * at rest using Laravel's encryption (APP_KEY).
     */
    public function up(): void
    {
        Schema::table('config_keys', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('default_value');
            $table->index('is_sensitive');
        });
    }

    public function down(): void
    {
        Schema::table('config_keys', function (Blueprint $table) {
            $table->dropIndex(['is_sensitive']);
            $table->dropColumn('is_sensitive');
        });
    }
};
