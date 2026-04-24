<?php
/**
 * Restore SQL backup file to Aiven MySQL
 * Usage: php restore.php path/to/backup.sql
 */

$sqlFile = $argv[1] ?? null;

if (!$sqlFile) {
    die("❌ Usage: php restore.php <backup.sql>\n");
}

if (!file_exists($sqlFile)) {
    die("❌ File not found: $sqlFile\n");
}

echo "📂 Reading backup file: $sqlFile\n";
$sqlContent = file_get_contents($sqlFile);

if ($sqlContent === false) {
    die("❌ Could not read file\n");
}

// Load env.local
$envFile = __DIR__ . '/env.local';
$envData = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $envData[trim($key)] = trim($value);
    }
}

echo "🔌 Connecting to Aiven MySQL...\n";

$host = $envData['DB_HOST'] ?? 'localhost';
$port = (int) ($envData['DB_PORT'] ?? 3306);
$dbname = $envData['DB_NAME'] ?? 'defaultdb';
$user = $envData['DB_USER'] ?? 'root';
$pass = $envData['DB_PASS'] ?? '';

$mysqli = @new mysqli($host, $user, $pass, '', $port);

if ($mysqli->connect_error) {
    die("❌ Connection failed: " . $mysqli->connect_error . "\n");
}

echo "✅ Connected!\n";

// Try to create/select database
if (!$mysqli->select_db($dbname)) {
    echo "⚠️  Database '$dbname' doesn't exist. Creating...\n";
    $createDbSql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$mysqli->query($createDbSql)) {
        die("❌ Failed to create database: " . $mysqli->error . "\n");
    }
    $mysqli->select_db($dbname);
}

echo "✅ Database selected: $dbname\n\n";

// Disable foreign key checks to avoid constraint errors during restore
echo "⚙️  Disabling foreign key checks...\n";
$mysqli->query("SET FOREIGN_KEY_CHECKS=0");

// Execute all queries using multi_query (properly handles escaping and multiline)
echo "📋 Executing SQL queries...\n\n";

if ($mysqli->multi_query($sqlContent)) {
    $queryCount = 0;
    do {
        $queryCount++;
        if ($queryCount % 10 == 0) {
            echo "   ✓ [$queryCount] Executed\n";
        }
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    
    $successCount = $queryCount;
    $failCount = 0;
    echo "   ✓ [$queryCount] Executed (final count)\n";
} else {
    die("❌ Error executing SQL: " . $mysqli->error . "\n");
}

// Re-enable foreign key checks
echo "\n⚙️  Re-enabling foreign key checks...\n";
$mysqli->query("SET FOREIGN_KEY_CHECKS=1");

$mysqli->close();

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Restore complete!\n";
echo "   Queries executed: $successCount\n";
echo str_repeat("=", 60) . "\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
?>
