# Limit Order

A Laravel-based limit order system.

## Prerequisites

- [Docker](https://www.docker.com/get-started) and Docker Compose
- Git
- PHP 8.5
- Composer

## Setup with Laravel Sail

### 1. Clone the repository

```bash
git clone git@github.com:chiragparekh/backend-limit-order.git
cd backend-limit-order
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Create environment file

```bash
cp .env.example .env
```

### 4. Start Laravel Sail

```bash
./vendor/bin/sail up -d
```

### 5. Generate application key

```bash
./vendor/bin/sail artisan key:generate
```

### 6. Run database migrations

```bash
./vendor/bin/sail artisan migrate
```

### 7. Seed the database

```bash
./vendor/bin/sail artisan db:seed
```

### Use following credentials to login to frontend
```
Email: user1@test.com
Password: password
``` 
```
Email: user2@test.com
Password: password
``` 