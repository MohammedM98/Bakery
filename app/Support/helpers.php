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
