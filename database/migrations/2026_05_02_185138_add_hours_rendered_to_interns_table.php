<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('interns', function (Blueprint $table) {
            // Adds the column, sets default to 0, allows decimals (e.g., 40.50)
            $table->decimal('hours_rendered', 8, 2)->default(0)->after('id'); 
        });
    }

    public function down()
    {
        Schema::table('interns', function (Blueprint $table) {
            $table->dropColumn('hours_rendered');
        });
    }
};