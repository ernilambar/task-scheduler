# Task Scheduler

A clean and robust WordPress task scheduling library built on top of Action Scheduler. This library provides a simple interface for scheduling one-time and recurring tasks with built-in uniqueness checking and error handling.

## Features

- **Simple API**: Clean interface for scheduling tasks
- **Uniqueness Checking**: Prevents duplicate task creation based on hook and arguments
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
// Schedule a simple task.
$task_id = Task_Scheduler::add_task(
    'process_item',
    60,                    // 60 second delay.
    ['item_id' => 123],    // Arguments.
    'my_group'             // Optional group.
);

// Schedule without uniqueness check.
$task_id = Task_Scheduler::add_task(
    'process_item',
    60,
    ['item_id' => 123],
    'my_group',
    null,                  // Priority (optional).
    false                  // Disable uniqueness check.
);
```

#### Recurring Tasks

```php
// Schedule a recurring task.
$task_id = Task_Scheduler::add_repeating_task(
    'cleanup_data',
    3600,                  // Run every hour.
    ['type' => 'temp'],    // Arguments.
0,                     // No initial delay.
'maintenance'          // Group.
);

// Schedule unique recurring task.
$task_id = Task_Scheduler::add_unique_repeating_task_by_args(
    'sync_data',
    1800,                  // Run every 30 minutes.
    ['source' => 'api'],   // Arguments.
0,                     // No initial delay.
'sync'                 // Group.
);
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

- `add_task(string $hook, int $delay, array $args, string $group, int|null $priority, bool $unique)`: Add one-time task
- `add_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs)`: Add recurring task
- `add_unique_task_by_args(string $hook, int $delay, array $args, string $group, int|null $priority)`: Add unique one-time task
- `add_unique_repeating_task_by_args(string $hook, int $interval, array $args, int $delay, string $group, int|null $priority, int|null $max_runs)`: Add unique recurring task

### Task Management Methods

- `cancel_task(int $action_id)`: Cancel a scheduled task
- `get_task_status(int $action_id)`: Get task status
- `get_tasks_by_group(string $group, string $status, int $limit)`: Get tasks by group
- `get_tasks_by_args(string $hook, array $args, string $group, string $status)`: Get tasks by arguments
- `get_task_by_hook_and_args(string $hook, array $args, string $group)`: Find specific task
- `clear_group_tasks(string $group)`: Clear all tasks in a group

### Utility Methods

- `is_available()`: Check if Action Scheduler is available

## Uniqueness Implementation

The library implements uniqueness checking based on:
- Hook name (with prefix)
- Arguments array
- Task status (pending or running)

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
4. **Handle uniqueness appropriately**: Enable uniqueness for critical tasks, disable for logging/monitoring
5. **Monitor task execution**: Use the query methods to monitor task status
6. **Clean up old tasks**: Regularly clear completed or failed tasks

## Contributing

Contributions are welcome! Please ensure your code follows WordPress coding standards and includes proper documentation.

## License

This project is licensed under the [MIT](https://opensource.org/license/MIT).
