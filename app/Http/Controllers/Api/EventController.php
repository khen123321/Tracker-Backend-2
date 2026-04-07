<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    /**
     * Fetch all events for the calendar.
     */
    public function index()
    {
        // We map the database columns to what FullCalendar expects (id, title, start)
        $events = Event::all()->map(function ($event) {
            return [
                'id'    => $event->id,
                'title' => $event->title,
                // If there's a time, we combine it: 2026-04-07T10:00:00
                'start' => $event->time ? "{$event->date}T{$event->time}" : $event->date,
                'extendedProps' => [
                    'description' => $event->description,
                    'type'        => $event->type,
                ],
                'backgroundColor' => $this->getEventColor($event->type),
                'borderColor'     => $this->getEventColor($event->type),
            ];
        });

        return response()->json($events);
    }

    /**
     * Store a new event (HR only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'date'        => 'required|date',
            'time'        => 'nullable',
            'type'        => 'required|in:holiday,meeting,deadline,other',
            'description' => 'nullable|string',
        ]);

        // Create the event and link it to the logged-in HR user
        $event = Event::create([
            'title'       => $validated['title'],
            'date'        => $validated['date'],
            'time'        => $validated['time'] ?? null,
            'type'        => $validated['type'],
            'description' => $validated['description'] ?? '',
            'created_by'  => Auth::id(), // Automatically gets the ID of the HR admin
        ]);

        return response()->json([
            'message' => 'Event created successfully!',
            'event'   => $event
        ], 201);
    }

    /**
     * Helper to set calendar colors based on event type
     */
    private function getEventColor($type)
    {
        return match($type) {
            'holiday'  => '#ef4444', // Red
            'meeting'  => '#3b82f6', // Blue
            'deadline' => '#f59e0b', // Amber
            default    => '#64748b', // Slate
        };
    }
}