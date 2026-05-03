<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;           
use App\Models\Notification;   
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    /**
     * 1. GET ALL ANNOUNCEMENTS
     * Used by both Interns and HR to view the feed.
     */
    public function index()
    {
        $announcements = Announcement::with('creator:id,first_name,last_name')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($announcements);
    }

    /**
     * 2. CREATE A NEW ANNOUNCEMENT
     * Used by HR to post a new notice.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'required|in:event,holiday,reminder,general',
            'expiry_date' => 'nullable|date',
            'is_pinned' => 'boolean'
        ]);

        $announcement = new Announcement($validated);
        $announcement->created_by = Auth::id(); // Automatically tag the logged-in HR
        $announcement->save();

        // ✨ THE NEW NOTIFICATION LOGIC (WITH EXCLUSION) ✨
        // Get all active interns, but explicitly exclude the person currently making the post
        $interns = User::where('role', 'like', 'intern%')
                       ->where('id', '!=', Auth::id())
                       ->get();
        
        // Loop through them and drop a notification in their bell
        foreach ($interns as $intern) {
            Notification::create([
                'user_id' => $intern->id,
                'title' => 'New Announcement',
                'message' => $announcement->title,
                'type' => 'info', // 'info' makes it a blue icon in your React frontend
                'read_at' => null,
                // Passing the data payload so your React app can read the title/details
                'data' => json_encode([
                    'title' => $announcement->title,
                    'message' => 'Check your dashboard for new announcements.',
                    'announcement_id' => $announcement->id
                ])
            ]);
        }

        return response()->json([
            'message' => 'Announcement published successfully!', 
            'announcement' => $announcement
        ], 201);
    }

    /**
     * 3. UPDATE AN EXISTING ANNOUNCEMENT
     */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:event,holiday,reminder,general',
            'expiry_date' => 'nullable|date',
            'is_pinned' => 'boolean'
        ]);

        $announcement->update($validated);

        return response()->json([
            'message' => 'Announcement updated successfully!', 
            'announcement' => $announcement
        ]);
    }

    /**
     * 4. DELETE AN ANNOUNCEMENT
     */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted successfully!']);
    }

    /**
     * 5. MARK AS READ (INTERN ACTION)
     */
    public function markAsRead($id)
    {
        $announcement = Announcement::findOrFail($id);
        $user = Auth::user();

        $announcement->readers()->syncWithoutDetaching([
            $user->id => ['read_at' => now()]
        ]);

        return response()->json(['message' => 'Announcement marked as read.']);
    }

    /**
     * 6. PIN AN ANNOUNCEMENT (HR ACTION)
     */
    public function pin($id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            
            $announcement->is_pinned = true;
            $announcement->save();

            return response()->json([
                'message' => 'Announcement pinned successfully!',
                'announcement' => $announcement
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to pin announcement.'], 500);
        }
    }

    /**
     * 7. UNPIN AN ANNOUNCEMENT (HR ACTION)
     */
    public function unpin($id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            
            $announcement->is_pinned = false;
            $announcement->save();

            return response()->json([
                'message' => 'Announcement unpinned successfully!',
                'announcement' => $announcement
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to unpin announcement.'], 500);
        }
    }
}