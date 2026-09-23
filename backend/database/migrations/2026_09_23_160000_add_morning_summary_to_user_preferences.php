<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('morning_summary_enabled')->default(false)->after('theme');
            $table->time('morning_summary_time')->default('07:00:00')->after('morning_summary_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn(['morning_summary_enabled', 'morning_summary_time']);
        });
    }
};
