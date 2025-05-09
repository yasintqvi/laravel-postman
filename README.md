# Laravel Postman Documentation Generator

[![Latest Version](https://img.shields.io/packagist/v/yasin_tgh/laravel-postman.svg?style=flat-square)](https://packagist.org/packages/yasin_tgh/laravel-postman)

Automatically generate Postman collections from your Laravel API routes with flexible organization options.

## Features

- 🚀 Automatic Postman collection generation
- 🔧 Multiple grouping strategies:
  - By route prefix (e.g., `api/v1`)
  - By controller
  - Nested path structure
- ⚙️ Customizable request naming
- 🔍 Route filtering with include/exclude rules
- 📁 Configurable output location

## Installation

Install via Composer:

```bash
composer require yasin_tgh/laravel-postman
```

## 🚀 Basic Usage

Publish config file:
```bash
php artisan vendor:publish --provider="YasinTgh\LaravelPostman\PostmanServiceProvider" --tag="postman-config"
```

Generate documentation:

```bash
php artisan postman:generate
```
‍‍‍‍
