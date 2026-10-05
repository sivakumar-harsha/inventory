<?php

use App\Libraries\CashOpeningGuard;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Release 4.9.0CF: the pure (database-free) parts of the back-dated cash guard.
 *
 * @internal
 */
final class CashOpeningGuardTest extends CIUnitTestCase
{
    public function testAcceptsOnlyRealCalendarDates(): void
    {
        foreach (['2026-06-15', '2026-02-28', '2024-02-29', '2026-12-31'] as $ok) {
            $this->assertTrue(CashOpeningGuard::isValidDate($ok), $ok);
        }

        foreach (['', '0000-00-00', '00/00/0000', '2026-13-01', '2026-00-10', '2026-02-30', '2026-04-31', '2025-02-29',
                  'abc', '15/06/2026', '2026-6-5', '2026-06-15 10:00', ' 2026-06-15', '2026-06-15 ', '99999-01-01', '1800-01-01'] as $bad) {
            $this->assertFalse(CashOpeningGuard::isValidDate($bad), var_export($bad, true));
        }

        $this->assertFalse(CashOpeningGuard::isValidDate(null));
        $this->assertFalse(CashOpeningGuard::isValidDate(['2026-06-15']));
    }

    public function testFlattenKeepsNestedFieldNamesAndValues(): void
    {
        $flat = CashOpeningGuard::flatten(['a' => '1', 'items' => [['product_id' => '5', 'qty' => '2'], ['product_id' => '6']], 'e' => '']);

        $this->assertSame([['a', '1'], ['items[0][product_id]', '5'], ['items[0][qty]', '2'], ['items[1][product_id]', '6'], ['e', '']], $flat);
    }

    public function testFingerprintIgnoresOrderTokenAndLineEndingsButNotValues(): void
    {
        $a = CashOpeningGuard::fingerprint(['amount' => '10', 'remarks' => "x\r\ny", 'items' => ['b' => '2', 'a' => '1']]);
        $b = CashOpeningGuard::fingerprint(['items' => ['a' => '1', 'b' => '2'], 'remarks' => "x\ny", 'amount' => '10', CashOpeningGuard::FIELD => 'abc', 'confirm_duplicate' => '1']);
        $c = CashOpeningGuard::fingerprint(['amount' => '11', 'remarks' => "x\ny", 'items' => ['a' => '1', 'b' => '2']]);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }
}
