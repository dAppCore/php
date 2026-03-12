<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source', 64)->index();
            $table->string('event_type', 128)->nullable();
            $table->json('headers');
            $table->json('payload');
            $table->boolean('signature_valid')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source', 'processed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_calls');
    }
};
