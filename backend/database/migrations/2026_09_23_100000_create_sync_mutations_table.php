<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_mutations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->uuid('client_mutation_id');
            $table->string('entity');
            $table->uuid('entity_id');
            $table->string('operation');
            $table->json('payload');
            $table->json('result')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('origin_device_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'client_mutation_id']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_mutations');
    }
};
