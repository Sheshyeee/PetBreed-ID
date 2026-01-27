<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (ClientException $e) {
            // User probably cancelled the login
            return redirect('/login')->withErrors([
                'login' => 'Google login was cancelled.'
            ]);
        }

        $avatarUrl = null;
        if ($googleUser->getAvatar()) {
            try {
                // Download and store the avatar locally
                $avatarContents = file_get_contents($googleUser->getAvatar());
                $avatarName = 'avatar_' . $googleUser->id . '_' . time() . '.jpg';
                Storage::disk('public')->put('avatars/' . $avatarName, $avatarContents);
                $avatarUrl = '/storage/avatars/' . $avatarName;
            } catch (\Exception $e) {
                // If download fails, use the original URL
                $avatarUrl = $googleUser->getAvatar();
            }
        }

        $allowedEmail = ['clapisdave8@gmail.com'];
        if (!in_array($googleUser->email, $allowedEmail)) {
            return redirect('/login')->withErrors([
                'login' => 'Unauthorized email address.'
            ]);
        }

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

        Auth::login($user);

        return redirect('/dashboard');
    }
}
