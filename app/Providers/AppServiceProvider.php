<?php

namespace App\Providers;

use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('api-token', function (Request $request) {
            $plainToken = $request->bearerToken();

            if (! $plainToken) {
                return null;
            }

            $apiToken = ApiToken::with('user')
                ->where('token', hash('sha256', $plainToken))
                ->first();

            if (! $apiToken) {
                return null;
            }

            $apiToken->forceFill([
                'last_used_at' => now(),
            ])->save();

            return $apiToken->user;
        });
    }
}
