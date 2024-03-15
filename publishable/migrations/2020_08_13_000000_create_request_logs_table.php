<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('client_ip');
            $table->string('user_agent');
            $table->string('method');
            $table->integer('status');
            $table->string('url');
            $table->string('root');
            $table->string('path');
            $table->string('query_string');
            $table->mediumText('request_headers');
            $table->mediumText('request_body');
            $table->mediumText('response_headers');
            $table->mediumText('response_body');
            $table->mediumText('response_exception');
            $table->decimal('execution_time', 20, 10)->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('request_logs');
    }
}
