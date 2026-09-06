# AGENTS.md — Guidelines for AI Coding Agents

## Overview

Task Scheduler is a WordPress plugin library built on top of WooCommerce Action Scheduler. It provides a clean API for scheduling one-time and recurring tasks with uniqueness checking and WP_Error-based error handling. Core stack: PHP 7.4+, WordPress 6.8+, Composer, PHPCS (WordPress Coding Standards).

## Setup

```bash
composer install
```

No additional environment variables or database setup required. The package autoloads via PSR-4 (`Nilambar\Task_Scheduler\` → `src/`).

## Commands

```bash
composer run lint-php    # PHP syntax check (parallel-lint)
composer run phpcs       # WordPress Coding Standards check
composer run format      # Auto-fix coding standard violations
```

Note: There is no test suite. Static analysis (lint + phpcs) is the only automated quality gate. There are no CI workflows in this repository.

## Conventions

1. **Indentation**: 4-space tabs throughout PHP files. Markdown files use 2-space indent. See `.editorconfig` for full rules.
2. **Uniqueness levels**: Use class constants `UNIQUE_NONE`, `UNIQUE_HOOK`, `UNIQUE_GROUP`, `UNIQUE_ARGS` — never magic strings.
3. **Error handling**: All public methods must return `WP_Error` on failure; never throw exceptions from public APIs.
4. **Instance creation**: Always use `Task_Scheduler::get_instance($config)` as a factory; constructor is private.
5. **PHP compatibility**: Target PHP 7.4+. Do not use PHP 8.0+ features (union types, named arguments, attributes, etc.).

## Quality Gate

Before declaring any task complete, run these commands in sequence and verify each exits with code 0:

```bash
composer run format
composer run lint
```
