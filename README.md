# Kata: Transforming Legacy PHP: Refactoring Techniques in Theory and Practice

Invoice processing demo used as the basis for the live session on April, 7th 2026.

## Requirements

- PHP 8.4+
- Docker (for MySQL)
- Composer

## Setup

```bash
composer install
composer setup # starts MySQL container and creates the schema
```

> Connect locally → `docker exec -it ipc-mysql mysql -uroot -ppassword123 shop_db`

## Usage

```bash
composer start # runs the invoice processing demo (run.php)
```

## Tests

```bash
composer test
```

> Tests require the MySQL container to be running (`composer setup`).
