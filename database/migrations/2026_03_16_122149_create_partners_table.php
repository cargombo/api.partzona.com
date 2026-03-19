<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnersTable extends Migration
{
    public function up()
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('plain_password')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->enum('payment_model', ['deposit', 'debit'])->default('deposit');
            $table->decimal('deposit_balance', 12, 2)->default(0);
            $table->decimal('debit_limit', 12, 2)->default(0);
            $table->decimal('debit_used', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->integer('rpm_limit')->default(0);           // Request/dəqiqə
            $table->integer('daily_limit')->default(0);          // Request/gün
            $table->integer('monthly_limit')->default(0);        // Request/ay
            $table->integer('max_concurrent')->default(5);       // Eyni anda max sorğu
            $table->json('ip_whitelist')->nullable();
            $table->boolean('allow_negative')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('partners');
    }
}
