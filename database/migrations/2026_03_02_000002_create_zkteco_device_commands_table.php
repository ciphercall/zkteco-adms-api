<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->index();
            $table->string('channel', 40)->default('getrequest');
            $table->text('command_template');
            $table->unsignedInteger('cmd_id')->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->text('ack_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_device_commands');
    }
};
