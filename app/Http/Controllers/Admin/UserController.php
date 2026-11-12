<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
 
class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::orderBy('name')->get(['id', 'name', 'email', 'role', 'department', 'is_active', 'last_login_at']));
    }
 
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|string|min:8|max:255',
            'role'       => 'required|in:super_admin,manager,sap_admin,dc,im_lead,viewer',
            'department' => 'nullable|string',
        ]);
 
        $user = User::create(array_merge($data, ['password' => Hash::make($data['password'])]));
        return response()->json($user, 201);
    }
 
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'password'   => 'nullable|string|min:8|max:255',
            'role'       => 'sometimes|in:super_admin,manager,sap_admin,dc,im_lead,viewer',
            'department' => 'nullable|string',
            'is_active'  => 'boolean',
        ]);
 
        // An admin cannot lock themselves out
 
        if ($user->id === auth()->id()) {
 
            if (array_key_exists('is_active', $data) && !$data['is_active']) {
 
                return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
 
            }
 
            if (isset($data['role']) && $data['role'] !== $user->role) {
 
                return response()->json(['message' => 'You cannot change your own role.'], 422);
 
            }
 
        }

 
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
 
        $user->update($data);
        return response()->json($user);
    }
 
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }
}