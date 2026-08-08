<?php

declare(strict_types=1);

namespace Modules\Auth\app\Models;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\Database\Factories\UserFactory;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Issue\app\Models\Issue;
use Modules\Media\app\Models\Media;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;
use Modules\Notification\app\Models\Notification;
use Modules\Poll\app\Models\Poll;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */

    use HasApiTokens,HasRoles, HasFactory, Notifiable;

    /**
     * Spatie RBAC authority guard for this model.
     *
     * Pinned to the `api` guard. API routes authenticate through the `sanctum`
     * guard, which shifts the runtime default auth guard; without a pinned
     * guard Spatie would resolve roles/permissions under that shifted guard and
     * authorization would silently fail. All RBAC data lives under `api`.
     */
    protected string $guard_name = 'api';

    /**
     * Safe profile attributes that may be mass assigned.
     *
     * Role and active status are intentionally excluded to prevent privileged
     * mass assignment from public request data. Factories, seeders, and future
     * actions may assign them explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isResident(): bool
    {
        return $this->role === UserRole::Resident;
    }

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    /**
     * The user's resident record (residents.user_id is unique).
     */
    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    /**
     * The user's resident record when it is flagged as the current residency.
     */
    public function currentResident(): HasOne
    {
        return $this->hasOne(Resident::class)->where('current_marker', true);
    }

    /**
     * Communities this user manages via the community_mangers pivot.
     */
    public function managedCommunities(): BelongsToMany
    {
        return $this->belongsToMany(
            Community::class,
            'community_mangers',
            'manager_id',
            'community_id'
        );
    }

    public function reportedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'reported_by');
    }

    public function assignedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'assigned_to');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Custom NeighborHub in-app notifications (notifications.user_id).
     *
     * Named explicitly to avoid shadowing the polymorphic notifications()
     * relationship provided by the Notifiable trait.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function uploadedMedia(): HasMany
    {
        return $this->hasMany(Media::class, 'uploaded_by');
    }

    public function authoredComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function createdPolls(): HasMany
    {
        return $this->hasMany(Poll::class, 'created_by');
    }

    /**
     * Polls closed by this manager.
     *
     * Uses the actual migration column "colsed_by_manager" (misspelled in the
     * schema). The spelling must not be "corrected" here without a matching
     * schema migration; see remaining risks.
     */
    public function closedPolls(): HasMany
    {
        return $this->hasMany(Poll::class, 'colsed_by_manager');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by_manager');
    }

}
