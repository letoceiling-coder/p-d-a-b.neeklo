<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->foreignId('bot_user_id')->constrained('bot_users')->cascadeOnDelete();
            $table->text('summary_text');
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->index(['telegram_bot_id', 'created_at']);
            $table->index('bot_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_analyses');
    }
};
