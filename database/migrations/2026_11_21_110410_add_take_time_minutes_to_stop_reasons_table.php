<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stop_reasons', function (Blueprint $table) {
            $table->unsignedInteger('take_time_minutes')
                ->nullable()
                ->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('stop_reasons', function (Blueprint $table) {
            $table->dropColumn('take_time_minutes');
        });
    }
};