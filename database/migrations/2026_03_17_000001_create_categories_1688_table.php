<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategories1688Table extends Migration
{
    public function up()
    {
        Schema::create('categories_1688', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->unique()->comment('1688 category ID');
            $table->string('chinese_name')->nullable();
            $table->string('translated_name')->nullable();
            $table->unsignedBigInteger('parent_category_id')->default(0)->comment('1688 parent category ID');
            $table->boolean('leaf')->default(false);
            $table->string('level')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('inactive');
            $table->unsignedInteger('partner_count')->default(0);
            $table->timestamps();

            $table->index('parent_category_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories_1688');
    }
}
