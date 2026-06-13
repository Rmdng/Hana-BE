<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\ShipmentOrder;
use App\Models\SuratAngkut;
use App\Models\TripLocation;
use App\Models\TripPhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = $this->createUser(
            'Admin Hana Transport',
            'admin@hana.com',
            'admin'
        );

        $operasional = $this->createUser(
            'Rudi Hartono',
            'operasional@hana.com',
            'operasional'
        );

        $kepalaOperasional = $this->createUser(
            'Haryanto Setiawan',
            'kepala@hana.com',
            'kepala_operasional'
        );

        $supir = $this->createUser(
            'Agus Santoso',
            'supir@hana.com',
            'supir'
        );

        $customerUser = $this->createUser(
            'Budi Pratama',
            'customer@hana.com',
            'customer',
            '081234567890',
            'Jl. Daan Mogot No. 25, Cengkareng, Jakarta Barat'
        );

        $customer = Customer::updateOrCreate(
            ['company_name' => 'PT Sinar Logistik Indonesia'],
            [
                'user_id' => $customerUser->id,
                'contact_name' => 'Budi Pratama',
                'phone' => '081234567890',
                'email' => 'customer@hana.com',
                'address' => 'Jl. Daan Mogot No. 25, Cengkareng, Jakarta Barat',
            ]
        );

        $vehicle = Vehicle::updateOrCreate(
            ['plate_number' => 'B 9876 HJP'],
            [
                'vehicle_type' => 'Wingbox',
                'status' => 'tersedia',
            ]
        );

        $driver = Driver::updateOrCreate(
            ['user_id' => $supir->id],
            [
                'vehicle_id' => $vehicle->id,
                'driver_name' => 'Agus Santoso',
                'phone' => '082112345678',
                'license_number' => 'SIM-B1-123456',
            ]
        );

        $shipmentOrder = ShipmentOrder::updateOrCreate(
            ['order_number' => 'ORD-001'],
            [
                'customer_id' => $customer->id,
                'pickup_address' => 'Kawasan Industri Jatake, Tangerang, Banten',
                'destination_address' => 'Jl. Soekarno Hatta No. 120, Bandung, Jawa Barat',
                'item_name' => 'Barang Ekspedisi',
                'item_description' => 'Muatan barang kebutuhan retail',
                'vehicle_type' => 'Wingbox',
                'order_date' => now()->toDateString(),
                'status' => 'berjalan',
                'notes' => 'Pengiriman dari Tangerang menuju Bandung',
            ]
        );

        $suratAngkut = SuratAngkut::updateOrCreate(
            ['surat_number' => 'SA-001-HJP'],
            [
                'shipment_order_id' => $shipmentOrder->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'issue_date' => now()->toDateString(),
                'pickup_address' => 'Kawasan Industri Jatake, Tangerang, Banten',
                'destination_address' => 'Jl. Soekarno Hatta No. 120, Bandung, Jawa Barat',
                'status' => 'diterbitkan',
            ]
        );

        $driverTrip = DriverTrip::updateOrCreate(
            [
                'shipment_order_id' => $shipmentOrder->id,
                'driver_id' => $driver->id,
            ],
            [
                'surat_angkut_id' => $suratAngkut->id,
                'start_time' => now()->subHours(2),
                'finish_time' => null,
                'status' => 'dalam_perjalanan',
                'notes' => 'Supir sedang dalam perjalanan menuju lokasi bongkar',
            ]
        );

        TripLocation::updateOrCreate(
            [
                'driver_trip_id' => $driverTrip->id,
                'status' => 'dalam_perjalanan',
            ],
            [
                'latitude' => -6.914744,
                'longitude' => 107.609810,
                'address' => 'Bandung, Jawa Barat',
                'recorded_at' => now(),
            ]
        );

        TripPhoto::updateOrCreate(
            [
                'driver_trip_id' => $driverTrip->id,
                'photo_type' => 'muat',
            ],
            [
                'photo_path' => 'trip_photos/dummy-bukti-muat.jpg',
                'latitude' => -6.178306,
                'longitude' => 106.631889,
                'notes' => 'Bukti muat barang di Tangerang',
                'uploaded_at' => now()->subHours(2),
            ]
        );

        TripPhoto::updateOrCreate(
            [
                'driver_trip_id' => $driverTrip->id,
                'photo_type' => 'bongkar',
            ],
            [
                'photo_path' => 'trip_photos/dummy-bukti-bongkar.jpg',
                'latitude' => -6.914744,
                'longitude' => 107.609810,
                'notes' => 'Bukti bongkar belum final, hanya contoh dummy',
                'uploaded_at' => now(),
            ]
        );

        unset($admin, $operasional, $kepalaOperasional);
    }

    private function createUser(
        string $name,
        string $email,
        string $role,
        ?string $phone = null,
        ?string $address = null
    ): User {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password123'),
                'role' => $role,
                'phone' => $phone,
                'address' => $address,
            ]
        );
    }
}
