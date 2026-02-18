<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable()->after('user_id');
            $table->string('status', 32)->default('draft')->after('title');
            $table->string('pdf_path')->nullable()->after('summary_json');
        });

        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->foreignId('telegram_bot_id')->nullable()->change();
            $table->foreignId('bot_user_id')->nullable()->change();
        });

        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->text('summary_text')->nullable()->change();
        });

        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id']);
        });
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->text('summary_text')->nullable(false)->change();
        });
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->foreignId('telegram_bot_id')->nullable(false)->change();
            $table->foreignId('bot_user_id')->nullable(false)->change();
        });
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'title', 'status', 'pdf_path']);
        });
    }
};
