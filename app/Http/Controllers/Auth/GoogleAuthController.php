<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteGoogleRegistrationRequest;
use App\Models\Marga;
use App\Models\OAuthAccount;
use App\Models\User;
use App\Support\IndonesiaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return to_route('login')->withErrors(['email' => 'Login dengan Google gagal. Silakan coba lagi.']);
        }

        $rawProfile = $googleUser->user;
        if (blank($googleUser->getId()) || blank($googleUser->getEmail())
            || (array_key_exists('verified_email', $rawProfile) && ! $rawProfile['verified_email'])) {
            return to_route('login')->withErrors(['email' => 'Email Google harus terverifikasi.']);
        }

        $provider = 'google';
        $providerId = (string) $googleUser->getId();
        $email = Str::lower($googleUser->getEmail());
        $oauthAccount = OAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($oauthAccount !== null) {
            $this->login($oauthAccount->user);

            return to_route('dashboard');
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser !== null) {
            $existingUser->oauthAccounts()->create([
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_email' => $email,
            ]);
            $this->login($existingUser);

            return to_route('dashboard');
        }

        session()->put('google_oauth', [
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email,
            'name' => $googleUser->getName() ?: Str::before($email, '@'),
            'avatar' => $googleUser->getAvatar(),
        ]);

        return to_route('google.registration.create');
    }

    public function create(): Response|RedirectResponse
    {
        $profile = session('google_oauth');
        if (! is_array($profile)) {
            return to_route('login');
        }

        return Inertia::render('auth/complete-google-registration', [
            'profile' => [
                'name' => $profile['name'],
                'email' => $profile['email'],
                'avatar' => $profile['avatar'],
            ],
            'margas' => Marga::query()->orderBy('name')->get(['id', 'name']),
            'regions' => IndonesiaRegions::all(),
        ]);
    }

    public function store(CompleteGoogleRegistrationRequest $request): RedirectResponse
    {
        $profile = $request->session()->pull('google_oauth');
        abort_unless(is_array($profile), 419, 'Sesi Google telah berakhir. Silakan ulangi login.');

        $user = DB::transaction(function () use ($profile, $request): User {
            $user = User::create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'password' => Hash::make(Str::random(64)),
                'marga_id' => $request->validated('marga_id'),
                'province_code' => $request->validated('province_code'),
                'regency_code' => $request->validated('regency_code'),
                'district_code' => $request->validated('district_code'),
                'village_code' => $request->validated('village_code'),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->oauthAccounts()->create([
                'provider' => $profile['provider'],
                'provider_id' => $profile['provider_id'],
                'provider_email' => $profile['email'],
            ]);

            return $user;
        });

        $this->login($user);

        return to_route('dashboard');
    }

    private function login(User $user): void
    {
        Auth::login($user, remember: true);
        request()->session()->regenerate();
    }
}
