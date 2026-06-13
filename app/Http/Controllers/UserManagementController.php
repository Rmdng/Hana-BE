<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Support\DriverAvailability;
use App\Support\ListQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['customer', 'driver.vehicle']);
        $search = ListQueryFilters::searchTerm($request);

        $query
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driver', function (Builder $query) use ($search): void {
                            $query->where('driver_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('license_number', 'like', "%{$search}%")
                                ->orWhereHas('vehicle', function (Builder $query) use ($search): void {
                                    $query->where('plate_number', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($request->filled('role'), function (Builder $query) use ($request): void {
                $query->where('role', $request->query('role'));
            });

        ListQueryFilters::applyDateFilters($query, $request, 'created_at');

        $users = $query->latest()->get();

        return $this->success('Data pengguna berhasil ditampilkan.', $users);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(
            'Detail pengguna berhasil ditampilkan.',
            $user->load(['customer', 'driver.vehicle'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->syncProfile($user, $data);

            return $user;
        });

        return $this->success('Pengguna berhasil dibuat.', $user->load(['customer', 'driver.vehicle']), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate($this->rules($request, $user));

        DB::transaction(function () use ($data, $user): void {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);
            $this->syncProfile($user, $data);
        });

        return $this->success('Pengguna berhasil diperbarui.', $user->load(['customer', 'driver.vehicle']));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->driver && DriverAvailability::hasActiveTrip($user->driver)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun supir tidak bisa dihapus karena masih memiliki order aktif.',
                'data' => null,
            ], 422);
        }

        $user->delete();

        return $this->success('Pengguna berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'operasional', 'kepala_operasional', 'supir', 'customer'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['required_if:role,customer', 'nullable', 'string'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => [
                'nullable',
                'exists:vehicles,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $used = Driver::where('vehicle_id', $value)
                        ->when($user?->driver, function ($query) use ($user): void {
                            $query->where('id', '!=', $user->driver->id);
                        })
                        ->exists();

                    if ($used) {
                        $fail('Unit kendaraan sudah digunakan oleh supir lain.');
                    }
                },
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function syncProfile(User $user, array $data): void
    {
        if ($user->role === 'customer') {
            Customer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $data['company_name'] ?? $user->name,
                    'contact_name' => $data['contact_name'] ?? $data['name'] ?? $user->name,
                    'phone' => $data['customer_phone'] ?? $data['phone'] ?? '',
                    'email' => $data['customer_email'] ?? $user->email,
                    'address' => $data['customer_address'] ?? $data['address'] ?? '',
                ]
            );
        }

        if ($user->role === 'supir') {
            Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'vehicle_id' => $data['vehicle_id'] ?? null,
                    'driver_name' => $data['driver_name'] ?? $user->name,
                    'phone' => $data['driver_phone'] ?? $data['phone'] ?? '',
                    'license_number' => $data['license_number'] ?? null,
                ]
            );
        } else {
            $user->driver?->update(['vehicle_id' => null]);
        }
    }

    private function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
