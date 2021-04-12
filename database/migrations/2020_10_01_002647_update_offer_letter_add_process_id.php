<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOfferLetterAddProcessId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if (!Schema::hasColumn('offer_letters', 'process_id')) {
            Schema::table('offer_letters', function (Blueprint $table) {
                $table->integer('process_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('offer_letters', function (Blueprint $table) {
            $table->dropColumn(['process_id']);
        });
    }
}
