<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $config = config('footprints.drivers.database');

        if (!$config) {
            return;
        }

        Schema::create($config['table_name'], function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->index();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('method');
            $table->string('uri');
            $table->string('endpoint');
            $table->ipAddress();
            $table->integer('status_code');
            $table->float('duration_ms');
            $table->boolean('success');
            $table->json('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->json('request_headers')->nullable();
            $table->timestamp('requested_at');
            $table->boolean('success');
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('footprints.drivers.database.table_name', 'footprints'));
    }
};