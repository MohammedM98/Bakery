<?php

if (! function_exists('money')) {
    /**
     * Format a monetary amount for display in Israeli Shekels (₪).
     */
    function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2).' ₪';
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
