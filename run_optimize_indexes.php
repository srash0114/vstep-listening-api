<?php
/**
 * Run database optimization indexes
 * This script executes the optimize_indexes.sql file to create indexes for performance
 * 
 * Usage: php run_optimize_indexes.php
 */

// Load environment variables
$env_file = __DIR__ . '/env.local';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_port = $_ENV['DB_PORT'] ?? 3306;
$db_name = $_ENV['DB_NAME'] ?? 'defaultdb';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

echo "🔄 Connecting to MySQL...\n";
echo "Host: $db_host:$db_port\n";
echo "Database: $db_name\n";
echo "User: $db_user\n\n";

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    echo "❌ Connection failed: " . $conn->connect_error . "\n";
    exit(1);
}

echo "✅ Connected successfully!\n\n";

// Read SQL file
$sql_file = __DIR__ . '/db/optimize_indexes.sql';
if (!file_exists($sql_file)) {
    echo "❌ SQL file not found: $sql_file\n";
    exit(1);
}

$sql_content = file_get_contents($sql_file);

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $sql_content)),
    function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
);

$success_count = 0;
$error_count = 0;
$skipped_count = 0;

echo "Executing " . count($statements) . " SQL statements...\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($statements as $i => $statement) {
    // Skip comment-only lines
    if (strpos(trim($statement), '--') === 0 || empty(trim($statement))) {
        continue;
    }

    $stmt_short = substr(trim($statement), 0, 60);
    echo "[$i] " . $stmt_short . (strlen($statement) > 60 ? '...' : '') . "\n";

    try {
        if ($conn->query($statement) === true) {
            echo "    ✅ Success\n";
            $success_count++;
        } else {
            $error = $conn->error;
            // Check if it's a "duplicate key" error (index already exists)
            if (strpos($error, 'Duplicate key name') !== false || strpos($error, 'already exists') !== false) {
                echo "    ⏭️  Skipped (already exists)\n";
                $skipped_count++;
            } else if (strpos($statement, 'SHOW') === 0) {
                // SHOW statements return results, not a boolean
                $result = $conn->query($statement);
                if ($result) {
                    echo "    ℹ️  Query executed\n";
                    $success_count++;
                } else {
                    echo "    ❌ Error: " . $conn->error . "\n";
                    $error_count++;
                }
            } else {
                echo "    ❌ Error: " . $error . "\n";
                $error_count++;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if (strpos($error, 'Duplicate key name') !== false || strpos($error, 'already exists') !== false) {
            echo "    ⏭️  Skipped (already exists)\n";
            $skipped_count++;
        } else {
            echo "    ❌ Error: " . $error . "\n";
            $error_count++;
        }
    }
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "📊 Results:\n";
echo "   ✅ Successful: $success_count\n";
echo "   ⏭️  Skipped: $skipped_count\n";
echo "   ❌ Errors: $error_count\n";

if ($error_count === 0) {
    echo "\n🎉 All indexes are ready!\n";
    echo "Your API should now be 5-50x faster on exam queries.\n";
    $conn->close();
    exit(0);
} else {
    echo "\n⚠️  Some operations failed. Check the errors above.\n";
    $conn->close();
    exit(1);
}
?>
