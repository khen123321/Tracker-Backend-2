<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewEventNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB; 

class EventController extends Controller
{
    public function index()
    {
        $events = Event::all()->map(function ($event) {
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
                ],
                'backgroundColor' => $this->getEventColor($event->type),
                'borderColor'     => $this->getEventColor($event->type),
            ];
        });
        return response()->json($events);
    }

    /**
     * ✅ DYNAMIC FILTERS: Accurate counts for your dashboard
     */
    public function getFilters()
    {
        // 1. Fetch Schools (Case Insensitive)
         // Example change in getFilters()
$schools = User::where('role', 'LIKE', 'intern%')
    ->whereNotNull('school_university')
    ->select('school_university as school', DB::raw('count(*) as total'))
    ->groupBy('school_university')
    ->get();

        // 2. Fetch Courses (Case Insensitive)
        $courses = User::where('role', 'LIKE', 'intern%')
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->select('course', DB::raw('count(*) as total'))
            ->groupBy('course')
            ->get();

        return response()->json([
            'schools' => $schools,
            'courses' => $courses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string',
            'date'        => 'required|date',
            'audience'    => 'required|in:all,school,course,both,intern,hr_intern',
            'school'      => 'nullable|string',
            'course'      => 'nullable|string',
            'type'        => 'required|string',
            'location'    => 'nullable|string',
            'description' => 'nullable|string',
            'time'        => 'nullable'
        ]);

        $event = Event::create(array_merge($validated, ['created_by' => Auth::id()]));

        // Notification Logic
        $query = User::where('role', '!=', 'superadmin');
        
        if ($validated['audience'] === 'school') {
            $query->where('school', $validated['school']);
        } elseif ($validated['audience'] === 'course') {
            $query->where('course', $validated['course']);
        } elseif ($validated['audience'] === 'both') {
            $query->where('school', $validated['school'])
                  ->where('course', $validated['course']);
        }

        $users = $query->get();
        if ($users->count() > 0) {
            Notification::send($users, new NewEventNotification($event));
        }

        return response()->json($event, 201);
    }

    public function destroy($id)
    {
        Event::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function getEventColor($type)
    {
        return match(strtolower($type)) {
            'holiday' => '#ef4444', 
            'meeting' => '#3b82f6', 
            default   => '#0B1EAE', 
        };
    }
}