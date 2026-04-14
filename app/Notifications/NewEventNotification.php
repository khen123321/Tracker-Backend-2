<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewEventNotification extends Notification
{
    use Queueable;
    private $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    // Tell Laravel to save this in the database
    public function via($notifiable)
    {
        return ['database']; 
    }

    // The actual data that will show up in the React frontend
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'event',
            'event_id' => $this->event->id,
            'title' => 'New Event: ' . $this->event->title,
            'message' => 'Scheduled for ' . \Carbon\Carbon::parse($this->event->date)->format('M d, Y'),
        ];
    }
}