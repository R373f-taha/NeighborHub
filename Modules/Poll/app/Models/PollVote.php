<?php

namespace Modules\Poll\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Community\Models\Resident;

class PollVote extends Model
{
    // بما أن الميغريشن ما فيها timestamps، نضيفها يدوياً أو نستخدم $timestamps = true
    // لكن الأفضل إضافة timestamps في الميغريشن. حالياً خليناها true عشان نضمن التوافق.
    public $timestamps = true;

    protected $fillable = ['poll_id', 'option_id', 'voter_id', 'submitted_at', 'voted_at'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'voted_at' => 'date',
    ];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'option_id');
    }

    public function voter()
    {
        return $this->belongsTo(Resident::class, 'voter_id');
    }
}
