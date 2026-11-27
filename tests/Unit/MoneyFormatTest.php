<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** money() is mirrored by formatMoney() in resources/js/lib/format.ts: keep both in step. */
class MoneyFormatTest extends TestCase
{
    public function test_amounts_are_written_with_the_word_shekel(): void
    {
        $this->assertSame('1,234.50 شيكل', money(1234.5));
        $this->assertSame('0.00 شيكل', money(null));
        // A negative number is isolated left to right so its minus stays on the left in Arabic text.
        $this->assertSame("\u{2066}-50.00\u{2069} شيكل", money('-50'));
    }
}
