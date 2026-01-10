# Footprints

An asynchronous request/response logging package for Laravel applications. Capture detailed "footprints" of every
interaction with your API or web application without impacting performance.

## Features

- **Asynchronous Processing**: Uses Laravel Jobs to offload logging, ensuring zero latency impact on user requests.
- **Multiple Channels**: Log to Database, File, Kafka, or Elasticsearch.
- **Sensitive Data Masking**: Automatically redact passwords, credit cards, and other sensitive fields.
- **Request ID Tracking**: Generates and propagates unique `X-Request-ID` headers for tracing across microservices.

## Installation

1. **pull from GitHub:**

Configure `composer.json`:
```json
  {
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:tnmdevs/footprints.git"
    }
  ],
  "require": {
    ....
    "tnmdev/footprints": "dev-<branch-name>"
  },
}
```

2. **Publish Configuration:**

```bash
php artisan vendor:publish --tag=footprints-config
```

3. **Publish Migrations (Optional):**

   By default, migrations are autoloaded from the package. If you want to copy them to your `database/migrations` directory for customization:

```bash
php artisan vendor:publish --tag=footprints-migrations
```

Run Migrations:

   ```bash
   php artisan migrate
   ```
   *This will create the `footprints` table if you plan to use the database driver.*

---

## Configuration

The package behavior is controlled via environment variables in your `.env` file.

### Basic Setup

```env
# Enable or Disable logging globally
FOOTPRINTS_ENABLED=true

FOOTPRINT_SERVICE_NAME=my-laravel-app

# Where to log? Options: database, file, kafka, elasticsearch
# Comma-separated for multiple channels. Order matters here
FOOTPRINTS_CHANNELS=database,file
```

### Sensitive Data Masking

Define which fields should be redacted from the request body logs.

```env
FOOTPRINTS_HIDDEN_FIELDS=password,password_confirmation,credit_card,api_key,secret,pin
```

### Queue Configuration

Since logging happens in the background, configure which queue to use.

```env
FOOTPRINTS_QUEUE_CONNECTION=database
FOOTPRINTS_QUEUE_NAME=logging
```

> **Important**: You must start a queue worker to process the logging jobs. Without a running queue worker, footprints
> will be queued but not processed.

#### Starting the Queue Worker

Start a queue worker using Laravel's queue command:

```bash
php artisan queue:work
```

For production, you should run the queue worker as a background process or use a process manager like Supervisor. To
specify the connection and queue name that match your configuration:

```bash
php artisan queue:work database --queue=logging
```

Alternatively, for development, you can use `queue:listen` to reflect code changes immediately:

```bash
php artisan queue:listen database --queue=logging
```

Replace `database` and `logging` with the values from your `.env` file (`FOOTPRINTS_QUEUE_CONNECTION` and
`FOOTPRINTS_QUEUE_NAME`).

**Note**: If you're using the `sync` queue connection for development/testing, jobs will run immediately without a queue
worker, but this is not recommended for production as it will impact request performance.

**Recommendation**: For high-volume APIs or web applications, it is highly recommended to use Redis or RabbitMQ as your
queue connection. Using the database driver for queues in high-traffic scenarios can overwhelm your database with
insert/delete operations for managing jobs, potentially degrading overall application performance.

## Driver Specific Configuration

### Database Driver

Uses your default Laravel database connection by default.

```env
DB_CONNECTION=mysql
FOOTPRINTS_TABLE_NAME=footprints
```

### File Driver

Logs are written to the specified file path. The directory will be created if it doesn't exist, and the package will
validate file accessibility and disk space during service discovery.

```env
FOOTPRINTS_FILE_PATH=/var/log/myapp/footprints.log
FOOTPRINTS_FILE_MIN_FREE_SPACE_MB=100
```

- `FOOTPRINTS_FILE_PATH`: Full path to the log file (default: `storage/logs/footprints.log`)
- `FOOTPRINTS_FILE_MIN_FREE_SPACE_MB`: Minimum free disk space required in MB (default: `100MB`)

**Note**: The package automatically validates:

- Directory exists or can be created
- Directory/file is writable
- Sufficient disk space is available

Validation errors are logged as warnings but won't prevent the application from starting.

### Kafka Driver

#### Prerequisites

This driver requires the `rdkafka` PHP extension. Please refer to the [official installation instructions](https://www.google.com/search?q=https://github.com/arnaud-lb/php-rdkafka%23installation) to set it up in
your environment.

#### Required Configuration

These variables are mandatory for the Kafka driver to function.

```env
KAFKA_BROKERS=localhost:9092
KAFKA_TOPIC=app_footprints
```

#### Optional Configuration

These variables provide additional control but have sensible defaults or are not strictly necessary.

```env
KAFKA_CLIENT_ID=my-app-logger
KAFKA_TIMEOUT_MS=1000
KAFKA_MESSAGE_KEY=request_id
```

**Message Key Configuration**:

- `KAFKA_MESSAGE_KEY`: Optional. Set to a field name (e.g., `request_id`) to use that field's value as the message key,
  or leave unset/null for no key.
- For custom key generation functions, set directly in `config/footprints.php`.
- Generated keys are validated to ensure they don't exceed 512 characters.

#### Authentication Configuration

If your Kafka broker requires authentication, you must provide the credentials.

> **Important**: If you provide a `KAFKA_SASL_USERNAME`, you must also provide the `PASSWORD`, `MECHANISM`, and
`PROTOCOL`.

```env
KAFKA_SASL_USERNAME=your-username
KAFKA_SASL_PASSWORD=your-password
KAFKA_SASL_MECHANISM=SCRAM-SHA-256   # Required if username is set
KAFKA_SECURITY_PROTOCOL=SASL_SSL     # Required if username is set
```

### Elasticsearch Driver

#### Prerequisites

This driver requires the `elasticsearch/elasticsearch` PHP client. You can install it via Composer using the [official
installation instructions](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/installation.html) or by running:

```bash
composer require elasticsearch/elasticsearch
```

#### Required Configuration

These variables are mandatory.

```env
ELASTICSEARCH_HOSTS=localhost:9200
ELASTICSEARCH_INDEX=footprints_logs
ELASTICSEARCH_OPERATION_TYPE=index   # 'index' (default) or 'create'
```

#### Optional Configuration

```env
ELASTICSEARCH_DOCUMENT_ID_FIELD=request_id
```

**Document ID**:

- `ELASTICSEARCH_DOCUMENT_ID_FIELD`: Field name from footprint to use as document ID (default: `request_id`).
- Generated IDs are validated to ensure they don't exceed 512 characters.

#### Authentication Configuration

You must choose one authentication method. Do not configure both.

**Option A: Basic Authentication (Username & Password)**
Use this if your cluster uses standard user credentials.

```env
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=changeme
```

**Option B: API Key**
Use this if you have generated an API Key for access.

```env
ELASTICSEARCH_API_KEY=your-api-key-here
```

## Environment Variables Overview

Here's a complete overview of all environment variables the package expects:

```env
# Basic Configuration
FOOTPRINTS_ENABLED=true
FOOTPRINT_SERVICE_NAME=my-laravel-app
FOOTPRINTS_CHANNELS=database,file

# Sensitive Data Masking
FOOTPRINTS_HIDDEN_FIELDS=password,password_confirmation,credit_card,api_key,secret,pin

# Queue Configuration
FOOTPRINTS_QUEUE_CONNECTION=database
FOOTPRINTS_QUEUE_NAME=logging

# Database Driver (if using database channel)
DB_CONNECTION=mysql
FOOTPRINTS_TABLE_NAME=footprints

# File Driver (if using file channel)
FOOTPRINTS_FILE_PATH=/var/log/myapp/footprints.log
FOOTPRINTS_FILE_MIN_FREE_SPACE_MB=100

# Kafka Driver (if using kafka channel)
KAFKA_BROKERS=localhost:9092
KAFKA_TOPIC=app_footprints
KAFKA_CLIENT_ID=my-app-logger
KAFKA_TIMEOUT_MS=1000
KAFKA_MESSAGE_KEY=request_id
# Kafka Auth (Only if needed)
KAFKA_SASL_MECHANISM=SCRAM-SHA-256
KAFKA_SECURITY_PROTOCOL=SASL_SSL
KAFKA_SASL_USERNAME=your-username
KAFKA_SASL_PASSWORD=your-password

# Elasticsearch Driver (if using elasticsearch channel)
ELASTICSEARCH_HOSTS=localhost:9200
ELASTICSEARCH_INDEX=footprints_logs
ELASTICSEARCH_OPERATION_TYPE=index
ELASTICSEARCH_DOCUMENT_ID_FIELD=request_id
# Elasticsearch Auth (Choose ONE)
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=changeme
ELASTICSEARCH_API_KEY=your-api-key-here
```

## Usage

To start capturing footprints, simply register the middleware.

### Global Usage (All Routes)

Add the middleware to the `web` or `api` middleware groups in `app/Http/Kernel.php` (Laravel 10-) or
`bootstrap/app.php` (Laravel 11+).

**Laravel 10 & below (`app/Http/Kernel.php`)**:

```php
protected $middlewareGroups = [
    'api' => [
        // ...
        \TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware::class,
    ],
];
```

**Laravel 11+ (`bootstrap/app.php`)**:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware::class);
})
```

### Route Specific Usage

You can also apply it to specific routes:

```php
Route::middleware('footprints')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});
```

## Data Structure

The `footprints` table (or JSON object) contains:

| Field           | Description                                                                    |
|:----------------|:-------------------------------------------------------------------------------|
| `request_id`    | Unique UUID for the request (Header: `X-Request-ID`).                          |
| `service_name`  | Name of the service/application (from `FOOTPRINT_SERVICE_NAME` or `APP_NAME`). |
| `user_id`       | Authenticated User ID (if any).                                                |
| `method`        | HTTP Method (GET, POST, etc.).                                                 |
| `uri`           | The request path (e.g., `/api/users`).                                         |
| `ip_address`    | Client IP.                                                                     |
| `status_code`   | HTTP Response code (200, 404, 500).                                            |
| `duration_ms`   | Execution time in milliseconds.                                                |
| `request_body`  | JSON payload (sensitive fields redacted).                                      |
| `response_body` | Response content (truncated/handled if binary).                                |
| `environment`   | Application environment (e.g., local, production).                             |

## Testing

The package comes with a comprehensive test suite.

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
