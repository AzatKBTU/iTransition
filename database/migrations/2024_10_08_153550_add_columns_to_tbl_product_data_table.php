<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tblProductData', function (Blueprint $table) {
            $table->decimal('price')->after('stmTimestamp');
            $table->integer('stockLevel')->after('price');
        });
    }

    public function down()
    {
        Schema::table('tblProductData', function (Blueprint $table) {
            $table->dropColumn(['price', 'stockLevel']);
        });
    }
};
