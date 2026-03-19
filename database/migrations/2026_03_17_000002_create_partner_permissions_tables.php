<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerPermissionsTables extends Migration
{
    public function up()
    {
        // Partner - Category icazələri (pivot)
        Schema::create('partner_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->unsignedBigInteger('category_id')->comment('1688 category_id');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->unique(['partner_id', 'category_id']);
        });

        // Partner - Endpoint icazələri
        Schema::create('partner_endpoint_perms', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->string('endpoint');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->unique(['partner_id', 'endpoint']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_endpoint_perms');
        Schema::dropIfExists('partner_categories');
    }
}
