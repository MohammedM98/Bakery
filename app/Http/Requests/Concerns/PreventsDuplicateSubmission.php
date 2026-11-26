<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

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
     * Runs $save unless a request with this submission_id already saved. A repeat that arrives
     * while the first is still running waits for it, so it reports the first one's real outcome:
     * if the first failed (e.g. a balance check), the repeat runs and fails the same way.
     */
    public function once(Closure $save): void
    {
        $key = 'submission:'.$this->user()->id.':'.$this->input('submission_id');

        Cache::lock($key.':lock', 30)->block(15, function () use ($key, $save) {
            if (Cache::has($key)) {
                return;
            }

            $save();
            Cache::put($key, true, now()->addDay());
        });
    }
}
