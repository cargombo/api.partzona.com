<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id');
            $table->string('order_id')->unique()->comment('1688 order ID');
            $table->string('out_order_id')->unique()->comment('Partzona internal order ID');
            $table->enum('status', [
                'waitbuyerpay',
                'waitsellersend',
                'waitbuyerreceive',
                'confirm_goods',
                'success',
                'cancel',
                'terminated',
            ])->default('waitbuyerpay');
            $table->json('products')->nullable()->comment('Order products data from 1688');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('Total amount in yuan');
            $table->decimal('post_fee', 10, 2)->default(0)->comment('Shipping fee');
            $table->string('flow')->nullable()->comment('bigcfenxiao or bigcpifa');
            $table->json('address')->nullable()->comment('Shipping address');
            $table->text('message')->nullable()->comment('Message to seller');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->index('partner_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
