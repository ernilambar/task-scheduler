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

### Configuration

First, configure the Task Scheduler with your preferred settings:

```php
use Nilambar\Task_Scheduler\Task_Scheduler;

// Configure with custom settings.
Task_Scheduler::configure(
    'myplugin_',           // Hook prefix.
    'myplugin_default',    // Default group.
    'MyPlugin'             // Log prefix.
);
```

### Scheduling Tasks

#### One-time Tasks

```php
// Schedule a simple task (no uniqueness check by default).
$task_id = Task_Scheduler::add_task(
    'process_item',
    60,                    // 60 second delay.
    ['item_id' => 123],    // Arguments.
    'my_group'             // Optional group.
);

// Schedule with hook-only uniqueness.
$task_id = Task_Scheduler::add_task(
    'send_notification',
    0,
    ['user_id' => 123],
    'notifications',
    null,                  // Priority (optional).
    Task_Scheduler::UNIQUE_HOOK  // Only one task with this hook.
);

// Schedule with group uniqueness.
$task_id = Task_Scheduler::add_task(
    'backup_site',
    3600,
    ['site_id' => 123],
    'site_123',
    null,
    Task_Scheduler::UNIQUE_GROUP  // Only one task per group.
);

// Schedule with full uniqueness (hook + group + args).
$task_id = Task_Scheduler::add_task(
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
$task_id = Task_Scheduler::add_repeating_task(
    'cleanup_data',
    3600,                  // Run every hour.
    ['type' => 'temp'],    // Arguments.
    0,                     // No initial delay.
    'maintenance'          // Group.
);

// Schedule unique recurring task with hook-only uniqueness.
$task_id = Task_Scheduler::add_repeating_task(
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
// Schedule a task
$task_id = Task_Scheduler::add_task(
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
public function process_user_data_callback( array $args ) {
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
            $this->update_user_profile( $user_id, $data );
            break;
        default:
            error_log( "Unknown action: {$action}" );
    }
}
```

#### Simple Example

```php
// Schedule
Task_Scheduler::add_task(
    'send_email',
    0,
    ['to' => 'user@example.com', 'subject' => 'Welcome!'],
    'emails',
    null,
    Task_Scheduler::UNIQUE_ARGS
);

// Callback
public function send_email_callback( array $args ) {
    $to = $args['to'] ?? '';
    $subject = $args['subject'] ?? '';

    if ( ! empty( $to ) && ! empty( $subject ) ) {
        wp_mail( $to, $subject, 'Your email content here' );
    }
}
```

### Task Management

#### Cancel Tasks

```php
// Cancel a specific task.
$result = Task_Scheduler::cancel_task($task_id);

if (is_wp_error($result)) {
    // Handle error.
    echo $result->get_error_message();
} else {
    // Task cancelled successfully.
    echo "Task cancelled";
}
```

#### Query Tasks

```php
// Get task status.
$status = Task_Scheduler::get_task_status($task_id);

// Get tasks by group.
$tasks = Task_Scheduler::get_tasks_by_group('my_group', 'pending', 10);

// Get tasks by arguments.
$tasks = Task_Scheduler::get_tasks_by_args('process_item', ['item_id' => 123]);

// Find specific task.
$task = Task_Scheduler::get_task_by_hook_and_args('process_item', ['item_id' => 123]);

// Check if a task exists.
$exists = Task_Scheduler::has_scheduled_task('process_item', 'my_group');

// Check if a recurring task exists.
$recurring_exists = Task_Scheduler::has_scheduled_recurring_task('daily_backup', 'backups');

// Get count of tasks.
$count = Task_Scheduler::get_task_count('process_item', ['item_id' => 123], 'my_group');
```

#### Clear Tasks

```php
// Clear all tasks in a group.
$cleared_count = Task_Scheduler::clear_group_tasks('my_group');
```

### Error Handling

The library uses WordPress's WP_Error system for error handling:

```php
$result = Task_Scheduler::add_task('invalid_hook', -1);

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();

    switch ($error_code) {
        case 'action_scheduler_not_available':
            // Action Scheduler not available.
            break;
        case 'invalid_hook':
            // Invalid hook name.
            break;
        case 'invalid_delay':
            // Invalid delay value.
            break;
        default:
            // Handle other errors.
            break;
    }
}
```

## API Reference

### Configuration Methods

- `configure(string $hook_prefix, string $default_group, string $log_prefix)`: Configure the scheduler
- `get_hook_prefix()`: Get current hook prefix
- `get_default_group()`: Get current default group
- `get_log_prefix()`: Get current log prefix

### Task Scheduling Methods

- `add_task(string $hook, int $delay, array $args, string $group, int|null $priority, string $unique)`: Add one-time task
- `add_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs, string $unique)`: Add recurring task
- `add_unique_task(string $hook, int $delay, array $args, string $group, int|null $priority, string $unique)`: Add unique one-time task
- `add_unique_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs, string $unique)`: Add unique recurring task

### Task Management Methods

- `cancel_task(int $action_id)`: Cancel a scheduled task
- `get_task_status(int $action_id)`: Get task status
- `get_tasks_by_group(string $group, string $status, int $limit)`: Get tasks by group
- `get_tasks_by_args(string $hook, array $args, string $group, string $status)`: Get tasks by arguments
- `get_task_by_hook_and_args(string $hook, array $args, string $group)`: Find specific task
- `has_scheduled_task(string $hook, string $group, string $status)`: Check if a task exists
- `has_scheduled_recurring_task(string $hook, string $group, string $status)`: Check if a recurring task exists
- `get_task_count(string $hook, array $args, string $group, string $status)`: Get count of tasks
- `clear_group_tasks(string $group)`: Clear all tasks in a group

### Utility Methods

- `is_available()`: Check if Action Scheduler is available

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
// Allow multiple identical tasks (default)
Task_Scheduler::add_task('log_event', 0, ['event' => 'user_login']);

// Only one global maintenance task
Task_Scheduler::add_task('system_maintenance', 0, [], 'maintenance', null, Task_Scheduler::UNIQUE_HOOK);

// Only one backup per site
Task_Scheduler::add_task('backup_site', 3600, ['site_id' => 123], 'site_123', null, Task_Scheduler::UNIQUE_GROUP);

// Only one specific sync operation
Task_Scheduler::add_task('sync_user', 0, ['user_id' => 456, 'type' => 'profile'], 'sync', null, Task_Scheduler::UNIQUE_ARGS);
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

1. **Always check for errors**: Use `is_wp_error()` to check return values
2. **Use meaningful hook names**: Make hook names descriptive and unique
3. **Group related tasks**: Use groups to organize and manage related tasks
4. **Choose appropriate uniqueness level**: Use UNIQUE_NONE for logging/monitoring, UNIQUE_ARGS for critical tasks
5. **Monitor task execution**: Use the query methods to monitor task status
6. **Clean up old tasks**: Regularly clear completed or failed tasks
7. **Use array arguments**: Pass arguments as arrays for future-proof callback functions

## Contributing

Contributions are welcome! Please ensure your code follows WordPress coding standards and includes proper documentation.

## License

This project is licensed under the [MIT](https://opensource.org/license/MIT).
