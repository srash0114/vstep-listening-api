<?php
// Database Setup Script
echo "🚀 English Listening Test - Database Setup\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Load env.local if present
$envFile = __DIR__ . '/env.local';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$host   = getenv('DB_HOST')   ?: getenv('MYSQLHOST')     ?: '127.0.0.1';
$user   = getenv('DB_USER')   ?: getenv('MYSQLUSER')     ?: 'root';
$pass   = getenv('DB_PASS')   ?: getenv('MYSQLPASSWORD') ?: '';
$port   = (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);
$dbName = getenv('DB_NAME')   ?: getenv('MYSQLDATABASE') ?: 'listening_test';

// Connect to MySQL without selecting database
$mysqli = new mysqli($host, $user, $pass, '', $port);

if ($mysqli->connect_error) {
    die("❌ Connection failed: " . $mysqli->connect_error . "\n");
}

echo "✅ Connected to MySQL ($host:$port)\n";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if ($mysqli->query($sql)) {
    echo "✅ Database '$dbName' created/verified\n";
} else {
    die("❌ Error creating database: " . $mysqli->error . "\n");
}

// Select database
$mysqli->select_db($dbName);
echo "✅ Database selected\n\n";

// Import schema
echo "📋 Creating tables...\n";
$schemaFile = __DIR__ . '/db/schema.sql';

if (!file_exists($schemaFile)) {
    die("❌ Schema file not found: $schemaFile\n");
}

$schema = file_get_contents($schemaFile);

// Split and execute queries
$queries = array_filter(
    array_map('trim', explode(';', $schema)), 
    function($q) { return !empty($q); }
);

$tableCount = 0;
foreach ($queries as $query) {
    if ($mysqli->query($query)) {
        preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $query, $matches);
        $tableName = $matches[1] ?? 'Unknown';
        echo "   ✓ Table '$tableName' created\n";
        $tableCount++;
    } else {
        echo "   ❌ Error: " . $mysqli->error . "\n";
    }
}

$mysqli->close();

echo "\n" . str_repeat("=", 52) . "\n";
echo "✅ Setup complete! Tables created: $tableCount\n";
echo str_repeat("=", 52) . "\n\n";

echo "📝 Next steps:\n";
echo "1. Navigate to public directory:\n";
echo "   cd d:\\php\\english-listening-test\\public\n\n";
echo "2. Start the PHP development server WITH ROUTER:\n";
echo "   php -S localhost:8000 router.php\n\n";
echo "3. Test the API:\n";
echo "   curl http://localhost:8000/api/tests\n\n";
echo "4. Test CORS preflight:\n";
echo "   curl -X OPTIONS http://localhost:8000/api/users/login\n\n";

echo "📚 For detailed setup instructions, see RUN_SERVER.md\n";
echo "📚 API Documentation: See docs/API.md\n";
?>
