<?php
declare(strict_types=1);

$path = __DIR__ . '/database.sql';
$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Cannot read SQL file\n");
    exit(1);
}

try {
    require_once dirname(__DIR__) . '/includes/config.php';
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec($sql);
    $tables = $pdo->query('SHOW TABLES FROM webconnect')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Imported OK. Tables (' . count($tables) . '): ' . implode(', ', $tables) . PHP_EOL;
    $admin = $pdo->query('SELECT username FROM webconnect.admins')->fetch(PDO::FETCH_ASSOC);
    echo 'Admin user: ' . ($admin['username'] ?? 'none') . PHP_EOL;
    // Verify password
    $hash = $pdo->query('SELECT password_hash FROM webconnect.admins WHERE username="admin"')->fetchColumn();
    echo 'Password verify admin123: ' . (password_verify('admin123', $hash) ? 'YES' : 'NO') . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
