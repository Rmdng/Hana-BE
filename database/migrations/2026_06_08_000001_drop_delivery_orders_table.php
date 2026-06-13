<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_orders')) {
            return;
        }

        Schema::table('delivery_orders', function (Blueprint $table): void {
            try {
                $table->dropForeign(['shipment_order_id']);
            } catch (Throwable) {
                // Foreign key may already be absent depending on local database state.
            }
        });

        Schema::dropIfExists('delivery_orders');
    }

    public function down(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_order_id')->constrained()->cascadeOnDelete();
            $table->string('do_number')->unique();
            $table->date('do_date');
            $table->string('status')->default('menunggu_muat');
            $table->string('sender_name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
