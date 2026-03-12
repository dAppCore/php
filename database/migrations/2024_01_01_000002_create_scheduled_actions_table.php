<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_class')->unique();
            $table->string('frequency', 100);
            $table->string('timezone', 50)->nullable();
            $table->boolean('without_overlapping')->default(true);
            $table->boolean('run_in_background')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_actions');
    }
};
