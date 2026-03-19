<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('sender', ['partner', 'admin']);
            $table->text('message');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->index('ticket_id');
        });

        // admin_reply və replied_at artıq lazım deyil — messages cədvəlinə köçür
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['admin_reply', 'replied_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_messages');

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
        });
    }
};
