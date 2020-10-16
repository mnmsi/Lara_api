<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('supplier');
            $table->string('model');
            $table->string('productCode');
            $table->string('color');
            $table->float('price', 8, 2);
            $table->string('image');
            $table->string('description');
            $table->string('discount')->nullable();
            $table->tinyInteger('isActive')->default(1);
            $table->tinyInteger('isDelete')->default(0);
            $table->dateTime('createdAt', 0)->useCurrent();
            $table->dateTime('updatedAt')->nullable();
            $table->tinyInteger('createdBy');
            $table->tinyInteger('updatedBy')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
