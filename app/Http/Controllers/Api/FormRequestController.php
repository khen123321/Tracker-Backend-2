<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternRequest;
use App\Models\User;
use App\Notifications\NewFormRequestAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage; // ✨ ADDED FOR FILE UPLOADS

class FormRequestController extends Controller
{
    /**
     * ✨ HR: Fetch all requests for the HR Dashboard ✨
     * (NEW FUNCTION ADDED FOR THE REACT DASHBOARD)
     */
    public function index()
    {
        try {
            // Fetch all requests and map them so the data structure matches 
            // what the React frontend expects (e.g., mapping user to intern.user)
            $requests = InternRequest::orderBy('created_at', 'desc')->get()->map(function($req) {
                $user = User::find($req->user_id);
                return [
                    'id'                 => $req->id,
                    'type'               => $req->type,
                    'target_date'        => $req->date_of_absence, // Mapped for React
                    'reason'             => $req->reason,
                    'additional_details' => $req->additional_details,
                    'status'             => strtolower($req->status), // Ensure lowercase for frontend badge classes
                    'attachment_path'    => $req->attachment_path,
                    // Mocking the 'intern.user' structure so the frontend table doesn't crash
                    'intern' => [
                        'user' => [
                            'first_name' => $user ? $user->first_name : 'Unknown',
                            'last_name'  => $user ? $user->last_name : '',
                        ]
                    ]
                ];
            });

            return response()->json(['data' => $requests]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✨ INTERN: Submits a Form Request (NOW WITH ATTACHMENTS) ✨
     */
    public function store(Request $request)
    {
        // 1. Validate the incoming data (including the optional file)
        $validated = $request->validate([
            'type' => 'required|string',
            'date_of_absence' => 'required|date',
            'reason' => 'required|string',
            'additional_details' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120', // Max 5MB
        ]);

        // 2. Handle the File Upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            // This saves the file into storage/app/public/form_attachments
            $attachmentPath = $request->file('attachment')->store('form_attachments', 'public');
        }

        // 3. Create the Database Record
        $internRequest = InternRequest::create([
            'user_id' => Auth::id(), 
            'type' => $validated['type'],
            'date_of_absence' => $validated['date_of_absence'],
            'reason' => $validated['reason'],
            'additional_details' => $request->additional_details,
            'attachment_path' => $attachmentPath, // ✨ SAVES THE PATH HERE
            'status' => 'Pending',
        ]);

        // 4. Alert HR
        $admins = User::whereIn('role', ['hr', 'admin', 'superadmin'])->get();
        if (Auth::user()) {
            Notification::send($admins, new NewFormRequestAlert($internRequest, Auth::user()));
        }

        return response()->json([
            'message' => 'Form submitted successfully!',
            'data' => $internRequest
        ], 201);
    }

    /**
     * ✨ HR: Fetch full details of a single request for the Popup ✨
     */
    public function show($id)
    {
        try {
            $internRequest = InternRequest::findOrFail($id);
            
            // Look up the user's name manually (or use relationships if you have them)
            $targetUser = User::find($internRequest->user_id);

            return response()->json([
                'id' => $internRequest->id,
                // ✨ FIXED: Concatenating first_name and last_name ✨
                'intern_name' => $targetUser ? trim($targetUser->first_name . ' ' . $targetUser->last_name) : 'Unknown Intern',
                'type' => $internRequest->type,
                'date_of_absence' => \Carbon\Carbon::parse($internRequest->date_of_absence)->format('M d, Y'),
                'reason' => $internRequest->reason,
                'additional_details' => $internRequest->additional_details,
                'status' => $internRequest->status,
                'attachment_path' => $internRequest->attachment_path
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Request not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * ✨ HR: Approves or Rejects the Form ✨
     */
    public function processRequest(Request $request, $id)
    {
        // Support both old 'action' payload and new 'status' payload
        $request->validate([
            'action' => 'nullable|in:approve,reject',
            'status' => 'nullable|in:approved,rejected'
        ]);

        try {
            // Find the request
            $internRequest = InternRequest::findOrFail($id);
            
            // 1. Update the Status
            if ($request->status) {
                // Read from new React Payload
                $internRequest->status = ucfirst($request->status); // 'Approved' or 'Rejected'
            } else {
                // Read from legacy payload
                $internRequest->status = $request->action === 'approve' ? 'Approved' : 'Rejected';
            }
            
            // Note: If you want to save $request->remarks to the database, 
            // you would need to add an 'hr_remarks' column to your intern_requests table.
            
            $internRequest->save();

            // 2. ✨ THE NUCLEAR FIX: Force the notification straight into the database ✨
            $targetUser = User::find($internRequest->user_id);

            if ($targetUser) {
                $status = $internRequest->status;
                $date = \Carbon\Carbon::parse($internRequest->date_of_absence)->format('M d, Y');
                
                $message = $status === 'Approved' 
                    ? "Your request for {$date} has been approved."
                    : "Your request for {$date} has been rejected by HR.";

                // Bypass the Notification class and write directly to the table
                DB::table('notifications')->insert([
                    'id' => Str::uuid(),
                    'type' => 'App\Notifications\InternRequestProcessedAlert',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $targetUser->id,
                    'data' => json_encode([
                        'type' => 'request_update',
                        'title' => "Request {$status}",
                        'message' => $message,
                        'request_id' => $internRequest->id,
                        'date_of_absence' => $internRequest->date_of_absence,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
            } else {
                throw new \Exception("User ID missing from this form.");
            }

            return response()->json(['message' => "BANANA {$internRequest->status} successfully!"]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✨ HR: Download attached file ✨
     * (NEW FUNCTION ADDED FOR THE REACT DASHBOARD)
     */
    public function download($id)
    {
        try {
            $internRequest = InternRequest::findOrFail($id);
            
            if (!$internRequest->attachment_path) {
                return response()->json(['message' => 'No file found'], 404);
            }

            return Storage::disk('public')->download($internRequest->attachment_path);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error downloading file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}