#!/bin/bash
# ================================================================
# Railway First-Boot Setup Script
# ================================================================

echo "🚀 School Management System - Starting up..."

# Wait for MySQL to be ready (Railway MySQL takes a few seconds)
echo "⏳ Waiting for MySQL connection..."
for i in {1..30}; do
    php -r "
        \$host = getenv('MYSQLHOST') ?: 'localhost';
        \$port = getenv('MYSQLPORT') ?: '3306';
        \$user = getenv('MYSQLUSER') ?: 'root';
        \$pass = getenv('MYSQLPASSWORD') ?: '';
        \$db   = getenv('MYSQLDATABASE') ?: 'school_db';
        try {
            new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db;charset=utf8mb4\", \$user, \$pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo 'OK';
        } catch(Exception \$e) {
            echo 'FAIL: ' . \$e->getMessage();
            exit(1);
        }
    " 2>/dev/null && echo "✅ MySQL connected!" && break
    echo "   Attempt $i/30 - waiting 2 seconds..."
    sleep 2
done

# Check if tables already exist
TABLE_EXISTS=$(php -r "
    \$host = getenv('MYSQLHOST') ?: 'localhost';
    \$port = getenv('MYSQLPORT') ?: '3306';
    \$user = getenv('MYSQLUSER') ?: 'root';
    \$pass = getenv('MYSQLPASSWORD') ?: '';
    \$db   = getenv('MYSQLDATABASE') ?: 'school_db';
    try {
        \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db;charset=utf8mb4\", \$user, \$pass);
        \$result = \$pdo->query(\"SHOW TABLES LIKE 'users'\")->fetch();
        echo \$result ? 'EXISTS' : 'NOTFOUND';
    } catch(Exception \$e) {
        echo 'NOTFOUND';
    }
" 2>/dev/null)

if [ "$TABLE_EXISTS" = "EXISTS" ]; then
    echo "✅ Database already initialized, skipping..."
else
    echo "📦 Installing database tables..."
    php -r "
        \$host = getenv('MYSQLHOST') ?: 'localhost';
        \$port = getenv('MYSQLPORT') ?: '3306';
        \$user = getenv('MYSQLUSER') ?: 'root';
        \$pass = getenv('MYSQLPASSWORD') ?: '';
        \$db   = getenv('MYSQLDATABASE') ?: 'school_db';
        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db;charset=utf8mb4\", \$user, \$pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            \$sql = file_get_contents('/var/www/html/install/database.sql');
            // Remove comments
            \$sql = preg_replace('/--.*\$/m', '', \$sql);
            \$sql = preg_replace('/\/\*.*?\*\//s', '', \$sql);
            // Split and execute
            \$statements = array_filter(array_map('trim', explode(';', \$sql)));
            foreach (\$statements as \$stmt) {
                if (!empty(\$stmt)) {
                    \$pdo->exec(\$stmt);
                }
            }
            echo '✅ Database tables created successfully!' . PHP_EOL;
        } catch(Exception \$e) {
            echo '❌ Database error: ' . \$e->getMessage() . PHP_EOL;
        }
    "
fi

# Set proper permissions
chmod -R 755 /var/www/html/assets/uploads 2>/dev/null || true
chown -R www-data:www-data /var/www/html/assets 2>/dev/null || true

echo ""
echo "✅ ============================================"
echo "✅  School Management System is READY!"
echo "✅  Login: admin / password"
echo "✅ ============================================"
echo ""

# Configure PORT
echo "Listen ${PORT:-80}" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/g" /etc/apache2/sites-enabled/000-default.conf

# Start Apache
exec apache2-foreground
