<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_device_commands', function (Blueprint $table) {
            $table->unsignedInteger('pin')->nullable()->after('device_sn');
            $table->index(['device_sn', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_device_commands', function (Blueprint $table) {
            $table->dropIndex(['device_sn', 'pin']);
            $table->dropColumn('pin');
        });
    }
};
