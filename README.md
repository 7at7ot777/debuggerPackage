# Laravel Debugger Package

A powerful debugging tool for Laravel applications that allows you to monitor and analyze debug data in real-time with advanced filtering and visualization capabilities.

## Requirements

- PHP ^8.3
- Laravel ^12.19

## Installation

```bash
composer require mohamed_hathout/debugger
```

## Configuration

After installation, publish the configuration file:

```bash
php artisan vendor:publish --provider="MohamedHathout\Debugger\DebuggerServiceProvider"
```

This will publish:
- Configuration file (`config/debugger.php`)
- Views (optional)

## Usage

### Basic Debugging

```php
// Debug any variable
debug($variable);

// Debug an Eloquent query
debug_query($query);
```

### Configuration Options

In `config/debugger.php`:

```php
return [
    'truncate_tables' => false, // Whether to use TRUNCATE instead of DELETE when clearing data
    'sort' => 'desc', // Sort order for debug entries
    'route_name' => 'debugger', // URL path for the debug viewer
    'is_enabled' => true, // Enable/disable debugging
    'storage_type' => 'database', // 'database' or 'cache'
    
    'cache' => [
        'key_prefix' => 'debugger:',
        'counter_key' => 'debugger:counter',
        'index_key' => 'debugger:index',
        'files_key' => 'debugger:files',
        'ttl' => 3600, // Cache TTL in seconds
    ],
];
```

### Debug Viewer

Access the debug viewer at `/debugger` (or your configured route) to see all debug entries with:
- Real-time updates
- Type filtering (text, number, JSON)
- File filtering
- Search functionality
- Copy to clipboard
- Clear all data

## Features

- Debug any variable type (text, numbers, arrays, objects)
- Query debugging with bindings resolution
- Real-time debug viewer with Livewire
- Advanced filtering and search
- Database or cache storage options
- Configurable settings
- Bootstrap 5 UI with dark mode support

## License

The MIT License (MIT).
