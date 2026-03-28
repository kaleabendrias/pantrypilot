<?php
declare(strict_types=1);

require '/var/www/html/vendor/autoload.php';

$TEST_RESULTS = ['passed' => 0, 'failed' => 0];

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEquals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function assertThrows(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message);
}

function runTest(string $name, callable $fn): void
{
    global $TEST_RESULTS;
    try {
        $fn();
        $TEST_RESULTS['passed']++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $TEST_RESULTS['failed']++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

function finishTests(): int
{
    global $TEST_RESULTS;
    echo "Unit tests passed={$TEST_RESULTS['passed']} failed={$TEST_RESULTS['failed']}\n";
    return $TEST_RESULTS['failed'] === 0 ? 0 : 1;
}
