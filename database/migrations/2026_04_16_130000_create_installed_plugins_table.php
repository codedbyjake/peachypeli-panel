<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('source');
            $table->string('plugin_id');
            $table->string('name');
            $table->string('version');
            $table->string('file_name');
            $table->string('install_dir');
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_plugins');
    }
};
