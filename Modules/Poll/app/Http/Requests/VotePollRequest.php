<?php

declare(strict_types=1);

namespace Modules\Poll\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;

class VotePollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            $this->fail('You must be logged in to vote.');
            return false;
        }

        if (!$user->isResident()) {
            $this->fail('Only residents can vote in polls.');
            return false;
        }

        $pollId = $this->route('pollId');
        if (!$pollId) {
            $this->fail('Poll ID is required.');
            return false;
        }

        $poll = Poll::find($pollId);
        if (!$poll) {
            $this->fail('The requested poll does not exist.');
            return false;
        }

        if ($poll->status !== 'active') {
            $this->fail('This poll is not currently active. Status: ' . $poll->status);
            return false;
        }

        if ($poll->ends_at && $poll->ends_at->isPast()) {
            $this->fail('This poll has expired. It ended on ' . $poll->ends_at->format('Y-m-d H:i'));
            return false;
        }

        $resident = $user->currentResident;
        if (!$resident) {
            $this->fail('You do not have an active residency in this community.');
            return false;
        }

        if ($resident->community_id !== $poll->community_id) {
            $this->fail('You are not a resident of the community where this poll was created.');
            return false;
        }

        $existingVote = \Modules\Poll\app\Models\PollVote::where('poll_id', $poll->id)
            ->where('voter_id', $resident->id)
            ->exists();

        if ($existingVote) {
            $this->fail('You have already voted in this poll.');
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $pollId = $this->route('pollId');
        $poll = $pollId ? Poll::find($pollId) : null;

        return [
            'option_id' => [
                'required',
                'integer',
                'exists:poll_options,id',
                function ($attribute, $value, $fail) use ($poll) {
                    if (!$poll) {
                        $fail('The poll does not exist.');
                        return;
                    }

                    $optionExists = PollOption::where('id', $value)
                        ->where('poll_id', $poll->id)
                        ->exists();

                    if (!$optionExists) {
                        $fail('The selected option does not belong to this poll.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'option_id.required' => 'You must select an option.',
            'option_id.integer' => 'Invalid option format.',
            'option_id.exists' => 'The selected option does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('option_id') && is_array($this->option_id)) {
            $this->merge([
                'option_id' => $this->option_id[0] ?? null
            ]);
        }

        if ($this->has('option_id') && is_string($this->option_id)) {
            $decoded = json_decode($this->option_id, true);
            if (is_array($decoded)) {
                $this->merge([
                    'option_id' => $decoded[0] ?? null
                ]);
            }
        }
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        // ✅ إذا فشل الـ authorize، نرمي ValidationException مع الرسالة
        $message = session('auth_error') ?? 'You are not authorized to vote in this poll.';
        throw ValidationException::withMessages(['auth' => $message]);
    }

    private function fail(string $message): void
    {
        session(['auth_error' => $message]);
    }
}
