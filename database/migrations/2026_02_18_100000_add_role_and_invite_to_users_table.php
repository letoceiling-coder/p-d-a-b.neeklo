<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('employee')->after('email');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('invite_code_id')->nullable()->after('role')->constrained('invite_codes')->nullOnDelete();
        });
        // Первый существующий пользователь — администратор (чтобы был хотя бы один admin)
        $firstId = DB::table('users')->min('id');
        if ($firstId !== null) {
            DB::table('users')->where('id', $firstId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invite_code_id']);
            $table->dropColumn(['role', 'invite_code_id']);
        });
    }
};
