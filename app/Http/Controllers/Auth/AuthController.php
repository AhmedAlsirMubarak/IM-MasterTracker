<?php
 
namespace App\Http\Controllers\Auth;
 
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
 
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
 
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
 
        $user = Auth::user();
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api-token')->plainTextToken;
 
        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }
 
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
 
    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }
 
    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'department' => $user->department,
        ];
    }
}