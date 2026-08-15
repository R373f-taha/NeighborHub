<?php


namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;

class ResidentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Community $community;
    protected Resident $resident;

    public function __construct(Community $community, Resident $resident)
    {
        $this->community = $community;
        $this->resident = $resident;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database']; // Store in database only 
    }

    /**
     * Get the database notification representation.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'resident_approved',
            'title' => '✅ Your membership has been approved',
            'message' => "Congratulations! Your request to join '{$this->community->name}' community has been approved.",
            'community_id' => $this->community->id,
            'community_name' => $this->community->name,
            'resident_id' => $this->resident->id,
            'approved_at' => now()->toDateTimeString(),
            'action_url' => "/api/v1/communities/{$this->community->id}/dashboard",
        ];
    }


}
