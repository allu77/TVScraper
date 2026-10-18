<?php
// Usage: php dump-db.php <db>
// Prints every table (not views) as JSON, rows ordered deterministically.

[, $dbFile] = $argv;

$db = new PDO("sqlite:$dbFile");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
	->fetchAll(PDO::FETCH_COLUMN);

$dump = [];
foreach ($tables as $table) {
	$columns = $db->query("PRAGMA table_info(\"$table\")")->fetchAll(PDO::FETCH_COLUMN, 1);
	$orderBy = implode(', ', array_map(fn($c) => "\"$c\"", $columns));
	$dump[$table] = $db->query("SELECT * FROM \"$table\" ORDER BY $orderBy")->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
