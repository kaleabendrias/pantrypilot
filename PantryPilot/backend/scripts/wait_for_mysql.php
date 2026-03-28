<?php
declare(strict_types=1);

$host = 'mysql';
$port = 3306;
$db = 'pantrypilot';
$user = 'pantry';
$pass = 'pantrypass';
$timeout = 90;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    } elseif (str_starts_with($arg, '--port=')) {
        $port = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--db=')) {
        $db = substr($arg, 5);
    } elseif (str_starts_with($arg, '--user=')) {
        $user = substr($arg, 7);
    } elseif (str_starts_with($arg, '--pass=')) {
        $pass = substr($arg, 7);
    } elseif (str_starts_with($arg, '--timeout=')) {
        $timeout = (int) substr($arg, 10);
    }
}

$start = time();
$lastError = 'unknown';

while ((time() - $start) < $timeout) {
    $resolved = gethostbyname($host);
    if ($resolved === $host) {
        $lastError = "dns_not_ready host={$host}";
        usleep(500000);
        continue;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        echo "mysql_ready host={$host} ip={$resolved} db={$db}\n";
        exit(0);
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        usleep(500000);
    }
}

fwrite(STDERR, "mysql_not_ready after {$timeout}s: {$lastError}\n");
exit(1);
