<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The form sends a random submission_id, new for each record. A second request with the
 * same id (a double tap, a retried request) is recognised and not saved again.
 */
trait PreventsDuplicateSubmission
{
    /** @return array<string, array<int, string>> */
    protected function submissionRules(): array
    {
        return ['submission_id' => ['required', 'string', 'max:64']];
    }

    /**
     * Runs $save only for the first request carrying this submission_id; returns false for a repeat.
     * If $save fails (e.g. a balance check), the id is released so the corrected form can be sent again.
     */
    public function once(Closure $save): bool
    {
        $key = 'submission:'.$this->user()->id.':'.$this->input('submission_id');

        if (! Cache::add($key, true, now()->addDay())) {
            return false;
        }

        try {
            $save();
        } catch (Throwable $e) {
            Cache::forget($key);

            throw $e;
        }

        return true;
    }
}
