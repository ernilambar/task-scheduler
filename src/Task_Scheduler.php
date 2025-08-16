<?php
/**
 * Task_Scheduler
 *
 * @package Task_Scheduler
 */

declare(strict_types=1);

namespace Nilambar\Task_Scheduler\Utils;

use WP_Error;

/**
 * Class Task_Scheduler.
 *
 * Provides a clean interface for Action Scheduler operations.
 *
 * Uniqueness Implementation:
 * - Checks for existing actions with same hook and arguments before scheduling
 * - Returns existing action ID if duplicate is found
 * - Prevents duplicate task creation
 *
 * Usage Example:
 * ```php
 * Task_Scheduler::configure(
 *     'myplugin_',           // Hook prefix
 *     'myplugin_default',    // Default group
 *     'MyPlugin'             // Log prefix
 * );
 *
 * $task_id = Task_Scheduler::add_task(
 *     'process_item',
 *     60,                    // 60 second delay
 *     ['item_id' => 123],    // Arguments
 *     'my_group'             // Optional group
 * );
 * ```
 *
 * @since 1.0.0
 */
class Task_Scheduler {

	/**
	 * Hook prefix for action scheduler actions.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $hook_prefix = 'queue_';

	/**
	 * Default group for actions.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $default_group = 'queue_default';

	/**
	 * Log prefix for error logging.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $log_prefix = 'Queue';

	/**
	 * Configure the Task_Scheduler class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_prefix  Hook prefix for actions (default: 'queue_').
	 * @param string $default_group Default group for actions (default: 'queue_default').
	 * @param string $log_prefix    Log prefix for error logging (default: 'Queue').
	 */
	public static function configure( string $hook_prefix = 'queue_', string $default_group = 'queue_default', string $log_prefix = 'Queue' ): void {
		self::$hook_prefix   = sanitize_key( $hook_prefix );
		self::$default_group = sanitize_key( $default_group );
		self::$log_prefix    = sanitize_text_field( $log_prefix );
	}

	/**
	 * Get the current hook prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Current hook prefix.
	 */
	public static function get_hook_prefix(): string {
		return self::$hook_prefix;
	}

	/**
	 * Get the current default group.
	 *
	 * @since 1.0.0
	 *
	 * @return string Current default group.
	 */
	public static function get_default_group(): string {
		return self::$default_group;
	}

	/**
	 * Get the current log prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Current log prefix.
	 */
	public static function get_log_prefix(): string {
		return self::$log_prefix;
	}

	/**
	 * Add a non-repeating task to the queue.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook     Action hook name (without prefix).
	 * @param int      $delay    Delay in seconds before execution (default: 0).
	 * @param array    $args     Arguments to pass to the action (default: []).
	 * @param string   $group    Action group (default: configured default group).
	 * @param int|null $priority Priority of the action (default: null).
	 * @param bool     $unique   Whether to ensure the action is unique (default: true).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_task( string $hook, int $delay = 0, array $args = [], string $group = '', ?int $priority = null, bool $unique = true ) {
		// If uniqueness is requested, use argument-based uniqueness check.
		if ( $unique ) {
			return self::add_unique_task_by_args( $hook, $delay, $args, $group, $priority );
		}

		return self::schedule_single_action( $hook, $delay, $args, $group, $priority );
	}

	/**
	 * Add a repeating task to the queue.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook        Action hook name (without prefix).
	 * @param int      $interval    Interval in seconds between executions.
	 * @param array    $args        Arguments to pass to the action (default: []).
	 * @param int      $delay       Initial delay in seconds (default: 0).
	 * @param string   $group       Action group (default: configured default group).
	 * @param int|null $priority    Priority of the action (default: null).
	 * @param int|null $max_runs    Maximum number of runs (default: null for unlimited).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_repeating_task( string $hook, int $interval, array $args = [], int $delay = 0, string $group = '', ?int $priority = null, ?int $max_runs = null ) {
		// Validate interval.
		if ( $interval <= 0 ) {
			return new WP_Error( 'invalid_interval', 'Interval must be greater than 0.' );
		}

		return self::schedule_recurring_action( $hook, $interval, $args, $delay, $group, $priority, $max_runs );
	}

	/**
	 * Add a non-repeating task to the queue with argument-based uniqueness.
	 *
	 * Checks for existing actions with same hook and arguments before scheduling.
	 * Returns existing action ID if duplicate is found.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook     Action hook name (without prefix).
	 * @param int      $delay    Delay in seconds before execution (default: 0).
	 * @param array    $args     Arguments to pass to the action (default: []).
	 * @param string   $group    Action group (default: configured default group).
	 * @param int|null $priority Priority of the action (default: null).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_unique_task_by_args( string $hook, int $delay = 0, array $args = [], string $group = '', ?int $priority = null ) {
		$validation_result = self::validate_common_params( $hook, $delay );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$processed_params = self::process_common_params( $hook, $delay, $group );
		if ( is_wp_error( $processed_params ) ) {
			return $processed_params;
		}

		$full_hook      = $processed_params['full_hook'];
		$execution_time = $processed_params['execution_time'];
		$group          = $processed_params['group'];

		return self::execute_with_uniqueness_check(
			$full_hook,
			$args,
			$group,
			$execution_time,
			$priority,
			'single'
		);
	}

	/**
	 * Add a repeating task to the queue with argument-based uniqueness.
	 *
	 * Checks for existing actions with same hook and arguments before scheduling.
	 * Returns existing action ID if duplicate is found.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook        Action hook name (without prefix).
	 * @param int      $interval    Interval in seconds between executions.
	 * @param array    $args        Arguments to pass to the action (default: []).
	 * @param int      $delay       Initial delay in seconds (default: 0).
	 * @param string   $group       Action group (default: configured default group).
	 * @param int|null $priority    Priority of the action (default: null).
	 * @param int|null $max_runs    Maximum number of runs (default: null for unlimited).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_unique_repeating_task_by_args( string $hook, int $interval, array $args = [], int $delay = 0, string $group = '', ?int $priority = null, ?int $max_runs = null ) {
		// Validate interval.
		if ( $interval <= 0 ) {
			return new WP_Error( 'invalid_interval', 'Interval must be greater than 0.' );
		}

		$validation_result = self::validate_common_params( $hook, $delay );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$processed_params = self::process_common_params( $hook, $delay, $group );
		if ( is_wp_error( $processed_params ) ) {
			return $processed_params;
		}

		$full_hook      = $processed_params['full_hook'];
		$execution_time = $processed_params['execution_time'];
		$group          = $processed_params['group'];

		return self::execute_with_uniqueness_check(
			$full_hook,
			$args,
			$group,
			$execution_time,
			$priority,
			'recurring',
			$interval,
			$max_runs
		);
	}

	/**
	 * Cancel a scheduled task.
	 *
	 * @since 1.0.0
	 *
	 * @param int $action_id Action ID to cancel.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function cancel_task( int $action_id ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		return self::execute_with_error_handling(
			function () use ( $action_id ) {
				$cancelled = as_unschedule_action( $action_id );

				if ( $cancelled ) {
					return true;
				}

				return new WP_Error( 'cancel_failed', 'Failed to cancel task. Task may not exist or already be completed.' );
			},
			'Error cancelling task: ',
			'Failed to cancel task.'
		);
	}

	/**
	 * Get task status.
	 *
	 * @since 1.0.0
	 *
	 * @param int $action_id Action ID.
	 * @return string|WP_Error Task status or WP_Error on failure.
	 */
	public static function get_task_status( int $action_id ) {
		// Check if Action Scheduler is available.
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		return self::execute_with_error_handling(
			function () use ( $action_id ) {
				$action = ActionScheduler::store()->fetch_action( $action_id );

				if ( ! $action ) {
					return new WP_Error( 'task_not_found', 'Task not found.' );
				}

				return $action->get_status();
			},
			'Error getting task status: ',
			'Failed to get task status.'
		);
	}

	/**
	 * Get tasks by group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group Action group.
	 * @param string $status Task status filter (default: 'pending').
	 * @param int    $limit  Maximum number of tasks to return (default: 50).
	 * @return array|WP_Error Array of tasks or WP_Error on failure.
	 */
	public static function get_tasks_by_group( string $group, string $status = 'pending', int $limit = 50 ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		// Sanitize group name.
		$group = sanitize_key( $group );

		return self::execute_with_error_handling(
			function () use ( $group, $status, $limit ) {
				$args = [
					'group'    => $group,
					'status'   => $status,
					'per_page' => $limit,
				];

				$actions = as_get_scheduled_actions( $args, ARRAY_A );

				$result = [];
				foreach ( $actions as $action_id => $action ) {
					$result[] = [
						'id'       => $action_id,
						'hook'     => $action['hook'] ?? '',
						'args'     => $action['args'] ?? [],
						'group'    => $action['group'] ?? '',
						'status'   => $action['status'] ?? '',
						'schedule' => $action['schedule'] ?? null,
					];
				}

				return $result;
			},
			'Error getting tasks by group: ',
			'Failed to get tasks by group.'
		);
	}

	/**
	 * Clear all tasks for a specific group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group Action group.
	 * @return int|WP_Error Number of tasks cleared or WP_Error on failure.
	 */
	public static function clear_group_tasks( string $group ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_unschedule_action' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		// Sanitize group name.
		$group = sanitize_key( $group );

		return self::execute_with_error_handling(
			function () use ( $group ) {
				$args = [
					'group'  => $group,
					'status' => 'pending',
				];

				$actions       = as_get_scheduled_actions( $args, 'ids' );
				$cleared_count = 0;

				foreach ( $actions as $action_id ) {
					if ( as_unschedule_action( $action_id ) ) {
						++$cleared_count;
					}
				}

				return $cleared_count;
			},
			'Error clearing group tasks: ',
			'Failed to clear group tasks.'
		);
	}

	/**
	 * Get a specific task by hook and arguments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (with prefix).
	 * @param array  $args Arguments to match.
	 * @param string $group Action group (optional).
	 * @return array|false Task data if found, false otherwise.
	 */
	public static function get_task_by_hook_and_args( string $hook, array $args, string $group = '' ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		// Add prefix to hook if not already present.
		$full_hook = strpos( $hook, self::$hook_prefix ) === 0 ? $hook : self::$hook_prefix . $hook;

		// Sanitize group name.
		$group = sanitize_key( $group );

		try {
			$query_args = [
				'hook'   => $full_hook,
				'args'   => $args,
				'status' => [ 'pending', 'in-progress' ],
			];

			// Add group filter if specified.
			if ( ! empty( $group ) ) {
				$query_args['group'] = $group;
			}

			$actions = as_get_scheduled_actions( $query_args, 'ids' );

			if ( ! empty( $actions ) ) {
				// Return the first matching task ID.
				return [ 'id' => $actions[0] ];
			}

			return false;
		} catch ( \Exception $e ) {
			// Log the error for debugging.
			error_log( self::$log_prefix . ': Error getting task by hook and args: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if Action Scheduler is available and active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Action Scheduler is available, false otherwise.
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		if ( ! class_exists( 'ActionScheduler' ) ) {
			return false;
		}

		if ( ! \ActionScheduler::is_initialized() ) {
			return false;
		}

		// Check if Action Scheduler tables exist.
		global $wpdb;
		$table_name   = $wpdb->prefix . 'actionscheduler_actions';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		return $table_exists;
	}

	/**
	 * Get all tasks with specific arguments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (without prefix).
	 * @param array  $args Arguments to match.
	 * @param string $group Action group (optional).
	 * @param string $status Task status filter (default: 'pending').
	 * @return array|WP_Error Array of tasks or WP_Error on failure.
	 */
	public static function get_tasks_by_args( string $hook, array $args, string $group = '', string $status = 'pending' ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = strpos( $hook, self::$hook_prefix ) === 0 ? $hook : self::$hook_prefix . $hook;

		// Sanitize group name.
		$group = sanitize_key( $group );

		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $status ) {
				$query_args = [
					'hook'   => $full_hook,
					'args'   => $args,
					'status' => $status,
				];

				// Add group filter if specified.
				if ( ! empty( $group ) ) {
					$query_args['group'] = $group;
				}

				$actions = as_get_scheduled_actions( $query_args, ARRAY_A );

				$result = [];
				foreach ( $actions as $action_id => $action ) {
					$result[] = [
						'id'       => $action_id,
						'hook'     => $action['hook'] ?? '',
						'args'     => $action['args'] ?? [],
						'group'    => $action['group'] ?? '',
						'status'   => $action['status'] ?? '',
						'schedule' => $action['schedule'] ?? null,
					];
				}

				return $result;
			},
			'Error getting tasks by args: ',
			'Failed to get tasks by args.'
		);
	}

	/**
	 * Validate common parameters for task scheduling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook  Action hook name.
	 * @param int    $delay Delay in seconds.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function validate_common_params( string $hook, int $delay ) {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return new WP_Error( 'action_scheduler_not_available', 'Action Scheduler is not available.' );
		}

		// Validate hook name.
		if ( empty( $hook ) ) {
			return new WP_Error( 'invalid_hook', 'Hook name cannot be empty.' );
		}

		// Validate delay.
		if ( $delay < 0 ) {
			return new WP_Error( 'invalid_delay', 'Delay cannot be negative.' );
		}

		return true;
	}

	/**
	 * Process common parameters for task scheduling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook  Action hook name.
	 * @param int    $delay Delay in seconds.
	 * @param string $group Action group.
	 * @return array|WP_Error Processed parameters on success, WP_Error on failure.
	 */
	private static function process_common_params( string $hook, int $delay, string $group ) {
		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = strpos( $hook, self::$hook_prefix ) === 0 ? $hook : self::$hook_prefix . $hook;

		// Use default group if not specified.
		$group = ! empty( $group ) ? sanitize_key( $group ) : self::$default_group;

		// Calculate execution time.
		$execution_time = $delay > 0 ? time() + $delay : time();

		return [
			'full_hook'      => $full_hook,
			'group'          => $group,
			'execution_time' => $execution_time,
		];
	}

	/**
	 * Execute action scheduling with uniqueness check.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $full_hook      Full hook name with prefix.
	 * @param array    $args           Arguments to pass to the action.
	 * @param string   $group          Action group.
	 * @param int      $execution_time Execution time.
	 * @param int|null $priority       Priority of the action.
	 * @param string   $type           Action type ('single' or 'recurring').
	 * @param int|null $interval       Interval for recurring actions.
	 * @param int|null $max_runs       Maximum runs for recurring actions.
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	private static function execute_with_uniqueness_check( string $full_hook, array $args, string $group, int $execution_time, ?int $priority, string $type, ?int $interval = null, ?int $max_runs = null ) {
		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $execution_time, $priority, $type, $interval, $max_runs ) {
				// Check for existing actions with same hook and arguments.
				$existing_actions = as_get_scheduled_actions(
					[
						'hook'   => $full_hook,
						'args'   => $args,
						'status' => [ 'pending', 'running' ],
					],
					'ids'
				);

				// Return existing action ID if found.
				if ( ! empty( $existing_actions ) ) {
					$existing_id = $existing_actions[0];
					$action_type = 'recurring' === $type ? 'recurring action' : 'action';
					error_log( sprintf( self::$log_prefix . ': Duplicate %s detected for hook "%s" with args %s. Returning existing action ID: %d', $action_type, $full_hook, wp_json_encode( $args ), $existing_id ) );
					return $existing_id;
				}

				// Schedule the action.
				if ( 'recurring' === $type ) {
					$action_id = as_schedule_recurring_action( $execution_time, $interval, $full_hook, $args, $group, false, $priority ?? 10 );
				} else {
					$action_id = as_schedule_single_action( $execution_time, $full_hook, $args, $group, false, $priority ?? 10 );
				}

				if ( 0 === $action_id ) {
					$action_type = 'recurring' === $type ? 'recurring action' : 'action';
					return new WP_Error( 'schedule_failed', sprintf( 'Failed to schedule %s.', $action_type ) );
				}

				return $action_id;
			},
			sprintf( 'Error adding unique %s task to queue: ', $type ),
			sprintf( 'Failed to add unique %s task to queue.', $type )
		);
	}

	/**
	 * Schedule a single action without uniqueness check.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook     Action hook name.
	 * @param int      $delay    Delay in seconds.
	 * @param array    $args     Arguments to pass to the action.
	 * @param string   $group    Action group.
	 * @param int|null $priority Priority of the action.
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	private static function schedule_single_action( string $hook, int $delay, array $args, string $group, ?int $priority ) {
		$validation_result = self::validate_common_params( $hook, $delay );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$processed_params = self::process_common_params( $hook, $delay, $group );
		if ( is_wp_error( $processed_params ) ) {
			return $processed_params;
		}

		$full_hook      = $processed_params['full_hook'];
		$execution_time = $processed_params['execution_time'];
		$group          = $processed_params['group'];

		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $execution_time, $priority ) {
				$action_id = as_schedule_single_action( $execution_time, $full_hook, $args, $group, false, $priority ?? 10 );

				if ( 0 === $action_id ) {
					return new WP_Error( 'schedule_failed', 'Failed to schedule action.' );
				}

				return $action_id;
			},
			'Error adding task to queue: ',
			'Failed to add task to queue.'
		);
	}

	/**
	 * Schedule a recurring action without uniqueness check.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook        Action hook name.
	 * @param int      $interval    Interval in seconds.
	 * @param array    $args        Arguments to pass to the action.
	 * @param int      $delay       Initial delay in seconds.
	 * @param string   $group       Action group.
	 * @param int|null $priority    Priority of the action.
	 * @param int|null $max_runs    Maximum number of runs.
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	private static function schedule_recurring_action( string $hook, int $interval, array $args, int $delay, string $group, ?int $priority, ?int $max_runs ) {
		$validation_result = self::validate_common_params( $hook, $delay );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$processed_params = self::process_common_params( $hook, $delay, $group );
		if ( is_wp_error( $processed_params ) ) {
			return $processed_params;
		}

		$full_hook      = $processed_params['full_hook'];
		$execution_time = $processed_params['execution_time'];
		$group          = $processed_params['group'];

		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $execution_time, $priority, $interval ) {
				$action_id = as_schedule_recurring_action( $execution_time, $interval, $full_hook, $args, $group, false, $priority ?? 10 );

				if ( 0 === $action_id ) {
					return new WP_Error( 'schedule_failed', 'Failed to schedule recurring action.' );
				}

				return $action_id;
			},
			'Error adding repeating task to queue: ',
			'Failed to add repeating task to queue.'
		);
	}

	/**
	 * Execute a function with standardized error handling.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback        Function to execute.
	 * @param string   $log_message     Log message prefix.
	 * @param string   $error_message   Error message for WP_Error.
	 * @return mixed Function result or WP_Error on failure.
	 */
	private static function execute_with_error_handling( callable $callback, string $log_message, string $error_message ) {
		try {
			return $callback();
		} catch ( \Exception $e ) {
			// Log the error for debugging.
			error_log( self::$log_prefix . ': ' . $log_message . $e->getMessage() );

			return new WP_Error(
				'queue_error',
				$error_message,
				[ 'exception' => $e->getMessage() ]
			);
		}
	}
}
