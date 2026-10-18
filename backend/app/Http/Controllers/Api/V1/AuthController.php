<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestCode(RequestOtpRequest $request): JsonResponse
    {
        $this->otp->send($request->phone(), $request->ip());

        return response()->json([
            'message' => 'Un code de connexion vous a été envoyé par SMS.',
            'expires_in' => config('otp.ttl'),
            'resend_in' => config('otp.resend_cooldown'),
        ], 202);
    }

    /**
     * Connexion et inscription ne font qu'un : le compte est créé au premier
     * code validé.
     */
    public function verifyCode(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();

        $this->otp->verify($phone, $request->validated('code'));

        $user = User::firstOrCreate(['phone' => $phone]);
        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'last_seen_at' => now(),
        ])->save();

        $token = $user->createToken($request->validated('device_name') ?? 'mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
