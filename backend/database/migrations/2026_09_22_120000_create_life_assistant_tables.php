<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('timezone')->default('Africa/Lagos');
            $table->time('reminder_time')->default('09:00:00');
            $table->unsignedSmallInteger('due_soon_days')->default(3);
            $table->string('currency', 3)->default('NGN');
            $table->string('theme')->default('system');
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->timestamp('last_sync_at')->nullable();
            $table->string('app_version')->nullable();
            $table->text('push_token')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('priority')->default('normal');
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('location')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('activity_recurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('rrule', 512);
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('activity_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('due_at');
            $table->date('due_local_date');
            $table->string('status')->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('snoozed_until')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'due_at']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('activity_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('offset_minutes');
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_category')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('account_reference')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->nullable()->constrained('activity_occurrences')->nullOnDelete();
            $table->string('delivery_key')->unique();
            $table->string('title');
            $table->text('body');
            $table->timestampTz('read_at')->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->constrained('activity_occurrences')->cascadeOnDelete();
            $table->integer('offset_minutes');
            $table->string('delivery_key')->unique();
            $table->timestampTz('sent_at');
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $this->syncColumns($table);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('activity_reminders');
        Schema::dropIfExists('activity_occurrences');
        Schema::dropIfExists('activity_recurrences');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('user_preferences');
    }

    private function syncColumns(Blueprint $table): void
    {
        $table->unsignedInteger('version')->default(1);
        $table->uuid('origin_device_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
