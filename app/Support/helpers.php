<?php

use Illuminate\Support\Carbon;

if (! function_exists('local_today')) {
    /**
     * Today's date in the bakery's time zone (app.local_timezone), as midnight in app.timezone so it
     * compares with date columns: a sale at 01:00 Palestine time belongs to that day, not the UTC one.
     */
    function local_today(): Carbon
    {
        return Carbon::parse(now(config('app.local_timezone'))->toDateString());
    }
}

if (! function_exists('money')) {
    /**
     * Format a monetary amount in shekels: "1,234.50 شيكل". A negative number is wrapped in a
     * left-to-right isolate so its minus stays on the left inside Arabic text ("-50.00 شيكل").
     */
    function money(float|int|string|null $amount): string
    {
        $number = number_format((float) $amount, 2);

        return ((float) $amount < 0 ? "\u{2066}{$number}\u{2069}" : $number).' شيكل';
    }
}

if (! function_exists('kg')) {
    /**
     * Format kilos like formatKg() in React: "12.5 كغ", up to two decimals.
     */
    function kg(float|int|string|null $kg): string
    {
        return rtrim(rtrim(number_format((float) $kg, 2), '0'), '.').' كغ';
    }
}

if (! function_exists('flour_balance')) {
    /**
     * Flour balance in words like formatFlourBalance() in React, never a sign:
     * positive = "عليه 20 كغ", negative = "له 20 كغ", zero = "0 كغ".
     */
    function flour_balance(float|int|string|null $kg): string
    {
        $kg = round((float) $kg, 2);

        return match (true) {
            $kg > 0 => 'عليه '.kg($kg),
            $kg < 0 => 'له '.kg(-$kg),
            default => kg(0),
        };
    }
}
