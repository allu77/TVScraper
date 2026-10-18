<?php
// Usage: php build-db.php <schema.sql> <seed.sql> <out.db>
// Builds a fresh SQLite DB from the legacy schema plus a seed file.

[, $schemaFile, $seedFile, $outFile] = $argv;

if (file_exists($outFile)) unlink($outFile);

$db = new PDO("sqlite:$outFile");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(file_get_contents($schemaFile));
$db->exec(file_get_contents($seedFile));
