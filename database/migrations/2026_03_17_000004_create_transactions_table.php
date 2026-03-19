<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['deposit', 'charge', 'withdrawal', 'refund']);
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable()->comment('order, refund, manual');
            $table->string('reference_id')->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->index(['partner_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
