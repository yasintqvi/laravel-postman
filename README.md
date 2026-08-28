
# Laravel Postman Documentation Generator

[![Latest Version](https://img.shields.io/packagist/v/yasin_tgh/laravel-postman.svg?style=flat-square)](https://packagist.org/packages/yasin_tgh/laravel-postman)

Automatically generate Postman collections from your Laravel API routes with flexible organization and authentication support.

## Features

- Generate Postman collections with one command
- Automatic request body generation from FormRequest validation rules
- Multiple organization strategies (route prefix, controller, nested paths)
- Built-in authentication support (Bearer, Basic Auth, API Keys)
- Customizable route filtering
- Environment variable support for sensitive data

## Installation

Install via Composer:

```bash
composer require --dev yasin_tgh/laravel-postman
```

Publish the config file:
```bash
php artisan vendor:publish --provider="YasinTgh\LaravelPostman\PostmanServiceProvider" --tag="postman-config"
```

## Basic Usage

Generate documentation:
```bash
php artisan postman:generate
```

The collection will be saved to: `storage/postman/api_collection.json`

## Configuration Guide

### Route Organization

Configure how API routes are organized in Postman folders, how request names are formatted, and how request bodies are generated, including optional default values for fields.

```php
'structure' => [
    'folders' => [
        'strategy' => 'nested_path', // 'prefix', 'nested_path', or 'controller'
        'max_depth' => 3, // Only for nested_path strategy
        'mapping' => [
            'admin' => 'Administration' // Custom folder name mapping
        ]
    ],
  
    'naming_format' => '[{method}] {uri}', // placeholders: {method} {uri} {controller} {action}

    /**
    * Request body settings:
    * - default_body_type: 'raw' or 'formdata'
    * - default_values: preset values applied to generated fields
    */
    'requests' => [
        'default_body_type' => 'raw',
        
        'default_values' => [
            // 'email' => 'test@example.com',
            // 'password' => '123456',
        ],
    ],

]
```

### Route Filtering

Control which routes are included:

```php
'routes' => [
    'prefix' => 'api', // Base API prefix
    
    'include' => [
        'patterns' => ['api/users/*'], // Wildcard patterns
        'middleware' => ['api'], // Only routes with these middleware
        'controllers' => [App\Http\Controllers\UserController::class] // Specific controllers
    ],
    
    'exclude' => [
        'patterns' => ['admin/*'],
        'middleware' => ['debug'],
        'controllers' => [App\Http\Controllers\TestController::class]
    ]
]
```

### Authentication Setup

Document your API authentication:

```php
'auth' => [
    'enabled' => true,
    'type' => 'bearer', // 'bearer', 'basic', or 'api_key'
    'location' => 'header', // 'header' or 'query' for API keys
    
    'default' => [
        'token' => env('POSTMAN_AUTH_TOKEN'),
        'username' => env('POSTMAN_AUTH_USER'),
        'password' => env('POSTMAN_AUTH_PASSWORD'),
        'key_name' => 'X-API-KEY',
        'key_value' => env('POSTMAN_API_KEY')
    ],
    
    'protected_middleware' => ['auth:api', 'auth:sanctum']
]
```

### Output Configuration

```php
'output' => [
        'driver' => env('POSTMAN_STORAGE_DISK', 'local'),

        // Storage path for generated files
        'path' => env('POSTMAN_STORAGE_DIR', storage_path('postman')),

        // File naming pattern (date will be appended)
        'filename' => env('POSTMAN_STORAGE_FILE', 'api_collection'),
    ],
```

## Authentication Examples

### Bearer Token
```php
'auth' => [
    'enabled' => true,
    'type' => 'bearer',
    'default' => [
        'token' => 'your-bearer-token'
    ]
]
```

### Basic Auth
```php
'auth' => [
    'enabled' => true,
    'type' => 'basic',
    'default' => [
        'username' => 'api-user',
        'password' => 'secret'
    ]
]
```

### API Key
```php
'auth' => [
    'enabled' => true,
    'type' => 'api_key',
    'location' => 'header', // or 'query'
    'default' => [
        'key_name' => 'X-API-KEY',
        'key_value' => 'your-api-key-123'
    ]
]
```

### Environment Variables
Use `.env` values for sensitive data:
```php
'auth' => [
    'default' => [
        'token' => env('POSTMAN_DEMO_TOKEN', 'test-token')
    ]
]
```

## Output Example

Generated Postman collection will:
- Group routes by your chosen strategy
- Apply authentication to protected routes
- Include all configured headers
- Use variables for base URL and auth credentials

```json
{
  "info": {
    "name": "My API",
    "description": "API Documentation"
  },
  "variable": [
    {"key": "base_url", "value": "https://api.example.com"},
    {"key": "auth_token", "value": "your-token"}
  ],
  "item": [
    {
      "name": "[GET] users",
      "request": {
        "method": "GET",
        "body": {
          "mode": "raw",
          "raw": "{\"email\":\"user@example.com\",\"password\":\"password123\"}"
        },
        "auth": {
          "type": "bearer",
          "bearer": [{"key": "token", "value": "{{auth_token}}"}]
        }
      }
    }
  ]
}
```

## Postman Cloud Sync

### Prerequisites

1. Generate a Postman API key at [go.postman.co/settings/me/api-keys](https://go.postman.co/settings/me/api-keys).
2. Get the **Collection ID** with either method below.

**Option A — Postman API (recommended)**

List every collection on the account and copy `uid` (or `id`) for the collection you want to sync:

```bash
curl --location 'https://api.getpostman.com/collections' \
  --header 'X-Api-Key: YOUR_POSTMAN_API_KEY'
```

Example response:

```json
{
  "collections": [
    {
      "id": "12345678-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "name": "My Phobia",
      "uid": "12345678-12345678-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
    }
  ]
}
```

Use the `uid` value as `POSTMAN_COLLECTION_ID`. Match on `"name"` if you have more than one collection.

To print name + uid only:

```bash
curl --location 'https://api.getpostman.com/collections' \
  --header 'X-Api-Key: YOUR_POSTMAN_API_KEY' \
  | php -r '$d=json_decode(stream_get_contents(STDIN), true); foreach (($d["collections"] ?? []) as $c) { echo $c["name"]." => ".$c["uid"].PHP_EOL; }'
```

**Option B — Postman app**

Open the collection → ⋯ → **Info** → copy Collection ID.

Add to your `.env`:

```env
POSTMAN_API_KEY=PMAK-xxxxxxxxxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
POSTMAN_COLLECTION_ID=12345678-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
POSTMAN_WORKSPACE_ID=        # optional
```

Publish and update `config/postman.php`:

```php
'cloud' => [
    'api_key'       => env('POSTMAN_API_KEY'),
    'collection_id' => env('POSTMAN_COLLECTION_ID'),
    'workspace_id'  => env('POSTMAN_WORKSPACE_ID'),

    'merge' => [
        'preserve_responses'     => true,  // keep saved example responses
        'preserve_scripts'       => true,  // keep pre-request & test scripts
        'preserve_manual_items'  => true,  // keep manually added routes/folders
        'overwrite_descriptions' => false, // keep descriptions written in Postman UI
    ],
],
```

### Usage

```bash
# Generate locally only
php artisan postman:generate

# Generate + push incremental changes to Postman Cloud
php artisan postman:generate --push

# Preview the merge result without pushing (useful in CI)
php artisan postman:generate --dry-run
```

### Merge Behaviour

| Item | Behaviour |
| :--- | :--- |
| New Laravel routes | Added to the matching folder in the remote collection |
| Changed route method / path | Updated automatically |
| Updated FormRequest body fields | Updated automatically |
| Saved example responses | **Preserved** (`preserve_responses`) |
| Pre-request & test scripts | **Preserved** (`preserve_scripts`) |
| Manually added Postman routes | **Preserved** (`preserve_manual_items`) |
| Descriptions written in Postman | **Preserved** by default (`overwrite_descriptions: false`) |
| Collection variables | Existing remote variables are never overwritten; new ones are appended |

## 🤝 Contributing
Pull requests are welcome! For major changes, please open an issue first.

## License
[MIT](./LICENSE)
