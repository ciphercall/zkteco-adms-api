<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_device_users', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->index();
            $table->unsignedInteger('pin');
            $table->string('name', 100);
            $table->unsignedSmallInteger('privilege')->default(0);
            $table->string('card_no', 50)->nullable();
            $table->unsignedSmallInteger('group_id')->default(1);
            $table->boolean('disabled')->default(false);
            $table->longText('face_template')->nullable();
            $table->longText('fingerprint_template')->nullable();
            $table->timestamps();

            $table->unique(['device_sn', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_device_users');
    }
};
