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
        $request->validate([
            'action' => 'required|in:approve,reject'
        ]);

        try {
            // Find the request
            $internRequest = InternRequest::findOrFail($id);
            
            // 1. Update the Status
            if ($request->action === 'approve') {
                $internRequest->status = 'Approved';
            } else {
                $internRequest->status = 'Rejected';
            }
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
}