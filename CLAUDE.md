# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer run lint       # Full lint: parallel PHP syntax check + PHPCS
composer run lint-php   # PHP syntax check only (parallel-lint)
composer run phpcs      # WordPress Coding Standards check
composer run format     # Auto-fix with phpcbf
```

No test suite — static analysis is the only automated quality gate.

## Architecture

A single-class PHP library (`src/Task_Scheduler.php`) that wraps [WooCommerce Action Scheduler](https://actionscheduler.org/) with a cleaner API.

**Pattern: Singleton factory with config-based instance caching**
- `get_instance(array $config)` returns a cached instance keyed by config hash
- `configure()` is the deprecated backward-compatible entry point (since 2.0.0)
- Constructor is private — always use `get_instance()`

**Config keys**: `hook_prefix`, `default_group`, `log_prefix`, `uniqueness`

**Uniqueness levels** (class constants):
- `UNIQUE_NONE` — no deduplication
- `UNIQUE_HOOK` — unique per hook name
- `UNIQUE_GROUP` — unique per group
- `UNIQUE_ARGS` — unique per hook + args combination

**Error handling**: All public methods return `WP_Error` on failure (never throw exceptions).

**Dependency**: Action Scheduler must be active (`is_available()` / `get_initialization_status()` check this at runtime).

## Standards

- PHP 7.4+ minimum, `declare(strict_types=1)` required
- WordPress Coding Standards (WPCS) — tabs, not spaces
- PHPCompatibility checked against PHP 7.4
- Namespace: `Nilambar\Task_Scheduler`
