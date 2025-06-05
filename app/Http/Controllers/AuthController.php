<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redis;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'role' => 'required|string|in:user,admin',
            'profile_picture' => 'nullable|image|max:2048',
            'bio' => 'nullable|string|max:500',
        ]);

        // Check if the email is already registered
        if (User::where('email', $request->email)->exists()) {
            return response()->json(['error' => 'Email already registered'], 409);
        }
        
        $randomString = Str::random(10);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'profile_picture' => $request->hasFile('profile_picture') 
                ? $request->file('profile_picture')->store('profile_pictures', 'public') 
                : null,
            'bio' => $request->bio,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'email_verified_at' => now(),
            'remember_token' => $randomString,
        ]);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    public function requestOtpLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
        

        $otp = rand(100000, 999999);
        $key = 'otp:' . $user->email;

        Redis::setex($key, 300, $otp); // Store OTP for 5 minutes

        Mail::to($user->email)->send(new SendOtpMail($otp));

        return response()->json(['message' => 'OTP sent to your email. It will expire in 5 minutes.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'remember' => 'sometimes|boolean',
        ]);

        $key = 'otp:' . $request->email;
        $storedOtp = Redis::get($key);

        if (!$storedOtp || $storedOtp != $request->otp) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        Redis::del($key); // Clear OTP from Redis after successful use
        // update last login details
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $remember = $request->input('remember', false);

        if ($remember) {
            $token = JWTAuth::customClaims([
                'exp' => now()->addDays(7)->timestamp
            ])->fromUser($user);
            $randomString = Str::random(10);
            $user->remember_token = $randomString;
            $user->save();
        } else {
            $token = JWTAuth::fromUser($user);
        }

        return response()->json([
            'token' => $token,
            'expires_in' => $remember ? now()->addDays(7)->toDateTimeString() : now()->addHour()->toDateTimeString(),
        ]);
    }




    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required|string',
    //         'remember' => 'sometimes|boolean',
    //     ]);

    //     $credentials = $request->only('email', 'password');
    //     $remember = $request->input('remember', false);

    //     if ($remember) {
    //         // Set token expiry to 7 days from now
    //         $token = JWTAuth::customClaims([
    //             'exp' => now()->addDays(7)->timestamp
    //         ])->attempt($credentials);
    //     } else {
    //         // Default expiry (e.g., 1 hour configured in JWT settings)
    //         $token = JWTAuth::attempt($credentials);
    //     }

    //     if (!$token) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     return response()->json([
    //         'token' => $token,
    //         'expires_in' => $remember ? now()->addDays(7)->toDateTimeString() : now()->addHour()->toDateTimeString(),
    //     ]);
    // }


    public function logout(Request $request)
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
    


    public function me(Request $request)
    {
        return response()->json(JWTAuth::user());
    }
}