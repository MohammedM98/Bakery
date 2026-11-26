<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The statement period from ?from=&to=, shared by the customer page and the PDF / Excel export
 * so the file always covers what is on screen. A bad date falls back to the default instead of erroring.
 */
class StatementRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * Default: the current month up to today. An empty value means "no bound" ("الكل" sends from= and to= empty).
     *
     * @return array{from: ?string, to: ?string}
     */
    public function range(): array
    {
        $valid = fn ($v) => is_string($v) && ($d = \DateTime::createFromFormat('!Y-m-d', $v)) && $d->format('Y-m-d') === $v;
        $date = fn (string $key) => $valid($this->query($key)) ? $this->query($key) : null;
        $explicit = fn (string $key) => $this->has($key) && in_array($this->query($key), [null, ''], true) || $valid($this->query($key));

        return [
            'from' => $explicit('from') ? $date('from') : now()->startOfMonth()->toDateString(),
            'to' => $explicit('to') ? $date('to') : now()->toDateString(),
        ];
    }
}
