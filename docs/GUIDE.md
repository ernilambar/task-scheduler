# Task Scheduler — Guide

## Table of Contents

- [Getting an Instance](#getting-an-instance)
- [Scheduling Tasks](#scheduling-tasks)
- [Handling Task Callbacks](#handling-task-callbacks)
- [Task Management](#task-management)
- [Error Handling](#error-handling)
- [Uniqueness Levels](#uniqueness-levels)
- [API Reference](#api-reference)
- [Error Codes](#error-codes)
- [Best Practices](#best-practices)
- [Upgrading](#upgrading)
- [Behavior Notes](#behavior-notes)

---

## Getting an Instance

Instances are cached by configuration — calling with the same parameters returns the same instance.

```php
use Nilambar\Task_Scheduler\Task_Scheduler;

$scheduler = Task_Scheduler::get_instance(
    'myplugin_',           // Hook prefix.
    'myplugin_default',    // Default group.
    'MyPlugin'             // Log prefix.
);
```

---

## Scheduling Tasks

### One-time Tasks

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
    null,                         // Priority (optional).
    Task_Scheduler::UNIQUE_HOOK   // Only one task with this hook.
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
    Task_Scheduler::UNIQUE_ARGS   // Only one task with exact same parameters.
);
```

### Recurring Tasks

```php
// Schedule a recurring task (no uniqueness check by default).
$task_id = $scheduler->add_repeating_task(
    'cleanup_data',
    3600,                  // Run every hour.
    ['type' => 'temp'],    // Arguments.
    0,                     // No initial delay.
    'maintenance'          // Group.
);

// Schedule unique recurring task.
$task_id = $scheduler->add_repeating_task(
    'daily_backup',
    86400,                        // Run every day.
    ['backup_type' => 'full'],
    0,
    'backups',
    null,                         // Priority (optional).
    Task_Scheduler::UNIQUE_HOOK   // Only one daily backup task.
);
```

---

## Handling Task Callbacks

The callback receives the task arguments directly.

```php
$scheduler = Task_Scheduler::get_instance( 'myplugin_', 'myplugin_default', 'MyPlugin' );

$task_id = $scheduler->add_task(
    'process_user_data',
    0,
    [
        'user_id' => 123,
        'action'  => 'update_profile',
        'data'    => [ 'name' => 'John Doe', 'email' => 'john@example.com' ],
    ],
    'user_processing',
    null,
    Task_Scheduler::UNIQUE_ARGS
);

add_action( 'myplugin_process_user_data', function ( $args ) {
    $user_id = $args['user_id'] ?? 0;
    $action  = $args['action'] ?? '';
    $data    = $args['data'] ?? [];

    if ( empty( $user_id ) || empty( $action ) ) {
        return;
    }

    if ( 'update_profile' === $action ) {
        update_user_meta( $user_id, 'profile_data', $data );
    }
} );
```

---

## Task Management

```php
$scheduler = Task_Scheduler::get_instance( 'myplugin_', 'myplugin_default', 'MyPlugin' );

// Cancel a specific task.
$result = $scheduler->cancel_task( $task_id );

// Get task status.
$status = $scheduler->get_task_status( $task_id );

// Get tasks by group.
$tasks = $scheduler->get_tasks_by_group( 'my_group', 'pending', 10 );

// Get tasks by arguments.
$tasks = $scheduler->get_tasks_by_args( 'process_item', [ 'item_id' => 123 ] );

// Find specific task.
$task = $scheduler->get_task_by_hook_and_args( 'process_item', [ 'item_id' => 123 ] );

// Check if a task exists.
$exists = $scheduler->has_scheduled_task( 'process_item', 'my_group' );

// Check if a recurring task exists.
$exists = $scheduler->has_scheduled_recurring_task( 'daily_backup', 'backups' );

// Get count of tasks.
$count = $scheduler->get_task_count( 'process_item', [ 'item_id' => 123 ], 'my_group' );

// Clear all tasks in a group.
$cleared = $scheduler->clear_group_tasks( 'my_group' );

// Delete a specific task.
$result = $scheduler->delete_task( $task_id );
```

---

## Error Handling

All public methods return `WP_Error` on failure — never throw exceptions.

```php
$result = $scheduler->add_task( 'my_hook', -1 );

if ( is_wp_error( $result ) ) {
    $code    = $result->get_error_code();
    $message = $result->get_error_message();
}
```

---

## Uniqueness Levels

### Constants

| Constant | Deduplication key |
|---|---|
| `UNIQUE_NONE` | None (default) |
| `UNIQUE_HOOK` | Hook name |
| `UNIQUE_GROUP` | Hook + group |
| `UNIQUE_ARGS` | Hook + group + args |

When a duplicate is detected, the existing action ID is returned — no new action is created.

### When to Use Each Level

| Level | Use case |
|---|---|
| `UNIQUE_NONE` | Logging, monitoring, queue processing |
| `UNIQUE_HOOK` | Global system tasks, maintenance operations |
| `UNIQUE_GROUP` | Per-site, per-user, or per-tenant operations |
| `UNIQUE_ARGS` | Critical operations with exact parameters |

### Examples

```php
// Allow multiple identical tasks (default).
$scheduler->add_task( 'log_event', 0, [ 'event' => 'user_login' ] );

// Only one global maintenance task.
$scheduler->add_task( 'system_maintenance', 0, [], 'maintenance', null, Task_Scheduler::UNIQUE_HOOK );

// Only one backup per site.
$scheduler->add_task( 'backup_site', 3600, [ 'site_id' => 123 ], 'site_123', null, Task_Scheduler::UNIQUE_GROUP );

// Only one specific sync operation.
$scheduler->add_task( 'sync_user', 0, [ 'user_id' => 456, 'type' => 'profile' ], 'sync', null, Task_Scheduler::UNIQUE_ARGS );
```

---

## API Reference

### Factory Method

| Method | Description |
|---|---|
| `get_instance(string $hook_prefix, string $default_group, string $log_prefix)` | Get or create a configured instance |

### Instance Methods

| Method | Description |
|---|---|
| `get_hook_prefix()` | Get current hook prefix |
| `get_default_group()` | Get current default group |
| `get_log_prefix()` | Get current log prefix |
| `add_task(string $hook, int $delay, array $args, string $group, int\|null $priority, string $unique)` | Add one-time task |
| `add_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int\|null $priority, string $unique)` | Add recurring task |
| `add_unique_task(string $hook, int $delay, array $args, string $group, int\|null $priority, string $unique)` | Add unique one-time task |
| `add_unique_repeating_task(string $hook, int $interval, array $args, int $delay, string $group, int\|null $priority, string $unique)` | Add unique recurring task |
| `cancel_task(int $action_id)` | Cancel a scheduled task |
| `delete_task(int $action_id)` | Delete a specific task |
| `get_task_status(int $action_id)` | Get task status |
| `get_tasks_by_group(string $group, string $status, int $limit)` | Get tasks by group |
| `get_tasks_by_args(string $hook, array $args, string $group, string $status)` | Get tasks by arguments |
| `get_tasks_by_hook(string $hook, string $group, string $status)` | Get tasks by hook |
| `get_task_by_hook_and_args(string $hook, array $args, string $group)` | Find specific task |
| `get_task_count(string $hook, array $args, string $group, string $status)` | Get count of tasks |
| `has_scheduled_task(string $hook, string $group, string $status)` | Check if a task exists |
| `has_scheduled_recurring_task(string $hook, string $group)` | Check if a recurring task exists |
| `clear_group_tasks(string $group)` | Clear all tasks in a group |

### Static Utility Methods

| Method | Description |
|---|---|
| `is_available()` | Check if Action Scheduler is available |
| `get_initialization_status()` | Get detailed initialization status |

---

## Error Codes

| Code | Meaning |
|---|---|
| `wordpress_not_loaded` | WordPress is not loaded |
| `action_scheduler_not_loaded` | Action Scheduler could not be loaded |
| `action_scheduler_not_initialized` | Action Scheduler loaded but not initialized |
| `action_scheduler_tables_missing` | Database tables not found |
| `action_scheduler_unknown_error` | Action Scheduler not ready for unknown reasons |
| `invalid_hook` | Hook name is empty |
| `invalid_delay` | Delay value is negative |
| `invalid_interval` | Interval value is not positive |
| `schedule_failed` | Failed to schedule the action |
| `cancel_failed` | Failed to cancel the task |
| `delete_failed` | Failed to delete the task |
| `task_not_found` | Task not found |
| `queue_error` | General queue error |

---

## Best Practices

1. Store the scheduler instance in a class property or helper to avoid repeated `get_instance()` calls.
2. Always check return values with `is_wp_error()`.
3. Use meaningful, namespaced hook names to avoid collisions.
4. Group related tasks so they can be queried and cleared together.
5. Use `UNIQUE_NONE` for high-frequency tasks (logging, monitoring) and `UNIQUE_ARGS` for critical operations.
6. Pass arguments as arrays to keep callback signatures future-proof.

---

## Upgrading

### 2.x → 3.0.0

**WordPress 6.8+ now required** (transitively via Action Scheduler 4.0). Verify your environment before upgrading.

**`configure()` removed.** Replace any calls with `get_instance()`:

```php
// Before
Task_Scheduler::configure( 'myplugin_', 'myplugin_default', 'MyPlugin' );

// After
Task_Scheduler::get_instance( 'myplugin_', 'myplugin_default', 'MyPlugin' );
```

**`$max_runs` removed from `add_repeating_task()` and `add_unique_repeating_task()`.** Action Scheduler has no native max-runs support — the parameter was silently broken. Implement run-counting yourself if needed (e.g. increment an option in the callback and cancel when the limit is reached).

```php
// Before
$scheduler->add_repeating_task( 'my_hook', 3600, [], 0, 'my_group', null, 5 );

// After
$scheduler->add_repeating_task( 'my_hook', 3600, [], 0, 'my_group' );
```

**`$status` removed from `has_scheduled_recurring_task()`.** The parameter was never applied — the method always checks both `pending` and `in-progress`. Remove the third argument if you were passing it.

```php
// Before
$scheduler->has_scheduled_recurring_task( 'my_hook', 'my_group', 'pending' );

// After
$scheduler->has_scheduled_recurring_task( 'my_hook', 'my_group' );
```

**Failed actions now auto-purged after 3 months.** See [Behavior Notes](#behavior-notes) below.

---

## Behavior Notes

**Failed action retention:** Action Scheduler 4.0 automatically purges failed actions after 3 months by default. Queries against old failed tasks (`get_task_status()`, `get_tasks_by_hook()`, etc.) will return not-found results past that window. Use the `action_scheduler_retention_period_for_failed` filter to adjust the window, or `action_scheduler_enable_failed_action_cleanup` to disable purging entirely.
