<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->string('temp_upload_path')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            $table->dropColumn('temp_upload_path');
        });
    }
};
