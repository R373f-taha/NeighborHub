<?php

namespace Modules\Auth\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Announcement\app\Models\Announcement as ModelsAnnouncement;
use Modules\Announcement\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident as ModelsResident;
use Modules\Community\Models\CommunityManager;
use Modules\Community\Models\Resident;
use Modules\Interaction\Models\Comment;
use Modules\Interaction\Models\Reaction;
use Modules\Issue\Models\Issue;
use Modules\Media\Models\Media;
use Modules\Messaging\Models\ConversationParticipant;
use Modules\Messaging\Models\Message;
use Modules\Notification\Models\Notification;
use Modules\Poll\Models\Poll;
use Modules\Poll\Models\PollVote;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'avatar', 'is_active', 'email_verified_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];


    public function currentResident()
    {
        return $this->hasOne(\Modules\Community\app\Models\Resident::class)->where('current_marker', true);
    }

    public function residents()
    {
        return $this->hasOne(ModelsResident::class);
    }

    public function managedCommunities()
    {
        return $this->belongsToMany(Community::class);
    }

    public function reportedIssues()
    {
        return $this->hasMany(Issue::class, 'reported_by');
    }

    public function assignedIssues()
    {
        return $this->hasMany(Issue::class, 'assigned_to');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function uploadedMedia()
    {
        return $this->hasMany(Media::class, 'uploaded_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'author_id');
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }

    public function createdPolls()
    {
        return $this->hasMany(Poll::class, 'created_by');
    }

    public function closedPolls()
    {
        return $this->hasMany(Poll::class, 'closed_by_manager');
    }

     public function announcements()
    {
        return $this->hasMany(ModelsAnnouncement::class, 'created_by_manager');
    }

    public function isSuperAdmin() { return $this->role === 'super_admin'; }
    public function isManager() { return $this->role === 'manager'; }
    public function isResident() { return $this->role === 'resident'; }
    public function isProvider() { return $this->role === 'provider'; }
}
