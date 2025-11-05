<?php
/**
 * Installation Verification Script
 * Checks if all required files and components are properly installed
 */

// Prevent web access for security
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied. This script can only be run from command line.');
}

echo "=== Zaphira Attendance System - Installation Verification ===\n\n";

// Check required files
$required_files = [
    'database/schema.sql' => 'Database schema file',
    'utils/db.php' => 'Database connection utility',
    'utils/helpers.php' 'Helper functions utility',
    'utils/authMiddleware.php' => 'Authentication middleware',
    'utils/roleMiddleware.php' => 'Role-based access middleware',
    'auth/login.php' => 'Login endpoint',
    'auth/register.php' => 'Registration endpoint',
    'auth/check-session.php' => 'Session validation endpoint',
    'auth/logout.php' => 'Logout endpoint',
    'attendance/checkin.php' => 'Check-in endpoint',
    'attendance/checkout.php' => 'Check-out endpoint',
    'attendance/viewSelf.php' => 'Personal attendance endpoint',
    'attendance/viewAll.php' => 'All attendance endpoint (admin)',
    'users/list.php' => 'User list endpoint (admin)',
    'users/getSingle.php' => 'Single user endpoint',
    'users/update.php' => 'User update endpoint',
    'users/delete.php' => 'User delete endpoint (admin)',
    'departments/list.php' => 'Department list endpoint',
    'departments/create.php' => 'Department create endpoint (admin)',
    'departments/update.php' => 'Department update endpoint (admin)',
    'departments/delete.php' => 'Department delete endpoint (admin)',
    '.env.example' => 'Environment configuration template',
    'README.md' => 'Documentation file'
];

$missing_files = [];
$existing_files = [];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        $existing_files[] = "✅ $file - $description";
    } else {
        $missing_files[] = "❌ $file - $description";
    }
}

echo "FILE STRUCTURE CHECK:\n";
echo "===================\n";

if (!empty($existing_files)) {
    echo "Existing files:\n";
    foreach ($existing_files as $file) {
        echo "  $file\n";
    }
    echo "\n";
}

if (!empty($missing_files)) {
    echo "Missing files:\n";
    foreach ($missing_files as $file) {
        echo "  $file\n";
    }
    echo "\n";
} else {
    echo "🎉 All required files are present!\n\n";
}

// Check PHP syntax (basic check)
echo "PHP SYNTAX CHECK:\n";
==================\n";

$php_files = glob('**/*.php');
$syntax_errors = [];

foreach ($php_files as $file) {
    if (strpos($file, 'verify_installation.php') !== false) continue;

    $output = [];
    $return_code = 0;
    exec("php -l \"$file\" 2>&1", $output, $return_code);

    if ($return_code !== 0) {
        $syntax_errors[$file] = $output;
    } else {
        echo "✅ $file - Syntax OK\n";
    }
}

if (!empty($syntax_errors)) {
    echo "\n❌ Syntax errors found:\n";
    foreach ($syntax_errors as $file => $errors) {
        echo "  $file:\n";
        foreach ($errors as $error) {
            echo "    $error\n";
        }
    }
} else {
    echo "\n🎉 All PHP files have valid syntax!\n\n";
}

// Check directory structure
echo "DIRECTORY STRUCTURE CHECK:\n";
============================\n";

$required_dirs = [
    'auth' => 'Authentication endpoints',
    'attendance' => 'Attendance management endpoints',
    'users' => 'User management endpoints',
    'departments' => 'Department management endpoints',
    'utils' => 'Utility functions',
    'database' => 'Database files'
];

foreach ($required_dirs as $dir => $description) {
    if (is_dir($dir)) {
        echo "✅ $dir/ - $description\n";
    } else {
        echo "❌ $dir/ - $description (missing)\n";
    }
}

echo "\n";

// Check .env file
echo "ENVIRONMENT CONFIGURATION:\n";
==========================\n";

if (file_exists('.env')) {
    echo "✅ .env file exists\n";

    // Check required environment variables
    $env_content = file_get_contents('.env');
    $required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'CORS_ORIGIN'];

    foreach ($required_vars as $var) {
        if (strpos($env_content, $var) !== false) {
            echo "✅ $var is configured\n";
        } else {
            echo "❌ $var is missing\n";
        }
    }
} else {
    echo "❌ .env file not found\n";
    echo "💡 Copy .env.example to .env and configure your settings\n";
}

echo "\n";

// Database connection test (if .env exists)
echo "DATABASE CONNECTION TEST:\n";
==========================\n";

if (file_exists('.env')) {
    // Load environment variables (basic parsing)
    $env_vars = [];
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value, '"\'');
    }

    if (isset($env_vars['DB_HOST'], $env_vars['DB_NAME'], $env_vars['DB_USER'])) {
        try {
            $dsn = "mysql:host=" . $env_vars['DB_HOST'] . ";dbname=" . $env_vars['DB_NAME'];
            $pdo = new PDO($dsn, $env_vars['DB_USER'], $env_vars['DB_PASS'] ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "✅ Database connection successful\n";

            // Check if tables exist
            $tables = ['users', 'departments', 'attendance', 'activity_logs'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "✅ Table '$table' exists\n";
                } else {
                    echo "❌ Table '$table' missing - Run database/schema.sql\n";
                }
            }

        } catch (PDOException $e) {
            echo "❌ Database connection failed: " . $e->getMessage() . "\n";
            echo "💡 Check database credentials and ensure database is created\n";
        }
    } else {
        echo "❌ Database credentials not found in .env\n";
    }
} else {
    echo "❌ Cannot test database - .env file missing\n";
}

echo "\n";

// Summary
echo "INSTALLATION SUMMARY:\n";
=======================\n";

$total_files = count($required_files);
$existing_count = count($existing_files);
$missing_count = count($missing_files);
$syntax_count = count($php_files) - count($syntax_errors);

echo "Files: $existing_count/$total_files present\n";
echo "PHP Syntax: $syntax_count/" . count($php_files) . " files valid\n";

if ($missing_count === 0 && empty($syntax_errors)) {
    echo "🎉 Installation appears to be complete!\n";
    echo "\nNEXT STEPS:\n";
    echo "1. Copy .env.example to .env and configure\n";
    echo "2. Create database and run database/schema.sql\n";
    echo "3. Test endpoints with a REST client or curl\n";
    echo "4. Change default admin password\n";
} else {
    echo "❌ Installation incomplete - Please fix the issues above\n";
}

echo "\n=== Verification Complete ===\n";

?>