<?php
declare(strict_types=1);
$src = __DIR__ . '/database.sql';
$out = __DIR__ . '/cpanel_import.sql';
$sql = file_get_contents($src);
if ($sql === false) {
    fwrite(STDERR, "Cannot read database.sql\n");
    exit(1);
}
$sql = preg_replace('/CREATE DATABASE[\s\S]*?;\s*/i', '', $sql) ?? $sql;
$sql = preg_replace('/USE\s+`?\w+`?\s*;\s*/i', '', $sql) ?? $sql;
$header = <<<'HDR'
-- WebChat schema for cPanel phpMyAdmin
-- 1) In cPanel: create an empty MySQL database + user, grant ALL
-- 2) In phpMyAdmin: select THAT database (left sidebar)
-- 3) Import this file
-- Default admin after import: admin / admin123
-- Then create includes/config.local.php from config.local.example.php

HDR;
file_put_contents($out, $header . $sql);
echo "Wrote {$out}\n";
