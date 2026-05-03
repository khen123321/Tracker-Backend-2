<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * * ✨ THIS FIXES YOUR 403 FORBIDDEN ERROR ✨
     * By default, Laravel sets this to 'false'. We change it to 'true' 
     * or add actual logic to verify the logged-in user is an Admin/HR.
     */
    public function authorize(): bool
    {
        // Option 1: Simple fix (Allows the request to pass to the Controller)
        return true; 
        
        /* // Option 2: Strict fix (Only lets HR and Superadmins pass)
        // $user = Auth::user();
        // return $user && in_array($user->role, ['hr', 'superadmin']);
        */
    }

    /**
     * Get the validation rules that apply to the request.
     * These rules match your User model exactly.
     */
    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            // Ensure email is a valid format and doesn't already exist in the users table
            'email'         => 'required|string|email|max:255|unique:users,email',
            
            // Requires a password of at least 8 characters
            'password'      => 'required|string|min:8', 
            
            // ✨ THE FIX: 'hr_intern' is now an allowed role ✨
            'role'          => 'required|string|in:intern,hr,superadmin,hr_intern',
            
            'status'        => 'nullable|string|in:active,inactive',
            
            // If provided, verify these IDs actually exist in their respective tables
            'school_id'     => 'nullable|integer|exists:schools,id',
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            
            // Permissions should be an array (JSON) based on your model casting
            'permissions'   => 'nullable|array', 
        ];
    }

    /**
     * Custom error messages (Optional but makes frontend toasts look better)
     */
    public function messages(): array
    {
        return [
            'email.unique'   => 'An account with this email address already exists.',
            'role.in'        => 'The selected role is invalid.',
            'password.min'   => 'The password must be at least 8 characters long.',
            'school_id.exists' => 'The selected school does not exist in the database.',
        ];
    }
}