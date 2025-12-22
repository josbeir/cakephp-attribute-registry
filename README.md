[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/josbeir/cakephp-attribute-registry)
[![Build Status](https://github.com/josbeir/cakephp-attribute-registry/actions/workflows/ci.yml/badge.svg)](https://github.com/josbeir/cakephp-attribute-registry/actions)
[![codecov](https://codecov.io/github/josbeir/cakephp-attribute-registry/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-attribute-registry)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net/releases/8.2/en.php)
[![CakePHP Version](https://img.shields.io/badge/CakePHP-5.2%2B-red.svg)](https://cakephp.org/)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-attribute-registry)](https://packagist.org/packages/josbeir/cakephp-attribute-registry)

# CakePHP Attribute Registry Plugin

A powerful CakePHP plugin for discovering, caching, and querying PHP 8 attributes across your application and plugins.

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
    - [Accessing the AttributeRegistry](#accessing-the-attributeregistry)
        - [Option 1: Singleton](#option-1-singleton)
        - [Option 2: Dependency Injection](#option-2-dependency-injection)
    - [Discovery Methods](#discovery-methods)
        - [Discover All Attributes](#discover-all-attributes)
        - [Find by Attribute Name](#find-by-attribute-name)
        - [Find by Class Name](#find-by-class-name)
        - [Find by Target Type](#find-by-target-type)
        - [Cache Management](#cache-management)
    - [Working with AttributeInfo](#working-with-attributeinfo)
    - [Example: Building a Route Registry](#example-building-a-route-registry)
- [Console Commands](#console-commands)
    - [Discover Attributes](#discover-attributes)
    - [List Attributes](#list-attributes)
    - [Inspect Attributes](#inspect-attributes)
- [DebugKit Panel](#debugkit-panel)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

## Overview

The CakePHP Attribute Registry Plugin provides a centralized system for discovering and managing PHP 8 attributes throughout your CakePHP application. It scans your codebase for attributes on classes, methods, properties, parameters, and constants, then caches the results for fast retrieval.

**Key Features:**

- 🔍 **Automatic Discovery** - Scans PHP files for attributes using configurable glob patterns
- 💾 **Built-in Caching** - Caches discovered attributes
- 🔎 **Flexible Querying** - Find attributes by name, class, or target type
- 🔌 **Plugin Support** - Automatically scans all loaded CakePHP plugins
- 🖥️ **CLI Tools** - Console commands for discovery, listing, and inspection
- 🏗️ **Service-Oriented** - Clean architecture with dependency injection via CakePHP's container
- 🐛 **DebugKit Panel** - Visual panel for browsing discovered attributes during development

## Requirements

- PHP 8.2 or higher
- CakePHP 5.2 or higher

## Installation

Install the plugin using Composer:

```bash
composer require josbeir/cakephp-attribute-registry
```

Load the plugin in your `src/Application.php`:

```bash
bin/cake plugin load AttributeRegistry
```

## Configuration

The plugin works out of the box with sensible defaults.

### Configuration Options

```php
// config/app_attribute_registry.php
return [
    'AttributeRegistry' => [
        'cache' => [
            // Enable/disable caching (default: true)
            // When disabled, attributes are re-discovered on every request
            'enabled' => true,
            // Cache configuration name (plugin auto-registers 'attribute_registry')
            // Override with CACHE_ATTRIBUTE_REGISTRY_URL env var for custom backends
            'config' => 'attribute_registry',
        ],
        'scanner' => [
            // Glob patterns for files to scan (relative to base paths)
            'paths' => [
                'src/**/*.php',
            ],
            // Glob patterns for paths to exclude
            'exclude_paths' => [
                'vendor/**',
                'tmp/**',
                'logs/**',
                'tests/**',
                'webroot/**',
            ],
            // Attribute classes to exclude from discovery
            'exclude_attributes' => [
                'Override',           // Exact FQCN match
                'App\\Internal\\*',   // Namespace wildcard
            ],
            // Maximum file size to scan (in bytes)
            'max_file_size' => 1024 * 1024, // 1 MB
        ],
    ],
];
```

### Disabling Cache

You can disable caching for development purposes by setting `cache.enabled` to `false`:

```php
'AttributeRegistry' => [
    'cache' => [
        'enabled' => false,
    ],
],
```

> [!WARNING]
> Disabling cache will cause attributes to be re-discovered on every request, which may impact performance. Only use this for development.

### Cache Configuration

The plugin automatically registers a file-based cache configuration named `attribute_registry`. You can override this by:

1. **Environment Variable**: Set the `CACHE_ATTRIBUTE_REGISTRY_URL` environment variable to use a custom cache backend:

   ```bash
   # Redis example
   export CACHE_ATTRIBUTE_REGISTRY_URL="redis://localhost:6379?prefix=my_app_attr_&duration=2592000"

   # Memcached example
   export CACHE_ATTRIBUTE_REGISTRY_URL="memcached://localhost:11211?prefix=my_app_attr_&duration=2592000"
   ```

2. **Manual Configuration**: Define your own `attribute_registry` cache config in `config/app.php` before the plugin loads

## Usage

### Accessing the AttributeRegistry

The `AttributeRegistry` can be accessed in two ways:

#### Option 1: Singleton

Use `getInstance()` anywhere in your application without requiring dependency injection:

```php
use AttributeRegistry\AttributeRegistry;

// Anywhere in your code
$registry = AttributeRegistry::getInstance();
$routes = $registry->findByAttribute('Route');
```

#### Option 2: Dependency Injection

The registry is also available via CakePHP's dependency injection container:

```php
use AttributeRegistry\AttributeRegistry;

// In a Controller
class MyController extends AppController
{
    public function index(AttributeRegistry $registry): Response
    {
        $routes = $registry->findByAttribute('Route');
        // ...
    }
}

// In a Command
class MyCommand extends Command
{
    public function __construct(
        private readonly AttributeRegistry $registry,
    ) {
        parent::__construct();
    }
}
```

Both approaches return the same singleton instance, ensuring consistent caching behavior.

### Discovery Methods

The `AttributeRegistry` service provides several methods for finding attributes:

> [!NOTE]
> All query methods (`findByAttribute`, `findByClass`, `findByTargetType`) internally call `discover()`. The discovery result is cached after the first call, so subsequent queries within the same request are fast. When adding new attributes to your codebase, clear the cache using `$registry->clearCache()` or run `bin/cake attribute discover` to refresh the registry.

#### Discover All Attributes

```php
// Get all discovered attributes (cached automatically)
$attributes = $registry->discover();
```

#### Find by Attribute Name

```php
// Find all usages of a specific attribute
$routes = $registry->findByAttribute(Route::class);
$columns = $registry->findByAttribute(Column::class);

// Partial matching is also supported
$routes = $registry->findByAttribute('Route');
```

#### Find by Class Name

```php
// Find attributes on a specific class
$attributes = $registry->findByClass(UserController::class);

// Partial matching is also supported
$attributes = $registry->findByClass('Controller');
```

#### Find by Target Type

```php
use AttributeRegistry\Enum\AttributeTargetType;

// Find all class-level attributes
$classAttributes = $registry->findByTargetType(AttributeTargetType::CLASS_TYPE);

// Find all method-level attributes
$methodAttributes = $registry->findByTargetType(AttributeTargetType::METHOD);

// Find all property-level attributes
$propertyAttributes = $registry->findByTargetType(AttributeTargetType::PROPERTY);
```

#### Cache Management

```php
// Clear all cached attribute data
$registry->clearCache();

// Warm the cache (clear and rediscover)
$registry->warmCache();
```

### Working with AttributeInfo

Each discovered attribute is returned as an `AttributeInfo` value object:

```php
use AttributeRegistry\ValueObject\AttributeInfo;

foreach ($registry->discover() as $attr) {
    // Basic information
    echo $attr->attributeName;  // Full attribute class name
    echo $attr->className;      // Class containing the attribute
    echo $attr->filePath;       // File where attribute was found
    echo $attr->lineNumber;     // Line number in file
    echo $attr->fileModTime;    // File modification timestamp

    // Attribute arguments
    print_r($attr->arguments);  // Array of constructor arguments

    // Target information
    echo $attr->target->type->value;    // 'class', 'method', 'property', etc.
    echo $attr->target->targetName;     // Name of the target element
    echo $attr->target->parentClass;    // Parent class (for methods/properties)

    // Instantiate the actual attribute
    $instance = $attr->getInstance();

    // With type safety
    $route = $attr->getInstance(MyRoute::class);
}
```

### Example: Building a Route Registry

```php
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Get
{
    public function __construct(
        public ?string $path = null,
    ) {}
}

// Controller
#[Route('/users')]
class UsersController
{
    #[Get('/')]
    public function index(): void {}

    #[Get('/{id}')]
    public function view(int $id): void {}
}

// In your application
$routes = $registry->findByAttribute(Route::class);
foreach ($routes as $routeInfo) {
    $route = $routeInfo->getInstance(Route::class);
    echo "Route: {$route->path} ({$route->method})";
}
```

## Console Commands

The plugin provides three console commands for managing attributes:

### Discover Attributes

Scan and cache all attributes:

```bash
bin/cake attribute discover
```

Output:
```
Clearing attribute cache...
Discovering attributes...
Discovered 42 attributes in 0.234s
```

### List Attributes

List discovered attributes with optional filtering:

```bash
# List all attributes
bin/cake attribute list

# Filter by attribute name
bin/cake attribute list --attribute Route

# Filter by class name
bin/cake attribute list --class UserController

# Filter by target type
bin/cake attribute list --type method
```

Output:
```
Found 5 attributes:

+-----------+-----------------+--------+--------+
| Attribute | Class           | Type   | Target |
+-----------+-----------------+--------+--------+
| Route     | UsersController | class  | Users  |
| Get       | UsersController | method | index  |
| Get       | UsersController | method | view   |
+-----------+-----------------+--------+--------+
```

### Inspect Attributes

View detailed information about specific attributes:

```bash
# Inspect by attribute name
bin/cake attribute inspect Route

# Inspect attributes on a specific class
bin/cake attribute inspect --class UserController
bin/cake attribute inspect -c UserController
```

Output:
```
Found 2 attributes for attribute "Route":

1. App\Attribute\Route
   Class: App\Controller\UsersController
   Target: UsersController (class)
   File: /path/to/src/Controller/UsersController.php:12
   Arguments:
     - path: /users
     - method: GET
```

## DebugKit Panel

When [DebugKit](https://github.com/cakephp/debug_kit) is installed, the plugin automatically registers a panel for browsing discovered attributes.

![DebugKit Panel](docs/debug_kit_screenshot.png)

The panel provides:
- Overview of all discovered attributes grouped by type or file
- Search functionality to filter attributes
- Re-discover button to refresh the attribute cache

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run PHPStan analysis
composer phpstan

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run Rector checks
composer rector-check
```

## Contributing

Contributions are welcome! Please follow these guidelines:

1. **Code Standards**: Follow CakePHP coding standards
2. **Tests**: Add tests for new features
3. **PHPStan**: Ensure level 8 compliance
4. **Documentation**: Update README for new features

### Development Setup

```bash
# Clone the repository
git clone git@github.com:josbeir/cakephp-attribute-registry.git
cd cakephp-attribute-registry

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## License

This plugin is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

- Built with [CakePHP](https://cakephp.org/)
