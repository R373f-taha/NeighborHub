<?php

namespace Modules\Poll\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Events\PollClosed;
use Modules\Poll\Database\Factories\PollFactory;

class Poll extends Model
{ use HasFactory;
    protected $fillable = [
        'community_id', 'created_by', 'title', 'description', 'type',
        'status', 'ends_at', 'activated_at', 'closed_at',    // 'closed_by_manager'
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return PollFactory::new();
    }
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by_manager');
    }

    public function options()
    {
        return $this->hasMany(PollOption::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }



    /**
     * Scope a query to only include draft polls.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', PollStatus::Draft);
    }

    /**
     * Scope a query to include expired polls (ends_at < now).
     */
 public function scopeActive($query)
    {
        return $query->where('status', PollStatus::Active);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', PollStatus::Closed);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', PollStatus::Active)
            ->where('ends_at', '<=', now());
    }
    /**
     * Check if the poll is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if the poll is closed.
     */
    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * Check if the poll is draft.
     */
    public function isDraft(): bool
    {
        return $this->status->isDraft();
    }

    /**
     * Check if the poll has expired.
     */
    public function isExpired(): bool
    {
        return $this->ends_at < now();
    }

    /**
     * Get total votes count.
     */
    public function getTotalVotesCount(): int
    {
        return $this->votes()->count();
    }

    /**
     * Get turnout percentage.
     */
    public function getTurnoutPercentage(): float
    {
        $totalResidents = $this->community->residents()->where('status', 'active')->count();

        if ($totalResidents === 0) {
            return 0.0;
        }

        return round(($this->getTotalVotesCount() / $totalResidents) * 100, 2);
    }

    /**
     * Get vote count for a specific option.
     */
    public function getVoteCountForOption(int $optionId): int
    {
        return PollVote::where('poll_id', $this->id)
            ->where('poll_option_id', $optionId)
            ->count();
    }



}
