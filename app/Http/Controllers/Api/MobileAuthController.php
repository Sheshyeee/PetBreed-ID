<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends Controller
{
    /**
     * Handle mobile Google Sign-In
     */
    public function mobileLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify the Google ID token via Google's API
            $client = new Client();
            $response = $client->get('https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['id_token' => $request->id_token]
            ]);

            $googleUser = json_decode($response->getBody()->getContents());

            // Verify the token 'sub' (Unique Google ID) exists
            if (!isset($googleUser->sub)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token'
                ], 401);
            }

            // Find existing user or prepare to create new one
            $user = User::where('google_id', $googleUser->sub)->first();

            // Avatar Management: Only download if user is new or avatar is missing
            $avatarUrl = $user ? $user->avatar : null;
            if (!$avatarUrl && isset($googleUser->picture)) {
                try {
                    $avatarContents = file_get_contents($googleUser->picture);
                    // Use a static name based on ID so we don't save duplicates
                    $avatarName = 'avatar_' . $googleUser->sub . '.jpg';
                    $avatarPath = 'avatars/' . $avatarName;
                    Storage::disk('object-storage')->put($avatarPath, $avatarContents);
                    // Build URL manually
                    $avatarUrl = config('filesystems.disks.object-storage.url') . '/' . $avatarPath;
                } catch (\Exception $e) {
                    $avatarUrl = $googleUser->picture;
                }
            }

            // Create or update user
            $user = User::updateOrCreate(
                ['google_id' => $googleUser->sub],
                [
                    'name' => $googleUser->name ?? 'User',
                    'email' => $googleUser->email,
                    'avatar' => $avatarUrl,
                    'password' => $user ? $user->password : bcrypt(Str::random(16)),
                    'email_verified_at' => now(),
                ]
            );

            // Cleanup: Revoke old tokens for this specific app/device to keep table clean
            $user->tokens()->where('name', 'mobile-app')->delete();
            $token = $user->createToken('mobile-app')->plainTextToken;

            // Admin Logic
            $allowedEmail = ['modeltraining2000@gmail.com', 'jrbd2022-8800-57025@bicol-u.edu.ph'];
            $isAdmin = in_array($user->email, $allowedEmail);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'is_admin' => $isAdmin,
                    ],
                    'token' => $token,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $isAdmin = in_array($user->email, ['modeltraining2000@gmail.com', 'jrbd2022-8800-57025@bicol-u.edu.ph']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_admin' => $isAdmin,
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}
