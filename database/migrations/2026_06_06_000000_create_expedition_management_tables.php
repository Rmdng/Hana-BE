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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('phone');
            $table->text('address');
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->string('vehicle_type');
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver_name');
            $table->string('phone');
            $table->string('license_number')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->text('pickup_address');
            $table->text('destination_address');
            $table->string('item_name');
            $table->text('item_description')->nullable();
            $table->string('vehicle_type');
            $table->date('order_date');
            $table->string('status')->default('diajukan');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('surat_angkuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('surat_number')->unique();
            $table->date('issue_date');
            $table->text('pickup_address');
            $table->text('destination_address');
            $table->string('status')->default('diterbitkan');
            $table->timestamps();
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')->constrained()->cascadeOnDelete();
            $table->string('do_number')->unique();
            $table->date('do_date');
            $table->string('sender_name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surat_angkut_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('finish_time')->nullable();
            $table->string('status')->default('menunggu');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_trip_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('address')->nullable();
            $table->string('status');
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        Schema::create('trip_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_trip_id')->constrained()->cascadeOnDelete();
            $table->string('photo_type');
            $table->string('photo_path');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('uploaded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_photos');
        Schema::dropIfExists('trip_locations');
        Schema::dropIfExists('driver_trips');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('surat_angkuts');
        Schema::dropIfExists('shipment_orders');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('customers');
    }
};
