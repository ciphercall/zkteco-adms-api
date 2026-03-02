<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->nullable()->index();
            $table->string('endpoint', 100);
            $table->string('method', 10);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('content_type')->nullable();
            $table->json('query_params')->nullable();
            $table->json('form_params')->nullable();
            $table->json('headers')->nullable();
            $table->longText('raw_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_raw_logs');
    }
};
