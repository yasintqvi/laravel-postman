
# Laravel Postman Documentation Generator

[![Latest Version](https://img.shields.io/packagist/v/yasin_tgh/laravel-postman.svg?style=flat-square)](https://packagist.org/packages/yasin_tgh/laravel-postman)

Automatically generate Postman collections from your Laravel API routes with flexible organization and authentication support.

## Features

- 🚀 Automatic Postman collection generation
- 🔐 Smart authentication handling for protected routes
- 🔧 Multiple organization strategies:
  - By route prefix (e.g., `api/v1`)
  - By controller namespace
  - Nested path structure with depth control
- ⚙️ Customizable request naming patterns
- 🔍 Advanced route filtering with include/exclude rules
- 📂 Configurable output location
- 🔑 Supports multiple auth types:
  - Bearer tokens
  - Basic Auth
  - API Keys

## Installation

Install via Composer:

```bash
composer require --dev yasin_tgh/laravel-postman
```

Publish the config file:
```bash
php artisan vendor:publish --provider="YasinTgh\LaravelPostman\PostmanServiceProvider" --tag="postman-config"
```

## 🚀 Basic Usage

Generate documentation:
```bash
php artisan postman:generate
```

The collection will be saved to: `storage/postman/api_collection.json`

## 🔧 Configuration Guide

### Route Organization

Choose how routes are grouped in Postman:

```php
'structure' => [
    'folders' => [
        'strategy' => 'nested_path', // 'prefix', 'nested_path', or 'controller'
        'max_depth' => 3, // Only for nested_path strategy
        'mapping' => [
            'admin' => 'Administration' // Custom folder name mapping
        ]
    ],
    'name' => [
        'strategy' => 'simple', // 'simple', 'controller', or 'custom'
        'available_strategies' => [
            'simple' => '[{method}] {uri}',
            'controller' => '{controller}@{action}',
            'custom' => '[{method}] {action} {uri}'
        ]
    ]
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
    'path' => storage_path('postman/docs'), // Custom save path
    'filename' => 'my_api_collection' // Custom filename
]
```

## 🔐 Authentication Examples

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

## 🎯 Advanced Usage

### Custom Request Naming
Create your own naming pattern:
```php
'structure' => [
    'name' => [
        'strategy' => 'custom',
        'available_strategies' => [
            'custom' => '[{method}] {controller} - {action}'
        ]
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

## 📦 Output Example

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
        "auth": {
          "type": "bearer",
          "bearer": [{"key": "token", "value": "{{auth_token}}"}]
        }
      }
    }
  ]
}
```

## 🤝 Contributing
Pull requests are welcome! For major changes, please open an issue first.

## 📄 License
[MIT](https://choosealicense.com/licenses/mit/)
