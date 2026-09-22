<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('relationship')->nullable();
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->text('notes')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'name']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
        });

        Schema::create('activity_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('child_activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('relation');
            $this->syncColumns($table);
            $table->unique(['parent_activity_id', 'child_activity_id', 'relation']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('shopping_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->nullable();
            $table->bigInteger('estimated_price_minor')->nullable();
            $table->bigInteger('actual_price_minor')->nullable();
            $table->string('priority')->default('normal');
            $table->boolean('purchased')->default(false);
            $table->string('store')->nullable();
            $table->text('notes')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
            $table->index(['shopping_list_id', 'purchased']);
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('follow_up_after_days')->nullable();
            $table->json('follow_up_rule')->nullable();
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->string('status_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn('status_label');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['follow_up_after_days', 'follow_up_rule']);
        });

        Schema::dropIfExists('subtasks');
        Schema::dropIfExists('shopping_items');
        Schema::dropIfExists('shopping_lists');
        Schema::dropIfExists('activity_links');

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::dropIfExists('contacts');
    }

    private function syncColumns(Blueprint $table): void
    {
        $table->unsignedInteger('version')->default(1);
        $table->uuid('origin_device_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
