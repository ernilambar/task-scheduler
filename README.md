# Task Scheduler

A WordPress task scheduling library built on top of [Action Scheduler](https://actionscheduler.org/). Provides a clean API for scheduling one-time and recurring tasks with uniqueness checking and WP_Error-based error handling.

## Requirements

- PHP 7.4+
- WordPress 6.8+
- Action Scheduler 4.0+

## Installation

```bash
composer require ernilambar/task-scheduler
```

## Quick Start

```php
use Nilambar\Task_Scheduler\Task_Scheduler;

$scheduler = Task_Scheduler::get_instance( 'myplugin_', 'myplugin_default', 'MyPlugin' );

// One-time task.
$scheduler->add_task( 'process_item', 60, [ 'item_id' => 123 ] );

// Recurring task.
$scheduler->add_repeating_task( 'cleanup', 3600 );

// With uniqueness.
$scheduler->add_task( 'send_report', 0, [], 'reports', null, Task_Scheduler::UNIQUE_HOOK );
```

## Documentation

See [docs/GUIDE.md](docs/GUIDE.md) for full usage, API reference, uniqueness levels, error codes, and upgrade notes.

## License

[MIT](https://opensource.org/license/MIT)
