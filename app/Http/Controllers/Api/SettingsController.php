<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RequirementSetting;
use App\Models\School; 

class SettingsController extends Controller
{
    public function getSchools()
    {
        return response()->json(School::all());
    }

    public function getRequirements()
    {
        $settings = RequirementSetting::with('school')->orderBy('created_at', 'desc')->get();
        return response()->json($settings);
    }

    /**
     * Add a new requirement rule (AND auto-create a school if needed)
     */
    public function storeRequirement(Request $request)
    {
        // Notice we made school_id nullable, and added new_school_name
        $request->validate([
            'school_id' => 'nullable|integer', 
            'new_school_name' => 'nullable|string|max:255',
            'course_name' => 'required|string|max:255',
            'required_hours' => 'required|integer|min:1'
        ]);

        // Failsafe: They must provide one or the other!
        if (!$request->school_id && empty($request->new_school_name)) {
            return response()->json(['message' => 'Please select a school or enter a new one.'], 400);
        }

        // 👇 THE MAGIC: Create the school on the fly if it doesn't exist 👇
        $finalSchoolId = $request->school_id;
        
        if (!empty($request->new_school_name)) {
            // Check if they accidentally typed a school that already exists to prevent duplicates
            $school = School::firstOrCreate(['name' => $request->new_school_name]);
            $finalSchoolId = $school->id;
        }

        // Check if a rule for this exact school and course already exists
        $existing = RequirementSetting::where('school_id', $finalSchoolId)
                        ->where('course_name', $request->course_name)
                        ->first();

        if ($existing) {
            return response()->json(['message' => 'A rule for this School and Course already exists.'], 400);
        }

        // Save the new curriculum rule using the final School ID
        $setting = RequirementSetting::create([
            'school_id' => $finalSchoolId,
            'course_name' => $request->course_name,
            'required_hours' => $request->required_hours
        ]);

        return response()->json([
            'message' => 'Requirement rule added successfully!',
            'setting' => $setting
        ], 201);
    }

    /**
     * ✨ NEW: Update an existing requirement rule ✨
     */
    public function updateRequirement(Request $request, $id)
    {
        // 1. Find the specific requirement
        $requirement = RequirementSetting::find($id);

        if (!$requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        // 2. Validate the incoming data 
        // ('sometimes' means it will only validate the field if React actually sends it in the request)
        $validatedData = $request->validate([
            'school_id' => 'sometimes|required|integer',
            'course_name' => 'sometimes|required|string|max:255',
            'required_hours' => 'sometimes|required|integer|min:1'
        ]);

        // 3. Update the record in the database
        $requirement->update($validatedData);

        // 4. Return a success response back to React
        return response()->json([
            'message' => 'Requirement updated successfully!',
            'data' => $requirement
        ], 200);
    }

    public function deleteRequirement($id)
    {
        $setting = RequirementSetting::findOrFail($id);
        $setting->delete();

        return response()->json(['message' => 'Rule deleted successfully']);
    }
}