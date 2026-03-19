<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->integer('rpm_limit')->default(0);
            $table->integer('daily_limit')->default(0);
            $table->integer('monthly_limit')->default(0);
            $table->integer('max_concurrent')->default(5);
            $table->integer('max_categories')->nullable();
            $table->boolean('sandbox')->default(true);
            $table->boolean('ip_whitelist')->default(false);
            $table->boolean('webhook')->default(false);
            $table->string('sla')->default('—');
            $table->decimal('price_monthly', 8, 2)->default(0);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
