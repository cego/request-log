<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Drop request_logs table
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('request_log_blacklisted_routes');
        Schema::dropIfExists('request_log_options');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Create request_logs table
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('client_ip');
            $table->text('user_agent')->nullable();
            $table->string('method');
            $table->integer('status');
            $table->string('url', 2000);
            $table->string('root');
            $table->string('path');
            $table->string('query_string', 2000);
            $table->mediumText('request_headers');
            $table->mediumText('request_body');
            $table->mediumText('response_headers');
            $table->mediumText('response_body');
            $table->mediumText('response_exception');
            $table->decimal('execution_time', 20, 10)->unsigned();
            $table->timestamps();
            $table->index('created_at');
            $table->index('status');
            $table->index('path');
        });

        // Create request_log_blacklisted_routes table
        Schema::create('request_log_blacklisted_routes', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->timestamps();
            $table->index("created_at");
        });

        // Create request_log_options table
        Schema::create('request_log_options', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('value');
            $table->timestamps();
        });
    }
};
