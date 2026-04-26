<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern; 
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InternController extends Controller
{
    /**
     * Display a listing of all interns (For HR Dashboard).
     */
    public function index()
    {
        try {
            $interns = User::where('role', 'intern')
                ->with(['intern', 'school', 'branch', 'department'])
                ->get();
            return response()->json($interns, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to fetch interns list.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified intern profile (For HR Drawer or Intern Profile)
     */
    public function show($id)
    {
        if ($id === 'me') { $id = auth('sanctum')->id(); }
        if (!$id) { return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401); }

        try {
            $intern = Intern::with(['user.attendance_logs', 'school', 'branch', 'department'])
                ->where('id', $id)
                ->orWhere('user_id', $id)
                ->firstOrFail();

            $totalHours = 0;
            if ($intern->user && $intern->user->relationLoaded('attendance_logs')) {
                $totalHours = $intern->user->attendance_logs->sum('hours_rendered');
            }
            $intern->setAttribute('attendance_logs_sum_hours_rendered', $totalHours);

            return response()->json(['status' => 'success', 'intern' => $intern ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Intern not found in the database.'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Server Crash: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Fetch specific intern with relations for HR
     */
    public function showForHr($id)
    {
        try {
            $intern = Intern::with(['user', 'school', 'department', 'branch'])
                ->where('id', $id)
                ->orWhere('user_id', $id)
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'intern' => $intern
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Intern not found in the database.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch intern profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Avatar Function
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $avatarUrl = url('storage/' . $path);

            if ($user->intern) {
                $user->intern()->update(['avatar_url' => $avatarUrl]);
            } else {
                $user->update(['avatar_url' => $avatarUrl]);
            }

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'avatar_url' => $avatarUrl
            ], 200);
        }

        return response()->json(['message' => 'No file provided'], 400);
    }

    /**
     * Upload Documents Function (With Custom Naming)
     */
    public function uploadDocument(Request $request)
    {
        ob_start();
        try {
            if (!$request->hasFile('document')) { return response()->json(['error' => 'No file found'], 400); }

            $file = $request->file('document');
            $user = $request->user();
            $type = $request->document_type;

            if (!$user || !$user->intern || !$type) { return response()->json(['error' => 'Invalid request'], 400); }

            $internId = $user->intern->id;
            $extension = $file->getClientOriginalExtension();
            $filename = "intern_{$internId}_{$type}.{$extension}";

            $existingFiles = Storage::disk('public')->files('documents');
            foreach ($existingFiles as $existingFile) {
                if (str_starts_with(basename($existingFile), "intern_{$internId}_{$type}.")) {
                    Storage::disk('public')->delete($existingFile);
                }
            }

            $path = $file->storeAs('documents', $filename, 'public');
            $user->intern()->update(['has_' . $type => true]);

            ob_end_clean();
            return response()->json(['message' => 'Success', 'path' => $path]);
        } catch (\Throwable $e) {
            ob_end_clean();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * View/Download Document securely
     */
    public function viewDocument($id, $type)
    {
        try {
            $intern = Intern::where('id', $id)->orWhere('user_id', $id)->firstOrFail();
            $internId = $intern->id;

            $allowedTypes = ['resume', 'moa', 'endorsement', 'nda', 'pledge'];
            if (!in_array($type, $allowedTypes)) { return response()->json(['error' => 'Invalid document type'], 400); }

            $files = Storage::disk('public')->files('documents');
            $matchedFile = null;

            foreach ($files as $file) {
                if (str_starts_with(basename($file), "intern_{$internId}_{$type}.")) {
                    $matchedFile = $file;
                    break;
                }
            }

            if (!$matchedFile) { return response()->json(['error' => 'Document not found on server'], 404); }

            $fullPath = storage_path('app/public/' . $matchedFile);
            return response()->file($fullPath, [
                'Content-Type' => mime_content_type($fullPath),
                'Content-Disposition' => 'inline; filename="'.basename($fullPath).'"'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Fetch statistics for the HR Dashboard (Interns by School)
     * Hardened version based on raw database dump.
     */
    public function getSchoolStats()
    {
        try {
            // Grab all interns directly
            $stats = Intern::all()
                ->groupBy(function($intern) {
                    
                    // 1. Safely check if the "school" text column has a value (like your Row #1)
                    $schoolText = $intern->getAttributes()['school'] ?? null;
                    if (!empty($schoolText) && is_string($schoolText)) {
                        return $schoolText;
                    }

                    // 2. If text is NULL, check the "school_id" column (like your Rows #19-22)
                    if (!empty($intern->school_id)) {
                        // Try to find the actual name in the schools table
                        $schoolRecord = \App\Models\School::find($intern->school_id);
                        if ($schoolRecord && !empty($schoolRecord->name)) {
                            return $schoolRecord->name;
                        }
                        
                        // 3. FAILSAFE: If school_id is 1 but the schools table is empty/broken
                        if ($intern->school_id == 1) {
                            return 'USTP'; 
                        }
                    }

                    // 4. Truly empty
                    return 'Unassigned / Other';
                })
                ->map(function($group, $schoolName) {
                    return [
                        'name' => $schoolName,
                        'value' => $group->count()
                    ];
                })
                // 👉 HIDE the unassigned ones so the chart looks clean
                ->reject(function($item) {
                    return $item['name'] === 'Unassigned / Other';
                })
                // Sort by highest count first, then re-index the array
                ->sortByDesc('value')
                ->values(); 

            return response()->json($stats, 200);

        } catch (\Exception $e) {
            Log::error('Dashboard School Stats Error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return response()->json([
                'status' => 'error', 
                'message' => 'Failed to fetch school stats.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}