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
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (ClientException $e) {
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
                Storage::disk('public')->put('avatars/' . $avatarName, $avatarContents);
                $avatarUrl = '/storage/avatars/' . $avatarName;
            } catch (\Exception $e) {
                $avatarUrl = $googleUser->getAvatar();
            }
        }

        // Create or update user FIRST
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

        // Log in the user
        Auth::login($user);

        // Check if admin and redirect accordingly
        $allowedEmail = ['clapisdave8@gmail.com'];
        if (in_array($googleUser->email, $allowedEmail)) {
            return redirect('/dashboard');
        }

        return redirect('/scan');
    }
}
