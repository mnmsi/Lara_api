<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userId');
            $table->string('status');
            $table->string('userName');
            $table->string('userPhone');
            $table->string('userEmail');
            $table->string('userAddress');
            $table->string('totalQuantity');
            $table->float('totalPrice', 8, 2);
            $table->float('totalDiscount', 8, 2);
            $table->float('totalAmount', 8, 2);
            $table->tinyInteger('isActive')->default(1);
            $table->tinyInteger('isDelete')->default(0);
            $table->dateTime('created_at', 0)->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->tinyInteger('createdBy')->default(1);
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
        Schema::dropIfExists('orders');
    }
}
