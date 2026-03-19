<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLimitsResetAtToPartners extends Migration
{
    public function up()
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->timestamp('limits_reset_at')->nullable()->after('allow_negative');
        });
    }

    public function down()
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('limits_reset_at');
        });
    }
}
