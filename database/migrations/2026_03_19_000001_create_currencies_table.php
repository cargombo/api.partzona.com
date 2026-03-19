<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 10);
            $table->string('to_currency', 10);
            $table->decimal('rate', 16, 6)->default(0);
            $table->timestamp('rate_date')->nullable();
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('currencies');
    }
};
