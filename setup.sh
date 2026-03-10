#!/usr/bin/env bash
set -euo pipefail

CONTAINER_NAME="ipc-mysql"
MYSQL_ROOT_PASSWORD="password123"
MYSQL_DATABASE="shop_db"
HOST_PORT=3307

echo "=== IPC Refactoring Techniques Setup ==="
echo ""

# Check Docker is available
if ! command -v docker &> /dev/null; then
    echo "ERROR: Docker is not installed or not in PATH."
    exit 1
fi

if ! docker info &> /dev/null; then
    echo "ERROR: Docker daemon is not running."
    exit 1
fi

# Start container if needed
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "✓ Container '${CONTAINER_NAME}' is already running."
elif docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "→ Starting existing container '${CONTAINER_NAME}'..."
    docker start "$CONTAINER_NAME"
    echo "✓ Container started."
else
    echo "→ Creating container '${CONTAINER_NAME}' (MySQL 8.0 on port ${HOST_PORT})..."
    docker run -d \
        --name "$CONTAINER_NAME" \
        -e MYSQL_ROOT_PASSWORD="$MYSQL_ROOT_PASSWORD" \
        -e MYSQL_DATABASE="$MYSQL_DATABASE" \
        -p "${HOST_PORT}:3306" \
        mysql:8.0
    echo "✓ Container created."
fi

# Wait for MySQL to be ready
echo "→ Waiting for MySQL to accept connections..."
for i in $(seq 1 30); do
    if docker exec "$CONTAINER_NAME" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" &> /dev/null; then
        echo "✓ MySQL is ready."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "ERROR: MySQL did not become ready in time."
        exit 1
    fi
    sleep 1
done

# Reset and run migrations
echo "→ Dropping existing tables..."
docker exec "$CONTAINER_NAME" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS orders;
"

echo "→ Running migrations..."
docker exec "$CONTAINER_NAME" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL,
    customer_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL
);
"
echo "✓ Tables ready."

echo ""
echo "=== Setup complete ==="
echo ""
echo "  composer start  — run the invoice app"
echo "  composer test   — run the test suite"
echo "  DB connection   — 127.0.0.1:${HOST_PORT} root/${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}"
echo ""
