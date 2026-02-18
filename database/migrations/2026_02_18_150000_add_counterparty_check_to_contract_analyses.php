<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->json('counterparty_check')->nullable()->after('summary_json');
        });
    }

    public function down(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->dropColumn('counterparty_check');
        });
    }
};
