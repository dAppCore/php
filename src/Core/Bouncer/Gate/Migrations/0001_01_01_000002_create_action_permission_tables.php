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
     * Action permission tables - whitelist-based request authorization.
     *
     * Philosophy: "If it wasn't trained, it doesn't exist."
     * Every controller action must be explicitly permitted.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Action Permissions (whitelist)
        Schema::create('core_action_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('action');                     // product.create, order.refund
            $table->string('scope')->nullable();          // Resource type or specific ID
            $table->string('guard')->default('web');      // web, api, admin
            $table->string('role')->nullable();           // admin, editor, or null for any auth
            $table->boolean('allowed')->default(false);
            $table->string('source');                     // 'trained', 'seeded', 'manual'
            $table->string('trained_route')->nullable();
            $table->foreignId('trained_by')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();

            $table->unique(['action', 'scope', 'guard', 'role'], 'action_permission_unique');
            $table->index('action');
            $table->index(['guard', 'allowed']);
        });

        // 2. Action Requests (audit log)
        Schema::create('core_action_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);                 // GET, POST, etc.
            $table->string('route');                      // /admin/products
            $table->string('action');                     // product.create
            $table->string('scope')->nullable();
            $table->string('guard');                      // web, api, admin
            $table->string('role')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20);                 // allowed, denied, pending
            $table->boolean('was_trained')->default(false);
            $table->timestamps();

            $table->index(['action', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('core_action_requests');
        Schema::dropIfExists('core_action_permissions');
        Schema::enableForeignKeyConstraints();
    }
};
