<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(Request $request)
    {
        // Store mobile parameters in session
        if ($request->has('redirect_to')) {
            session(['mobile_redirect_to' => $request->redirect_to]);
        }

        if ($request->has('mobile')) {
            session(['is_mobile' => true]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (ClientException $e) {
            // Check if this is a mobile request
            $mobileRedirect = session('mobile_redirect_to');
            if ($mobileRedirect || session('is_mobile')) {
                session()->forget(['mobile_redirect_to', 'is_mobile']);
                return redirect()->away($mobileRedirect . '?error=cancelled');
            }

            return redirect('/login')->withErrors([
                'login' => 'Google login was cancelled.'
            ]);
        }

        // Download and store avatar
        $avatarUrl = null;
        if ($googleUser->getAvatar()) {
            try {
                $avatarContents = file_get_contents($googleUser->getAvatar());
                $avatarName = 'avatar_' . $googleUser->id . '_' . time() . '.jpg';
                $avatarPath = 'avatars/' . $avatarName;
                Storage::disk('object-storage')->put($avatarPath, $avatarContents);
                // Build URL manually
                $avatarUrl = config('filesystems.disks.object-storage.url') . '/' . $avatarPath;
            } catch (\Exception $e) {
                $avatarUrl = $googleUser->getAvatar();
            }
        }

        // Create or update user
        $user = User::updateOrCreate(
            [
                'google_id' => $googleUser->id,
            ],
            [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'avatar' => $avatarUrl,
                'password' => bcrypt(Str::random(16)),
            ]
        );

        // Handle Mobile Redirect
        $mobileRedirect = session('mobile_redirect_to');
        $isMobile = session('is_mobile');

        if ($mobileRedirect || $isMobile) {
            // Create API token for mobile
            $token = $user->createToken('mobile-app')->plainTextToken;
            $baseRedirect = $mobileRedirect ?? 'mobileapp://auth-success';

            // Clean session
            session()->forget(['mobile_redirect_to', 'is_mobile']);

            // Append token to the URI
            $separator = strpos($baseRedirect, '?') !== false ? '&' : '?';
            return redirect()->away($baseRedirect . $separator . "token=" . $token);
        }

        // Standard Web Login
        Auth::login($user);

        // Check if admin
        $allowedEmail = ['modeltraining2000@gmail.com'];
        if (in_array($googleUser->email, $allowedEmail)) {
            return redirect('/dashboard');
        }

        return redirect('/scan');
    }
}
