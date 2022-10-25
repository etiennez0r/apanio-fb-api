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
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fb_handle', 256)->nullable();
            $table->string('fb_sync_status', 32)->nullable();
            $table->unsignedInteger('fb_sync_errors_total')->default(0);
            $table->string('fb_sync_errors', 512)->nullable();
            $table->unsignedInteger('fb_sync_attempts')->nullable();
            $table->timestamp('fb_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fb_handle');
            $table->dropColumn('fb_sync_status');
            $table->dropColumn('fb_sync_message');
            $table->dropColumn('fb_sync_attempts');
            $table->dropColumn('fb_synced_at');
        });
    }
};
