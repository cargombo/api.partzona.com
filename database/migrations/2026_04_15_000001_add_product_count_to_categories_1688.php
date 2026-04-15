<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('categories_1688', function (Blueprint $table) {
            $table->unsignedInteger('product_count')->default(0)->after('status');
            $table->timestamp('product_count_updated_at')->nullable()->after('product_count');
        });
    }

    public function down()
    {
        Schema::table('categories_1688', function (Blueprint $table) {
            $table->dropColumn(['product_count', 'product_count_updated_at']);
        });
    }
};
