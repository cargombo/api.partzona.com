<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiLogsTable extends Migration
{
    public function up()
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms');
            $table->string('ip', 45);
            $table->string('user_agent')->nullable();
            $table->text('request_params')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->index(['partner_id', 'created_at']);
            $table->index('endpoint');
            $table->index('status_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_logs');
    }
}
