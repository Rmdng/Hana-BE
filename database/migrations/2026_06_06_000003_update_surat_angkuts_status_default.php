<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surat_angkuts', function (Blueprint $table) {
            $table->string('status')->default('diterbitkan')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_angkuts', function (Blueprint $table) {
            $table->string('status')->default('issued')->change();
        });
    }
};
