# Laravel Postman

**Turn your Laravel routes into a ready-to-use Postman collection with one command, and keep it in sync.**

[![Latest Version](https://img.shields.io/packagist/v/yasin_tgh/laravel-postman.svg?style=flat-square)](https://packagist.org/packages/yasin_tgh/laravel-postman)
[![Total Downloads](https://img.shields.io/packagist/dt/yasin_tgh/laravel-postman.svg?style=flat-square)](https://packagist.org/packages/yasin_tgh/laravel-postman)
![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](./LICENSE)

No annotations and no hand-written JSON. Laravel Postman reads your routes and FormRequests and builds the collection your team actually wants to use: organized folders, realistic request bodies and working auth.

<!-- 📸 TODO: screenshot or GIF of the generated collection open in Postman (folder tree + body tab) -->

## ✨ From validation rules to a ready request

Write your validation rules as usual:

```php
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'age'      => 'required|integer|min:18',
            'role'     => ['required', Rule::in(['admin', 'editor'])],
            'born_at'  => 'required|date_format:Y-m-d',
            'tags.*'   => 'string',
            'addresses.*.city' => 'required|string',
        ];
    }
}
```

…and get a request body in Postman that **passes your own validation**:

```json
{
    "email": "user89@example.com",
    "password": "sample_text",
    "password_confirmation": "sample_text",
    "age": 27,
    "role": "admin",
    "born_at": "2026-11-13",
    "tags": ["sample_text"],
    "addresses": [{ "city": "sample_text" }]
}
```

Enums, `in:` lists, number ranges, string lengths, date formats, nested fields, wildcards and `confirmed` are all understood.

## 🚀 Quick start

```bash
composer require --dev yasin_tgh/laravel-postman
php artisan postman:generate
```

That's it. Import `storage/postman/api_collection.json` into Postman (**File → Import**) and start sending requests.

To customize it, publish the config:

```bash
php artisan vendor:publish --tag=postman-config
```

## 🧰 Features

| | |
|---|---|
| ⚡ **Zero-effort docs** | Every route under your API prefix, with path variables (`:user`) and a `{{base_url}}` variable. |
| 🧪 **Realistic request bodies** | Generated from FormRequest rules, as raw JSON or form-data. |
| 🗂️ **Your folder structure** | Group requests by URL path, prefix or controller. |
| 🔐 **Auth that just works** | Bearer, Basic or API key, applied only to protected routes. |
| ☁️ **Two-way Postman Cloud sync** | Push changes without losing your saved responses, scripts or manual requests. |
| 🎯 **Fine-grained filtering** | Include or exclude routes by pattern, middleware or controller. |

## 📖 Guides

### Organize your folders

Pick a strategy in `structure.folders.strategy`. Here's the same API three ways:

<table>
<tr><th><code>nested_path</code> (default)</th><th><code>prefix</code></th><th><code>controller</code></th></tr>
<tr valign="top"><td>

```
📁 users
   GET  api/users
   POST api/users
   GET  api/users/:user
   📁 avatar
      POST api/users/:user/avatar
📁 admin
   📁 reports
      📁 export
         GET api/admin/reports/export
```

</td><td>

```
📁 users
   GET  api/users
   POST api/users
   GET  api/users/:user
   POST api/users/:user/avatar
📁 admin
   GET api/admin/reports/export
```

</td><td>

```
📁 User
   GET  api/users
   POST api/users
   GET  api/users/:user
   POST api/users/:user/avatar
📁 Report
   GET api/admin/reports/export
```

</td></tr>
</table>

```php
'structure' => [
    'folders' => [
        'strategy'  => 'nested_path', // 'nested_path', 'prefix' or 'controller'
        'max_depth' => 10,            // nested_path only: deeper routes go in the deepest folder
    ],
    'naming_format' => '[{method}] {uri}', // placeholders: {method} {uri} {controller} {action}
],
```

### Choose which routes to document

Only routes under `routes.prefix` (default `api`) are included. You can narrow it down further:

```php
'routes' => [
    'prefix' => 'api',
    'include' => [
        'patterns'    => ['api/users/*'],          // wildcards
        'middleware'  => ['auth:sanctum'],
        'controllers' => [UserController::class],
    ],
    'exclude' => [
        'patterns'    => ['api/internal/*'],
        'middleware'  => ['debug'],
        'controllers' => [HealthController::class],
    ],
],
```

> [!TIP]
> If a route can't be processed (for example, a missing controller method), it's skipped and listed in the command output with the reason. It never breaks the whole collection.

### Shape the request bodies

```php
'structure' => [
    'requests' => [
        'default_body_type' => 'raw', // 'raw' (JSON) or 'formdata' (used for POST)
        'default_values' => [         // override generated values by field name
            'email'    => 'test@example.com',
            'password' => 'password',
        ],
    ],
],
```

`default_values` also fill in path variables. For example, `'user' => 42` sets `:user` to `42`.

### Set up authentication

```php
'auth' => [
    'enabled'  => true,
    'type'     => 'bearer',  // 'bearer', 'basic' or 'api_key'
    'location' => 'header',  // api_key only: 'header' or 'query'
    'default'  => [
        'token'     => env('POSTMAN_AUTH_TOKEN'),   // bearer
        'username'  => env('POSTMAN_AUTH_USER'),    // basic
        'password'  => env('POSTMAN_AUTH_PASSWORD'),
        'key_name'  => 'X-API-KEY',                 // api_key
        'key_value' => env('POSTMAN_AUTH_API_KEY'),
    ],
    'protected_middleware' => ['auth', 'auth:api', 'auth:sanctum'],
],
```

Routes behind one of the `protected_middleware` get your auth. **Every other route is set to No Auth**, so public endpoints never receive your credentials. The values are stored as collection variables (`{{auth_token}}`, …), so you change them in one place.

> [!WARNING]
> Make sure `protected_middleware` lists the guards your app uses (e.g. `auth:sanctum`). Otherwise protected routes are generated with No Auth.

## ☁️ Sync with Postman Cloud

Keep a shared Postman collection up to date as your API changes, without overwriting the work your team has done in Postman.

**1. Add your credentials to `.env`:**

```env
POSTMAN_API_KEY=PMAK-xxxxxxxx           # go.postman.co/settings/me/api-keys
POSTMAN_COLLECTION_ID=12345678-xxxx     # see "Finding your collection ID" below
POSTMAN_WORKSPACE_ID=                   # optional
```

**2. Preview, then push:**

```bash
php artisan postman:generate --dry-run   # show what would change, push nothing
php artisan postman:generate --push      # merge and push to Postman Cloud
```

```text
Merging…
  New requests: 2
    - POST /api/users
    - DELETE /api/users/:user
  Updated requests (remote responses, scripts and ids kept): 1
    - GET /api/users
  Remote-only requests kept: 1
    - Status page
[dry-run] Merge complete — no changes pushed to Postman Cloud.
```

> [!TIP]
> `--dry-run` and `--push` exit with a non-zero code on failure, so you can run them in CI.

#### What happens on a push

| In Postman | On `--push` |
|---|---|
| New Laravel routes | ➕ Added to the matching folder |
| Request bodies, headers, URLs of existing routes | 🔄 Updated from your code |
| Saved example responses | ✅ Kept (`preserve_responses`) |
| Pre-request and test scripts | ✅ Kept (`preserve_scripts`) |
| Requests and folders you added by hand | ✅ Kept (`preserve_manual_items`) |
| Descriptions written in Postman | ✅ Kept (`overwrite_descriptions: false`) |
| Auth set by hand on a public route | ✅ Kept (protected routes use your `auth` config) |
| Collection variables | ✅ Kept; new ones are added |
| Requests for deleted or renamed routes | ✅ Kept by default · 🗑️ removed with `prune_stale_routes: true` |

```php
'cloud' => [
    'api_key'       => env('POSTMAN_API_KEY'),
    'collection_id' => env('POSTMAN_COLLECTION_ID'),
    'workspace_id'  => env('POSTMAN_WORKSPACE_ID'),
    'merge' => [
        'preserve_responses'     => true,
        'preserve_scripts'       => true,
        'preserve_manual_items'  => true,
        'overwrite_descriptions' => false,
        'prune_stale_routes'     => false,
    ],
],
```

> [!WARNING]
> `prune_stale_routes` removes any remote request whose URL starts with `{{base_url}}` under your route prefix and has no matching route, **including ones you created by hand**. Run `--dry-run` first and check the "Stale requests removed" list.

<details>
<summary><b>Finding your collection ID</b></summary>

**In the Postman app:** open the collection → **⋯** → **Info** → copy the Collection ID.

**With the Postman API:** list your collections and copy the `uid`:

```bash
curl -s https://api.getpostman.com/collections -H "X-Api-Key: $POSTMAN_API_KEY" \
  | php -r 'foreach (json_decode(stream_get_contents(STDIN), true)["collections"] ?? [] as $c) echo "{$c["name"]} => {$c["uid"]}\n";'
```

</details>

## ⚙️ Configuration reference

<details>
<summary><b>All options in <code>config/postman.php</code></b></summary>

| Option | Default | Description |
|---|---|---|
| `name` | `APP_NAME` | Collection name |
| `description` | `API_DESCRIPTION` | Collection description |
| `base_url` | `APP_URL` | Value of the `{{base_url}}` variable |
| `routes.prefix` | `api` | Only routes under this prefix are documented |
| `routes.include.*` / `routes.exclude.*` | `[]` | Filter by `patterns`, `middleware`, `controllers` |
| `structure.folders.strategy` | `nested_path` | `nested_path`, `prefix` or `controller` |
| `structure.folders.max_depth` | `10` | Folder depth for `nested_path` |
| `structure.naming_format` | `[{method}] {uri}` | Request names: `{method}` `{uri}` `{controller}` `{action}` |
| `structure.requests.default_body_type` | `raw` | `raw` or `formdata` |
| `structure.requests.default_values` | `[]` | Fixed values per field or path variable |
| `auth.enabled` | `false` | Add auth to protected routes |
| `auth.type` | `bearer` | `bearer`, `basic` or `api_key` |
| `auth.location` | `header` | Where the API key goes: `header` or `query` |
| `auth.protected_middleware` | `auth`, `auth:api`, `auth:sanctum` | Middleware that marks a route as protected |
| `headers` | `Accept`, `Content-Type` | Headers on every request (`Content-Type` only with raw bodies) |
| `output.driver` | `local` | Filesystem driver (`POSTMAN_STORAGE_DISK`) |
| `output.path` | `storage/postman` | Output directory (`POSTMAN_STORAGE_DIR`) |
| `output.filename` | `api_collection` | File name; `.json` is added if missing (`POSTMAN_STORAGE_FILE`) |
| `cloud.*` | — | See **Sync with Postman Cloud** above |

</details>

## ❓ FAQ

<details>
<summary><b>A route is missing from my collection</b></summary>

Check that it's under `routes.prefix` and isn't removed by an `include`/`exclude` filter. Routes that can't be processed are listed as **Skipped** in the command output, with the reason.
</details>

<details>
<summary><b>A request has an empty body</b></summary>

Bodies are generated from a FormRequest type-hinted in the controller method. Plain `Request $request` parameters and inline `$request->validate()` calls have no rules to read.
</details>

<details>
<summary><b>Auth isn't applied to my protected routes</b></summary>

Make sure `auth.enabled` is `true` and that `auth.protected_middleware` contains the exact middleware your routes use, e.g. `auth:sanctum`.
</details>

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first.

```bash
composer install
composer test
```

## 📄 License

MIT. See [LICENSE](./LICENSE).
