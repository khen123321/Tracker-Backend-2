<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\School;
use App\Models\Intern;
// use App\Models\Notification; <-- We don't need this anymore
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str; // ✨ ADDED THIS FOR UUID GENERATION

class EventController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

            $query = Event::query();

            // Check if the logged-in user is a standard intern
            if ($user->role === 'intern') {
                
                // 1. Get the intern profile
                $intern = Intern::where('user_id', $user->id)->first();

                // 2. Find the School Name (Try Intern table first, then User table)
                $schoolId = $intern->school_id ?? $user->school_id;
                
                $schoolRecord = School::find($schoolId);
                $userSchoolName = $schoolRecord ? trim($schoolRecord->name) : null;
                
                // 3. Get the Course (From Intern table)
                $userCourse = $intern ? trim($intern->course) : null;

                $query->where(function($q) use ($userSchoolName, $userCourse) {
                    // Always show 'all' and 'intern' targeted events
                    $q->whereIn('audience', ['all', 'intern'])
                    
                      // Match School Name
                      ->orWhere(function($subQ) use ($userSchoolName) {
                          if ($userSchoolName) {
                              $subQ->where('audience', 'school')
                                   ->where('school', $userSchoolName);
                          } else {
                              $subQ->whereRaw('1 = 0'); // Force fail if no school found
                          }
                      })
                      
                      // Match Course Name
                      ->orWhere(function($subQ) use ($userCourse) {
                          if ($userCourse) {
                              $subQ->where('audience', 'course')
                                   ->where('course', $userCourse);
                          } else {
                              $subQ->whereRaw('1 = 0');
                          }
                      })
                      
                      // Match Both
                      ->orWhere(function($subQ) use ($userSchoolName, $userCourse) {
                          if ($userSchoolName && $userCourse) {
                              $subQ->where('audience', 'both')
                                   ->where('school', $userSchoolName)
                                   ->where('course', $userCourse);
                          } else {
                              $subQ->whereRaw('1 = 0');
                          }
                      });
                });
            } elseif ($user->role === 'hr_intern') {
                // If the user is an HR Intern, only show 'all' and 'hr_intern' events
                $query->whereIn('audience', ['all', 'hr_intern']);
            }

            $events = $query->get()->map(function ($event) {
                return [
                    'id'    => $event->id,
                    'title' => $event->title,
                    'start' => $event->time ? "{$event->date}T{$event->time}" : $event->date,
                    'extendedProps' => [
                        'description' => $event->description,
                        'type'        => $event->type,
                        'location'    => $event->location,
                        'audience'    => $event->audience,
                        'school'      => $event->school,
                        'course'      => $event->course,
                        'time'        => $event->time,
                        'is_pinned'   => (bool) $event->is_pinned, 
                    ],
                    'backgroundColor' => $this->getEventColor($event->type),
                    'borderColor'     => $this->getEventColor($event->type),
                ];
            });

            return response()->json($events);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getFilters()
    {
        try {
            // 1. Fetch Schools and correctly count standard interns from the relationship
            $schools = School::all()->map(function($schoolObj) {
                return [
                    'id'     => $schoolObj->id,
                    'school' => $schoolObj->name,
                    'total'  => User::where('role', 'intern')
                                     ->whereHas('intern', function($q) use ($schoolObj) {
                                         $q->where('school_id', $schoolObj->id);
                                     })->count()
                ];
            });

            // 2. Fetch Courses directly from the correct HR Utilities table
            $courses = [];
            
            // Safety check with the EXACT table name
            if (Schema::hasTable('requirement_settings')) {
                // Grab unique course names that HR has typed in
                $courses = DB::table('requirement_settings')
                    ->select('course_name')
                    ->distinct()
                    ->whereNotNull('course_name')
                    ->where('course_name', '!=', '') 
                    ->get()
                    ->map(function($req, $index) {
                        return [
                            'id'     => $index + 1, // Fake ID for React keys
                            'course' => trim($req->course_name),
                            'total'  => User::where('role', 'intern')
                                             ->whereHas('intern', function($q) use ($req) {
                                                 $q->where('course', trim($req->course_name));
                                             })->count()
                        ];
                    });
            }

            return response()->json([
                'schools' => $schools,
                'courses' => $courses,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'       => 'required|string',
                'date'        => 'required|date',
                'audience'    => 'required|in:all,school,course,both,intern,hr_intern',
                'school'      => 'nullable|string',
                'course'      => 'nullable|string',
                'type'        => 'required|string',
                'location'    => 'nullable|string',
                'description' => 'nullable|string',
                'time'        => 'nullable',
                'is_pinned'   => 'boolean' 
            ]);

            // Clean up payload based on audience selection
            if ($validated['audience'] === 'all' || $validated['audience'] === 'intern' || $validated['audience'] === 'hr_intern') {
                $validated['school'] = null;
                $validated['course'] = null;
            } elseif ($validated['audience'] === 'school') {
                $validated['course'] = null;
            } elseif ($validated['audience'] === 'course') {
                $validated['school'] = null;
            }

            // Trim strings before saving to database to prevent hidden space bugs
            if (!empty($validated['school'])) $validated['school'] = trim($validated['school']);
            if (!empty($validated['course'])) $validated['course'] = trim($validated['course']);

            // Capture current user ID
            $currentUserId = Auth::id();

            // Create the Event
            $event = Event::create(array_merge($validated, ['created_by' => $currentUserId]));

            // ✨ THE BULLETPROOF NOTIFICATION QUERY ✨
            // 1. Start the query by IMMEDIATELY blocking the current user from being fetched
            $query = User::where('id', '!=', $currentUserId);

            // 2. Add the role filters safely inside a grouped where clause
            $query->where(function($q) use ($validated) {
                if ($validated['audience'] === 'hr_intern') {
                    $q->where('role', 'hr_intern'); 
                } else {
                    $q->where('role', 'intern'); 
                }
            });

            // 3. Filter the interns by their specific profile data if not for "Everyone"
            if (!in_array($validated['audience'], ['all', 'intern', 'hr_intern'])) {
                $query->whereHas('intern', function($internQuery) use ($validated) {
                    
                    // Match the exact School Name using the relationship
                    if (in_array($validated['audience'], ['school', 'both'])) {
                        $internQuery->whereHas('school', function($schoolQuery) use ($validated) {
                            $schoolQuery->where('name', $validated['school']);
                        });
                    }
                    
                    // Match the exact Course Name inside the intern table
                    if (in_array($validated['audience'], ['course', 'both'])) {
                        $internQuery->where('course', $validated['course']);
                    }
                });
            }

            // Fetch the matched users and insert directly into the notifications table
            $usersToNotify = $query->get();

            foreach ($usersToNotify as $user) {
                // 🔥 THE ULTIMATE FAILSAFE 🔥
                // 1. If the user is the one who clicked "Post", SKIP THEM.
                // 2. If the user is an HR/Admin (just in case they slipped into the query), SKIP THEM.
                if ($user->id == $currentUserId || in_array(strtolower($user->role), ['hr', 'admin', 'superadmin'])) {
                    continue; 
                }

                // ✨ FIXED: Using DB facade to adhere to Laravel's standard notifications table schema
                DB::table('notifications')->insert([
                    'id'              => Str::uuid(),
                    'type'            => 'App\Notifications\NewEventAlert',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $user->id,
                    'data'            => json_encode([
                        'type'       => 'info',
                        'title'      => 'New ' . ucfirst($validated['type']) . ': ' . $event->title,
                        'message'    => 'Check your calendar for details.',
                        'event_id'   => $event->id
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            return response()->json($event, 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            Event::findOrFail($id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete event'], 500);
        }
    }

    public function pin($id)
    {
        try {
            $event = Event::findOrFail($id);
            $event->is_pinned = true;
            $event->save();
            return response()->json(['message' => 'Event pinned successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to pin event'], 500);
        }
    }

    public function unpin($id)
    {
        try {
            $event = Event::findOrFail($id);
            $event->is_pinned = false;
            $event->save();
            return response()->json(['message' => 'Event unpinned successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to unpin event'], 500);
        }
    }

    private function getEventColor($type)
    {
        return match(strtolower($type)) {
            'holiday'  => '#152286', // Blue
            'reminder' => '#22c55e', // Green
            default    => '#eab308', // Event (Yellow)
        };
    }
}