<?php
/**
 * Task_Scheduler
 *
 * @package Task_Scheduler
 */

declare(strict_types=1);

namespace Nilambar\Task_Scheduler;

use WP_Error;
use Exception;
use ActionScheduler;

/**
 * Class Task_Scheduler.
 *
 * Provides a clean interface for Action Scheduler operations.
 *
 * @since 1.0.0
 */
class Task_Scheduler {

	/**
	 * Uniqueness levels.
	 *
	 * @since 1.0.0
	 */
	const UNIQUE_NONE  = 'none';
	const UNIQUE_HOOK  = 'hook';
	const UNIQUE_GROUP = 'group';
	const UNIQUE_ARGS  = 'args';

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
	 * Initialization status.
	 *
	 * @since 1.0.0
	 * @var bool|null
	 */
	private static ?bool $initialized = null;

	/**
	 * Initialize Action Scheduler if not already done.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Action Scheduler is ready, false otherwise.
	 */
	private static function ensure_action_scheduler_ready(): bool {
		// Check if already initialized.
		if ( null !== self::$initialized ) {
			return self::$initialized;
		}

		// Check WordPress context.
		if ( ! defined( 'ABSPATH' ) ) {
			self::$initialized = false;
			error_log( self::$log_prefix . ': WordPress context not available.' );
			return false;
		}

		// Check if Action Scheduler is already loaded and initialized.
		if ( class_exists( 'ActionScheduler' ) && ActionScheduler::is_initialized() ) {
			self::$initialized = true;
			return true;
		}

		// Try to load Action Scheduler from our vendor directory.
		if ( ! self::load_action_scheduler() ) {
			self::$initialized = false;
			return false;
		}

		// Initialize Action Scheduler.
		if ( ! self::initialize_action_scheduler() ) {
			self::$initialized = false;
			return false;
		}

		// Verify database tables exist.
		if ( ! self::verify_database_tables() ) {
			self::$initialized = false;
			return false;
		}

		self::$initialized = true;
		return true;
	}

	/**
	 * Load Action Scheduler from vendor directory.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if loaded successfully, false otherwise.
	 */
	private static function load_action_scheduler(): bool {
		// Check if Action Scheduler is already loaded.
		if ( class_exists( 'ActionScheduler' ) ) {
			return true;
		}

		// Load from our vendor directory.
		$action_scheduler_path = __DIR__ . '/../../vendor/woocommerce/action-scheduler/action-scheduler.php';

		if ( file_exists( $action_scheduler_path ) ) {
			require_once $action_scheduler_path;

			if ( class_exists( 'ActionScheduler' ) ) {
				return true;
			}
		}

		error_log( self::$log_prefix . ': Action Scheduler could not be loaded from vendor directory.' );
		return false;
	}

	/**
	 * Initialize Action Scheduler.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if initialized successfully, false otherwise.
	 */
	private static function initialize_action_scheduler(): bool {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			error_log( self::$log_prefix . ': ActionScheduler class not found.' );
			return false;
		}

				// Check if already initialized.
		if ( ActionScheduler::is_initialized() ) {
			return true;
		}

		try {
			// Initialize Action Scheduler.
			ActionScheduler::init( __FILE__ );

			if ( ActionScheduler::is_initialized() ) {
				return true;
			}
		} catch ( Exception $e ) {
			error_log( self::$log_prefix . ': Failed to initialize Action Scheduler: ' . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Verify Action Scheduler database tables exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if tables exist, false otherwise.
	 */
	private static function verify_database_tables(): bool {
		global $wpdb;

		if ( ! $wpdb ) {
			error_log( self::$log_prefix . ': WordPress database not available.' );
			return false;
		}

		$table_name   = $wpdb->prefix . 'actionscheduler_actions';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( ! $table_exists ) {
			error_log( self::$log_prefix . ': Action Scheduler database tables not found.' );
		}

		return $table_exists;
	}

	/**
	 * Validate Action Scheduler availability with detailed error messages.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True if available, WP_Error with details if not.
	 */
	private static function validate_action_scheduler_available() {
		if ( ! defined( 'ABSPATH' ) ) {
			return new WP_Error(
				'wordpress_not_loaded',
				'WordPress is not loaded. Task Scheduler requires WordPress context.'
			);
		}

		if ( ! self::ensure_action_scheduler_ready() ) {
			// Provide specific error messages based on what's missing.
			if ( ! class_exists( 'ActionScheduler' ) ) {
				return new WP_Error(
					'action_scheduler_not_loaded',
					'Action Scheduler could not be loaded. Please ensure the woocommerce/action-scheduler package is installed via Composer.'
				);
			}

			if ( ! ActionScheduler::is_initialized() ) {
				return new WP_Error(
					'action_scheduler_not_initialized',
					'Action Scheduler is loaded but not initialized. This may be due to WordPress loading order issues.'
				);
			}

			global $wpdb;
			$table_name   = $wpdb->prefix . 'actionscheduler_actions';
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

			if ( ! $table_exists ) {
				return new WP_Error(
					'action_scheduler_tables_missing',
					'Action Scheduler database tables are missing. Please ensure Action Scheduler has been properly installed and activated.'
				);
			}

			return new WP_Error(
				'action_scheduler_unknown_error',
				'Action Scheduler is not ready for unknown reasons. Please check error logs for details.'
			);
		}

		return true;
	}

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
	 * @param string   $unique   Uniqueness level (default: UNIQUE_NONE).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_task( string $hook, int $delay = 0, array $args = [], string $group = '', ?int $priority = null, string $unique = self::UNIQUE_NONE ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// If no uniqueness check is requested, schedule directly.
		if ( self::UNIQUE_NONE === $unique ) {
			return self::schedule_single_action( $hook, $delay, $args, $group, $priority );
		}

		return self::add_unique_task( $hook, $delay, $args, $group, $priority, $unique );
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
	 * @param string   $unique      Uniqueness level (default: UNIQUE_NONE).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_repeating_task( string $hook, int $interval, array $args = [], int $delay = 0, string $group = '', ?int $priority = null, ?int $max_runs = null, string $unique = self::UNIQUE_NONE ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Validate interval.
		if ( $interval <= 0 ) {
			return new WP_Error( 'invalid_interval', 'Interval must be greater than 0.' );
		}

		// If no uniqueness check is requested, schedule directly.
		if ( self::UNIQUE_NONE === $unique ) {
			return self::schedule_recurring_action( $hook, $interval, $args, $delay, $group, $priority, $max_runs );
		}

		return self::add_unique_repeating_task( $hook, $interval, $args, $delay, $group, $priority, $max_runs, $unique );
	}

	/**
	 * Add a non-repeating task to the queue with specified uniqueness level.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook     Action hook name (without prefix).
	 * @param int      $delay    Delay in seconds before execution (default: 0).
	 * @param array    $args     Arguments to pass to the action (default: []).
	 * @param string   $group    Action group (default: configured default group).
	 * @param int|null $priority Priority of the action (default: null).
	 * @param string   $unique   Uniqueness level (default: UNIQUE_NONE).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_unique_task( string $hook, int $delay = 0, array $args = [], string $group = '', ?int $priority = null, string $unique = self::UNIQUE_NONE ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
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
			'single',
			null,
			null,
			$unique
		);
	}

	/**
	 * Add a repeating task to the queue with specified uniqueness level.
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
	 * @param string   $unique      Uniqueness level (default: UNIQUE_NONE).
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	public static function add_unique_repeating_task( string $hook, int $interval, array $args = [], int $delay = 0, string $group = '', ?int $priority = null, ?int $max_runs = null, string $unique = self::UNIQUE_NONE ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

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
			$max_runs,
			$unique
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
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
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
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		return self::execute_with_error_handling(
			function () use ( $action_id ) {
				$store  = \ActionScheduler::store();
				$status = $store->get_status( $action_id );

				if ( false === $status ) {
					return new WP_Error( 'task_not_found', 'Task not found.' );
				}

				return $status;
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
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
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
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
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
		if ( ! self::ensure_action_scheduler_ready() ) {
			return false;
		}

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		try {
			$query_args = [
				'hook'   => $full_hook,
				'args'   => [ 'task_args' => $args ],
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
		} catch ( Exception $e ) {
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
		return self::ensure_action_scheduler_ready();
	}

	/**
	 * Get detailed initialization status.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status information.
	 */
	public static function get_initialization_status(): array {
		$status = [
			'wordpress_loaded'             => defined( 'ABSPATH' ),
			'action_scheduler_loaded'      => class_exists( 'ActionScheduler' ),
			'action_scheduler_initialized' => false,
			'database_tables_exist'        => false,
			'ready'                        => false,
			'errors'                       => [],
		];

		if ( ! $status['wordpress_loaded'] ) {
			$status['errors'][] = 'WordPress is not loaded';
			return $status;
		}

		if ( ! $status['action_scheduler_loaded'] ) {
			$status['errors'][] = 'Action Scheduler is not loaded';
			return $status;
		}

		$status['action_scheduler_initialized'] = ActionScheduler::is_initialized();

		if ( ! $status['action_scheduler_initialized'] ) {
			$status['errors'][] = 'Action Scheduler is not initialized';
			return $status;
		}

		global $wpdb;
		$table_name                      = $wpdb->prefix . 'actionscheduler_actions';
		$status['database_tables_exist'] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( ! $status['database_tables_exist'] ) {
			$status['errors'][] = 'Action Scheduler database tables do not exist';
			return $status;
		}

		$status['ready'] = true;
		return $status;
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
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $status ) {
				$query_args = [
					'hook'   => $full_hook,
					'args'   => [ 'task_args' => $args ],
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
	 * Check if a task with the specified hook exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (without prefix).
	 * @param string $group Action group (optional).
	 * @param string $status Task status filter (default: 'pending').
	 * @return bool True if task exists, false otherwise.
	 */
	public static function has_scheduled_task( string $hook, string $group = '', string $status = 'pending' ): bool {
		// Check if Action Scheduler is available.
		if ( ! self::ensure_action_scheduler_ready() ) {
			return false;
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		try {
			$query_args = [
				'hook'   => $full_hook,
				'status' => $status,
			];

			// Add group filter if specified.
			if ( ! empty( $group ) ) {
				$query_args['group'] = $group;
			}

			$actions = as_get_scheduled_actions( $query_args, 'ids' );

			return ! empty( $actions );
		} catch ( Exception $e ) {
			// Log the error for debugging.
			error_log( self::$log_prefix . ': Error checking scheduled task: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if a recurring task with the specified hook exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (without prefix).
	 * @param string $group Action group (optional).
	 * @param string $status Task status filter (default: 'pending').
	 * @return bool True if recurring task exists, false otherwise.
	 */
	public static function has_scheduled_recurring_task( string $hook, string $group = '', string $status = 'pending' ): bool {
		// Check if Action Scheduler is available.
		if ( ! self::ensure_action_scheduler_ready() ) {
			return false;
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		try {
			$query_args = [
				'hook' => $full_hook,
			];

			// Add group filter if specified.
			if ( ! empty( $group ) ) {
				$query_args['group'] = $group;
			}

			// Check multiple statuses to be more thorough.
			$statuses_to_check = [ 'pending', 'in-progress' ];

			foreach ( $statuses_to_check as $check_status ) {
				$query_args['status'] = $check_status;

				// Get action IDs for this status.
				$action_ids = as_get_scheduled_actions( $query_args, 'ids' );

				// Check each action individually using ActionScheduler store.
				if ( class_exists( 'ActionScheduler' ) ) {
					$store = ActionScheduler::store();

					foreach ( $action_ids as $action_id ) {
						try {
							$action = $store->fetch_action( $action_id );
							if ( $action ) {
								$schedule = $action->get_schedule();
								if ( $schedule ) {
									$schedule_name = method_exists( $schedule, 'get_name' ) ? $schedule->get_name() : '';
									$interval      = method_exists( $schedule, 'get_interval' ) ? $schedule->get_interval() : 0;

									// Check if this is a recurring schedule.
									$is_recurring = false;

									// Check by schedule name.
									if ( in_array( $schedule_name, [ 'recurring', 'cron' ], true ) ) {
										$is_recurring = true;
									} elseif ( $interval > 0 ) {
										// Check by interval.
										$is_recurring = true;
									} elseif ( get_class( $schedule ) === 'ActionScheduler_IntervalSchedule' ) {
										// Check by class name for ActionScheduler_IntervalSchedule.
										$is_recurring = true;
									} elseif ( in_array( get_class( $schedule ), [ 'ActionScheduler_IntervalSchedule', 'ActionScheduler_CronSchedule', 'ActionScheduler_RecurringAction' ], true ) ) {
										// Check by class name for other recurring schedule types.
										$is_recurring = true;
									}

									if ( $is_recurring ) {
										return true;
									}
								}
							}
						} catch ( Exception $e ) {
							// Log the error for debugging.
							error_log( self::$log_prefix . ': Error fetching action ' . $action_id . ': ' . $e->getMessage() );
						}
					}
				}
			}

			return false;
		} catch ( Exception $e ) {
			// Log the error for debugging.
			error_log( self::$log_prefix . ': Error checking scheduled recurring task: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get tasks by hook name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (without prefix).
	 * @param string $group Action group (optional).
	 * @param string $status Task status filter (default: 'pending').
	 * @return array|WP_Error Array of tasks or WP_Error on failure.
	 */
	public static function get_tasks_by_hook( string $hook, string $group = '', string $status = 'pending' ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		return self::execute_with_error_handling(
			function () use ( $full_hook, $group, $status ) {
				$query_args = [
					'hook'   => $full_hook,
					'status' => $status,
				];

				// Add group filter if specified.
				if ( ! empty( $group ) ) {
					$query_args['group'] = $group;
				}

				$actions = as_get_scheduled_actions( $query_args, ARRAY_A );

				$result = [];
				foreach ( $actions as $action_id => $action ) {
					$task_data = [
						'id'        => $action_id,
						'hook'      => $action['hook'] ?? '',
						'args'      => $action['args'] ?? [],
						'group'     => $action['group'] ?? '',
						'status'    => $action['status'] ?? '',
						'schedule'  => $action['schedule'] ?? null,
						'recurring' => false,
						'next_run'  => null,
					];

					// Determine if this is a recurring task.
					if ( isset( $action['schedule'] ) && is_object( $action['schedule'] ) ) {
						$schedule_name = method_exists( $action['schedule'], 'get_name' ) ? $action['schedule']->get_name() : '';
						if ( in_array( $schedule_name, [ 'recurring', 'cron' ], true ) ) {
							$task_data['recurring'] = true;
						} elseif ( method_exists( $action['schedule'], 'get_interval' ) && $action['schedule']->get_interval() > 0 ) {
							$task_data['recurring'] = true;
						}

						// Get next run time if available.
						if ( method_exists( $action['schedule'], 'get_next' ) ) {
							$next_run = $action['schedule']->get_next();
							if ( $next_run instanceof \DateTime ) {
								$task_data['next_run'] = $next_run->getTimestamp();
							}
						}
					}

					$result[] = $task_data;
				}

				return $result;
			},
			'Error getting tasks by hook: ',
			'Failed to get tasks by hook.'
		);
	}

	/**
	 * Delete a specific task by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $action_id Action ID to delete.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_task( int $action_id ) {
		// Validate Action Scheduler availability.
		$validation_result = self::validate_action_scheduler_available();
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		return self::execute_with_error_handling(
			function () use ( $action_id ) {
				$deleted = as_unschedule_action( $action_id );

				if ( $deleted ) {
					return true;
				}

				return new WP_Error( 'delete_failed', 'Failed to delete task. Task may not exist or already be completed.' );
			},
			'Error deleting task: ',
			'Failed to delete task.'
		);
	}

	/**
	 * Get count of tasks with specific criteria.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Action hook name (without prefix).
	 * @param array  $args Arguments to match (optional).
	 * @param string $group Action group (optional).
	 * @param string $status Task status filter (default: 'pending').
	 * @return int Number of tasks matching the criteria.
	 */
	public static function get_task_count( string $hook, array $args = [], string $group = '', string $status = 'pending' ): int {
		// Check if Action Scheduler is available.
		if ( ! self::ensure_action_scheduler_ready() ) {
			return 0;
		}

		// Sanitize hook name.
		$hook = sanitize_key( $hook );

		// Add prefix to hook if not already present.
		$full_hook = self::ensure_hook_prefix( $hook );

		// Sanitize group name.
		$group = sanitize_key( $group );

		try {
			$query_args = [
				'hook'   => $full_hook,
				'args'   => [ 'task_args' => $args ],
				'status' => $status,
			];

			// Add group filter if specified.
			if ( ! empty( $group ) ) {
				$query_args['group'] = $group;
			}

			$actions = as_get_scheduled_actions( $query_args, 'ids' );

			return count( $actions );
		} catch ( Exception $e ) {
			// Log the error for debugging.
			error_log( self::$log_prefix . ': Error getting task count: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Ensure hook has prefix, adding it only if not already present.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Hook name (may or may not have prefix).
	 * @return string Hook with prefix.
	 */
	private static function ensure_hook_prefix( string $hook ): string {
		// If hook is empty or prefix is empty, return hook as-is.
		if ( empty( $hook ) || empty( self::$hook_prefix ) ) {
			return $hook;
		}

		$prefix_length = strlen( self::$hook_prefix );

		// Strip all occurrences of the current prefix from the beginning.
		// This handles cases where the hook has been prefixed multiple times.
		while ( strpos( $hook, self::$hook_prefix ) === 0 ) {
			$hook = substr( $hook, $prefix_length );
		}

		// Add the current prefix once.
		return self::$hook_prefix . $hook;
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
		$full_hook = self::ensure_hook_prefix( $hook );

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
	 * @param string   $unique         Uniqueness level.
	 * @return int|WP_Error Action ID on success, WP_Error on failure.
	 */
	private static function execute_with_uniqueness_check( string $full_hook, array $args, string $group, int $execution_time, ?int $priority, string $type, ?int $interval = null, ?int $max_runs = null, string $unique = self::UNIQUE_NONE ) {
		return self::execute_with_error_handling(
			function () use ( $full_hook, $args, $group, $execution_time, $priority, $type, $interval, $max_runs, $unique ) {
				// Build query based on uniqueness level.
				$query_args = [
					'hook'   => $full_hook,
					'status' => [ 'pending', 'in-progress' ],
				];

				// Add group filter for group and args uniqueness.
				if ( in_array( $unique, [ self::UNIQUE_GROUP, self::UNIQUE_ARGS ], true ) ) {
					$query_args['group'] = $group;
				}

				// Add args filter for args uniqueness only.
				if ( self::UNIQUE_ARGS === $unique ) {
					$query_args['args'] = [ 'task_args' => $args ];
				}

				// Check for existing actions based on uniqueness level.
				$existing_actions = as_get_scheduled_actions( $query_args, ARRAY_A );

				// Filter actions by type (recurring vs non-recurring) to avoid false duplicates.
				$matching_actions = [];
				foreach ( $existing_actions as $action_id => $action ) {
					$is_recurring = false;

					// Check if this action is recurring.
					if ( isset( $action['schedule'] ) && is_object( $action['schedule'] ) ) {
						$schedule_name = method_exists( $action['schedule'], 'get_name' ) ? $action['schedule']->get_name() : '';
						if ( in_array( $schedule_name, [ 'recurring', 'cron' ], true ) ) {
							$is_recurring = true;
						} elseif ( method_exists( $action['schedule'], 'get_interval' ) && $action['schedule']->get_interval() > 0 ) {
							$is_recurring = true;
						}
					}

					// Only consider actions of the same type (recurring vs non-recurring).
					if ( ( 'recurring' === $type && $is_recurring ) || ( 'single' === $type && ! $is_recurring ) ) {
						$matching_actions[] = $action_id;
					}
				}

				// Return existing action ID if found.
				if ( ! empty( $matching_actions ) ) {
					$existing_id     = $matching_actions[0];
					$action_type     = 'recurring' === $type ? 'recurring action' : 'action';
					$uniqueness_desc = self::get_uniqueness_description( $unique, $full_hook, $group, $args );
					error_log( sprintf( self::$log_prefix . ': Duplicate %s detected (%s). Returning existing action ID: %d', $action_type, $uniqueness_desc, $existing_id ) );
					return $existing_id;
				}

				// Schedule the action.
				if ( 'recurring' === $type ) {
					$action_id = as_schedule_recurring_action( $execution_time, $interval, $full_hook, [ 'task_args' => $args ], $group, false, $priority ?? 10 );
				} else {
					$action_id = as_schedule_single_action( $execution_time, $full_hook, [ 'task_args' => $args ], $group, false, $priority ?? 10 );
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
				$action_id = as_schedule_single_action( $execution_time, $full_hook, [ 'task_args' => $args ], $group, false, $priority ?? 10 );

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
			function () use ( $full_hook, $args, $group, $execution_time, $priority, $interval, $max_runs ) {
				$action_id = as_schedule_recurring_action( $execution_time, $interval, $full_hook, [ 'task_args' => $args ], $group, $max_runs, $priority ?? 10 );

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
	 * Get description of uniqueness check for logging.
	 *
	 * @since 1.0.0
	 *
	 * @param string $unique     Uniqueness level.
	 * @param string $full_hook  Full hook name.
	 * @param string $group      Action group.
	 * @param array  $args       Action arguments.
	 * @return string Description of uniqueness check.
	 */
	private static function get_uniqueness_description( string $unique, string $full_hook, string $group, array $args ): string {
		switch ( $unique ) {
			case self::UNIQUE_HOOK:
				return sprintf( 'hook: %s', $full_hook );
			case self::UNIQUE_GROUP:
				return sprintf( 'hook: %s, group: %s', $full_hook, $group );
			case self::UNIQUE_ARGS:
				return sprintf( 'hook: %s, group: %s, args: %s', $full_hook, $group, wp_json_encode( $args ) );
			default:
				return 'unknown uniqueness level';
		}
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
		} catch ( Exception $e ) {
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
