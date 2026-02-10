<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexauto_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tg_id')->unique();
            $table->string('username')->nullable();
            $table->string('fio');
            $table->string('phone');
            $table->timestamps();
        });

        Schema::create('lexauto_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('lexauto_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('lexauto_users')->cascadeOnDelete();
            $table->enum('status', ['reserved', 'review', 'sold', 'rejected']);
            $table->timestamp('reserved_until')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->string('check_file_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'reserved_until']);
        });

        Schema::create('lexauto_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('user_id')->constrained('lexauto_users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('lexauto_orders')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('lexauto_user_states', function (Blueprint $table) {
            $table->unsignedBigInteger('tg_id')->primary();
            $table->string('state', 64)->default('start');
            $table->json('payload')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexauto_user_states');
        Schema::dropIfExists('lexauto_tickets');
        Schema::dropIfExists('lexauto_orders');
        Schema::dropIfExists('lexauto_settings');
        Schema::dropIfExists('lexauto_users');
    }
};
