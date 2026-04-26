<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternRequestProcessedAlert extends Notification
{
    use Queueable;

    protected $internRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct($internRequest)
    {
        $this->internRequest = $internRequest;
    }

    /**
     * We only want to save this to the database so it shows up in their Notification Bell.
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        $status = $this->internRequest->status;
        $date = \Carbon\Carbon::parse($this->internRequest->date_of_absence)->format('M d, Y');
        
        // Format the message based on approval or rejection
        $message = $status === 'Approved' 
            ? "Your request for {$date} has been approved."
            : "Your request for {$date} has been rejected by HR.";

        return [
            'type' => 'request_update',
            'title' => "Request {$status}",
            'message' => $message,
            'request_id' => $this->internRequest->id,
            'date_of_absence' => $this->internRequest->date_of_absence,
        ];
    }
} 