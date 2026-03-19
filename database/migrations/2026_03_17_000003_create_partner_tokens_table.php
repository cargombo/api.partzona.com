<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerTokensTable extends Migration
{
    public function up()
    {
        Schema::create('partner_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->string('token_key', 64)->unique();
            $table->string('token_hash', 64)->comment('SHA-256 hash');
            $table->enum('token_type', ['live', 'sandbox'])->default('live');
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->date('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->index(['partner_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_tokens');
    }
}
