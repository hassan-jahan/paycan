<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect(string $provider, string $action = 'login'): RedirectResponse
    {
        $this->validateProvider($provider);
        session(['socialite_action' => $action]);
        return Socialite::driver($provider)->redirect();
    }
    
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);
        
        try {
            $action = session('socialite_action', 'login');
            $providerUser = Socialite::driver($provider)->user();
            
            if ($action === 'connect' && Auth::check()) {
                return $this->connectAccount($provider, $providerUser);
            } else {
                return $this->handleAuthentication($provider, $providerUser);
            }
            
        } catch (Exception $e) {
            return redirect()->route('login')
                ->withErrors(['error' => 'An error occurred with the ' . $provider . ' sign in: ' . $e->getMessage()]);
        }
    }
    
    protected function connectAccount(string $provider, $providerUser): RedirectResponse
    {
        $user = Auth::user();
        
        $existingConnection = SocialConnection::where('provider', $provider)
            ->where('provider_id', $providerUser->getId())
            ->first();
            
        if ($existingConnection && $existingConnection->user_id !== $user->id) {
            return redirect()->route('settings.social')
                ->withErrors(['error' => 'This ' . $provider . ' account is already connected to another user.']);
        }
        
        SocialConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerUser->getId()
            ],
            [
                'name' => $providerUser->getName(),
                'email' => $providerUser->getEmail(),
                'avatar' => $providerUser->getAvatar(),
                'access_token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken ?? null,
                'token_expires_at' => isset($providerUser->expiresIn) ? now()->addSeconds($providerUser->expiresIn) : null,
                'connection_type' => 'connect',
                'metadata' => $providerUser->user ?? null,
            ]
        );
        
        return redirect()->route('settings.social')
            ->with('status', 'Your ' . $provider . ' account has been connected successfully.');
    }
    
    protected function handleAuthentication(string $provider, $providerUser): RedirectResponse
    {
        return DB::transaction(function () use ($provider, $providerUser) {
            $connection = SocialConnection::where('provider', $provider)
                ->where('provider_id', $providerUser->getId())
                ->first();
                
            if ($connection) {
                Auth::login($connection->user);
                return redirect()->intended(route('dashboard'));
            }
            
            if ($providerUser->getEmail()) {
                $user = User::where('email', $providerUser->getEmail())->where('email_verified_at', '!=', null)->first();
                
                if ($user) {
                    $this->createConnection($user, $provider, $providerUser, 'login');
                    Auth::login($user);
                    return redirect()->intended(route('dashboard'));
                }
            }
            
            $user = User::create([
                'name' => $providerUser->getName() ?? 'User',
                'email' => $providerUser->getEmail() ?? $provider . '_' . $providerUser->getId() . '@example.com',
                'password' => Hash::make(Str::random(24)),
            ]);
            
            $this->createConnection($user, $provider, $providerUser, 'login');
            
            Auth::login($user);
            
            return redirect()->intended(route('dashboard'));
        });
    }
    
    protected function createConnection(User $user, string $provider, $providerUser, string $type): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerUser->getId(),
            'name' => $providerUser->getName(),
            'email' => $providerUser->getEmail(),
            'avatar' => $providerUser->getAvatar(),
            'access_token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken ?? null,
            'token_expires_at' => isset($providerUser->expiresIn) ? now()->addSeconds($providerUser->expiresIn) : null,
            'connection_type' => $type,
            'metadata' => $providerUser->user ?? null,
        ]);
    }
    
    protected function validateProvider(string $provider): void
    {
        $supportedProviders = ['google', 'facebook', 'github'];
        
        if (!in_array($provider, $supportedProviders)) {
            throw new Exception("The provider '{$provider}' is not supported.");
        }
    }
}