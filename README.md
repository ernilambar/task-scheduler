# Task Scheduler

A clean and robust WordPress task scheduling library built on top of Action Scheduler. This library provides a simple interface for scheduling one-time and recurring tasks with built-in uniqueness checking and error handling.

## Features

- **Simple API**: Clean interface for scheduling tasks
- **Flexible Uniqueness**: Multiple uniqueness levels (hook, group, args, or none)
- **Error Handling**: Comprehensive error handling with WP_Error objects
- **Flexible Configuration**: Customizable hook prefixes, groups, and logging
- **Task Management**: Cancel, query, and manage scheduled tasks
- **WordPress Standards**: Follows WordPress coding standards and best practices

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Action Scheduler

## Installation

### Via Composer

```bash
composer require ernilambar/task-scheduler
```

### Manual Installation

1. Download the library files
2. Place them in your WordPress plugin or theme
3. Include the main file:

```php
require_once 'path/to/task-scheduler/src/Task_Scheduler.php';
```

## Basic Usage

### Getting an Instance

Get a configured Task Scheduler instance. Instances are cached by configuration, so calling with the same parameters returns the same instance:

```php
use Nilambar\Task_Scheduler\Task_Scheduler;

// Get or create an instance with custom settings.
$scheduler = Task_Scheduler::get_instance(
    'myplugin_',           // Hook prefix.
    'myplugin_default',    // Default group.
    'MyPlugin'             // Log prefix.
);
```

### Scheduling Tasks

#### One-time Tasks

```php
// Schedule a simple task (no uniqueness check by default).
$task_id = $scheduler->add_task(
    'process_item',
    60,                    // 60 second delay.
    ['item_id' => 123],    // Arguments.
    'my_group'             // Optional group.
);

// Schedule with hook-only uniqueness.
$task_id = $scheduler->add_task(
    'send_notification',
    0,
    ['user_id' => 123],
    'notifications',
    null,                  // Priority (optional).
    Task_Scheduler::UNIQUE_HOOK  // Only one task with this hook.
);

// Schedule with group uniqueness.
$task_id = $scheduler->add_task(
    'backup_site',
    3600,
    ['site_id' => 123],
    'site_123',
    null,
    Task_Scheduler::UNIQUE_GROUP  // Only one task per group.
);

// Schedule with full uniqueness (hook + group + args).
$task_id = $scheduler->add_task(
    'sync_data',
    0,
    ['user_id' => 123, 'type' => 'profile'],
    'data_sync',
    null,
    Task_Scheduler::UNIQUE_ARGS  // Only one task with exact same parameters.
);
```

#### Recurring Tasks

```php
// Schedule a recurring task (no uniqueness check by default).
$task_id = $scheduler->add_repeating_task(
    'cleanup_data',
    3600,                  // Run every hour.
    ['type' => 'temp'],    // Arguments.
    0,                     // No initial delay.
    'maintenance'          // Group.
);

// Schedule unique recurring task with hook-only uniqueness.
$task_id = $scheduler->add_repeating_task(
    'daily_backup',
    86400,                 // Run every day.
    ['backup_type' => 'full'],
    0,
    'backups',
    null,                  // Priority (optional).
    null,                  // Max runs (optional).
    Task_Scheduler::UNIQUE_HOOK  // Only one daily backup task.
);
```

### Handling Task Callbacks

When your scheduled tasks execute, the callback function receives the task arguments directly:

```php
$scheduler = Task_Scheduler::get_instance('myplugin_', 'myplugin_default', 'MyPlugin');

// Schedule a task
$task_id = $scheduler->add_task(
    'process_user_data',
    0,
    [
        'user_id' => 123,
        'action' => 'update_profile',
        'data' => ['name' => 'John Doe', 'email' => 'john@example.com']
    ],
    'user_processing',
    null,
    Task_Scheduler::UNIQUE_ARGS
);

// Handle the task callback
add_action('myplugin_process_user_data', function( $args ) {
    $user_id = $args['user_id'] ?? 0;
    $action = $args['action'] ?? '';
    $data = $args['data'] ?? [];

    if ( empty( $user_id ) || empty( $action ) ) {
        error_log( 'Missing required parameters for user data processing' );
        return;
    }

    // Process the user data
    switch ( $action ) {
        case 'update_profile':
            update_user_meta( $user_id, 'profile_data', $data );
            break;
        default:
            error_log( "Unknown action: {$action}" );
    }
});
```

### Task Management

```php
$scheduler = Task_Scheduler::get_instance('myplugin_', 'myplugin_default', 'MyPlugin');

// Cancel a specific task.
$result = $scheduler->cancel_task($task_id);

// Get task status.
$status = $scheduler->get_task_status($task_id);

// Get tasks by group.
$tasks = $scheduler->get_tasks_by_group('my_group', 'pending', 10);

// Get tasks by arguments.
$tasks = $scheduler->get_tasks_by_args('process_item', ['item_id' => 123]);

// Find specific task.
$task = $scheduler->get_task_by_hook_and_args('process_item', ['item_id' => 123]);

// Check if a task exists.
$exists = $scheduler->has_scheduled_task('process_item', 'my_group');

// Check if a recurring task exists.
$recurring_exists = $scheduler->has_scheduled_recurring_task('daily_backup', 'backups');

// Get count of tasks.
$count = $scheduler->get_task_count('process_item', ['item_id' => 123], 'my_group');

// Clear all tasks in a group.
$cleared_count = $scheduler->clear_group_tasks('my_group');
```

### Error Handling

The library uses WordPress's WP_Error system for error handling:

```php
$scheduler = Task_Scheduler::get_instance('myplugin_', 'myplugin_default', 'MyPlugin');
$result = $scheduler->add_task('invalid_hook', -1);

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    // Handle error appropriately.
}
```

## API Reference

### Factory Method

- `get_instance(string $hook_prefix, string $default_group, string $log_prefix)`: Get or create a configured instance

### Instance Methods

- `get_hook_prefix()`: Get current hook prefix
- `get_default_group()`: Get current default group
- `get_log_prefix()`: Get current log prefix
- `add_task(string $hook, int $delay, array $args, string $group, int|null $priority, string $unique)`: Add one-time task
- `add_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs, string $unique)`: Add recurring task
- `add_unique_task(string $hook, int $delay, array $args, string $group, int|null $priority, string $unique)`: Add unique one-time task
- `add_unique_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs, string $unique)`: Add unique recurring task
- `cancel_task(int $action_id)`: Cancel a scheduled task
- `get_task_status(int $action_id)`: Get task status
- `get_tasks_by_group(string $group, string $status, int $limit)`: Get tasks by group
- `get_tasks_by_args(string $hook, array $args, string $group, string $status)`: Get tasks by arguments
- `get_task_by_hook_and_args(string $hook, array $args, string $group)`: Find specific task
- `has_scheduled_task(string $hook, string $group, string $status)`: Check if a task exists
- `has_scheduled_recurring_task(string $hook, string $group, string $status)`: Check if a recurring task exists
- `get_task_count(string $hook, array $args, string $group, string $status)`: Get count of tasks
- `clear_group_tasks(string $group)`: Clear all tasks in a group
- `delete_task(int $action_id)`: Delete a specific task

### Static Utility Methods

- `is_available()`: Check if Action Scheduler is available
- `get_initialization_status()`: Get detailed initialization status

## Uniqueness Levels

The library provides flexible uniqueness checking with four levels:

### Uniqueness Constants

- `Task_Scheduler::UNIQUE_NONE` - No uniqueness check (default)
- `Task_Scheduler::UNIQUE_HOOK` - Unique by hook only
- `Task_Scheduler::UNIQUE_GROUP` - Unique by hook + group
- `Task_Scheduler::UNIQUE_ARGS` - Unique by hook + group + args

### Default Behavior

By default, the library uses `UNIQUE_NONE`, which means:
- Multiple identical tasks can be scheduled
- No uniqueness checks are performed
- Better performance for high-frequency tasks
- Follows Action Scheduler's default behavior

### When to Use Each Level

- **UNIQUE_NONE**: Logging, monitoring, queue processing, testing
- **UNIQUE_HOOK**: Global system tasks, maintenance operations
- **UNIQUE_GROUP**: Per-site, per-user, or per-tenant operations
- **UNIQUE_ARGS**: Specific operations with exact parameters

### Example Use Cases

```php
$scheduler = Task_Scheduler::get_instance('myplugin_', 'myplugin_default', 'MyPlugin');

// Allow multiple identical tasks (default)
$scheduler->add_task('log_event', 0, ['event' => 'user_login']);

// Only one global maintenance task
$scheduler->add_task('system_maintenance', 0, [], 'maintenance', null, Task_Scheduler::UNIQUE_HOOK);

// Only one backup per site
$scheduler->add_task('backup_site', 3600, ['site_id' => 123], 'site_123', null, Task_Scheduler::UNIQUE_GROUP);

// Only one specific sync operation
$scheduler->add_task('sync_user', 0, ['user_id' => 456, 'type' => 'profile'], 'sync', null, Task_Scheduler::UNIQUE_ARGS);
```

When a duplicate task is detected, the existing task ID is returned instead of creating a new one. This prevents:
- Duplicate processing
- Resource waste
- Inconsistent state

## Error Codes

Common error codes returned by the library:

- `action_scheduler_not_available`: Action Scheduler is not available
- `invalid_hook`: Hook name is empty or invalid
- `invalid_delay`: Delay value is negative
- `invalid_interval`: Interval value is not positive
- `schedule_failed`: Failed to schedule the action
- `cancel_failed`: Failed to cancel the task
- `task_not_found`: Task not found
- `queue_error`: General queue error

## Best Practices

1. **Get instance once**: Store the scheduler instance in a class property or helper function to avoid repeated calls
2. **Always check for errors**: Use `is_wp_error()` to check return values
3. **Use meaningful hook names**: Make hook names descriptive and unique
4. **Group related tasks**: Use groups to organize and manage related tasks
5. **Choose appropriate uniqueness level**: Use UNIQUE_NONE for logging/monitoring, UNIQUE_ARGS for critical tasks
6. **Monitor task execution**: Use the query methods to monitor task status
7. **Clean up old tasks**: Regularly clear completed or failed tasks
8. **Use array arguments**: Pass arguments as arrays for future-proof callback functions

## Contributing

Contributions are welcome! Please ensure your code follows WordPress coding standards and includes proper documentation.

## License

This project is licensed under the [MIT](https://opensource.org/license/MIT).
