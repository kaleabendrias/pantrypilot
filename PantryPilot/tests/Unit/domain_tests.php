<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use app\domain\bookings\BookingDomainPolicy;
use app\domain\identity\LoginLockPolicy;
use app\domain\identity\PasswordPolicy;
use app\domain\payments\PaymentDomainPolicy;
use app\domain\recipes\RecipeDomainPolicy;

runTest('Password policy requires 10+ with letter and number', function (): void {
    $policy = new PasswordPolicy();
    assertThrows(fn () => $policy->assertValid('short123'), 'Should reject short password');
    assertThrows(fn () => $policy->assertValid('abcdefghijk'), 'Should reject no-number');
    assertThrows(fn () => $policy->assertValid('12345678901'), 'Should reject no-letter');
    $policy->assertValid('abc1234567');
    assertTrue(true, 'valid password should pass');
});

runTest('Login lock policy threshold and lock window', function (): void {
    $policy = new LoginLockPolicy();
    assertEquals(1, $policy->nextAttempts(0), 'Attempts should increment');
    assertTrue(!$policy->shouldLock(4), 'Attempt 4 should not lock yet');
    assertTrue($policy->shouldLock(5), 'Attempt 5 should lock');
    assertTrue($policy->shouldLock(8), 'Attempts above threshold should remain locked');
    $lockedUntil = $policy->lockedUntil();
    assertTrue(strtotime($lockedUntil) > time(), 'Lock time must be in future');
    assertThrows(fn () => $policy->assertNotLocked(date('Y-m-d H:i:s', time() + 60)), 'Locked account must throw');
    $policy->assertNotLocked(date('Y-m-d H:i:s', time() - 60));
    assertTrue(true, 'Expired lock should not throw');
});

runTest('Booking policy requires future pickup', function (): void {
    $policy = new BookingDomainPolicy();
    assertThrows(fn () => $policy->assertFuturePickup(date('Y-m-d H:i:s', time() - 60)), 'Past pickup should fail');
    assertThrows(fn () => $policy->assertFuturePickup(date('Y-m-d H:i:s')), 'Now should fail');
    $policy->assertFuturePickup(date('Y-m-d H:i:s', time() + 3600));
    assertTrue(true, 'Future pickup should pass');
});

runTest('Payment policy requires positive amount', function (): void {
    $policy = new PaymentDomainPolicy();
    assertThrows(fn () => $policy->assertPositiveAmount(0), 'Zero amount should fail');
    assertThrows(fn () => $policy->assertPositiveAmount(-1.25), 'Negative amount should fail');
    $policy->assertPositiveAmount(1.25);
    assertTrue(true, 'Positive amount should pass');
});

runTest('Recipe publish policy validates prep time', function (): void {
    $policy = new RecipeDomainPolicy();
    assertThrows(fn () => $policy->validateDraftToPublished(['prep_minutes' => 0]), 'prep 0 should fail');
    $policy->validateDraftToPublished(['prep_minutes' => 10]);
    assertTrue(true, 'prep > 0 should pass');
});

exit(finishTests());
