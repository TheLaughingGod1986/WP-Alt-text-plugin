<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Bus
 *
 * Implements publish-subscribe pattern for decoupled event-driven architecture.
 * Allows components to communicate without direct dependencies.
 *
 * @package BeepBeep\AltText\Core
 * @since   5.0.0
 */
class Event_Bus {
	/**
	 * Event listeners.
	 *
	 * @var array<string, array<callable>>
	 */
	private array $listeners = array();

	/**
	 * Subscribe to an event.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $event    Event name.
	 * @param callable $callback Event handler callback.
	 * @param int      $priority Priority (lower number = higher priority).
	 * @return callable Unsubscribe function.
	 */
	public function on(string $event, callable $callback, int $priority = 10): callable {
		if ( ! isset( $this->listeners[ $event ] ) ) {
			$this->listeners[ $event ] = array();
		}

		// Store callback with priority.
		$this->listeners[ $event ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);

		// Sort by priority.
		usort(
			$this->listeners[ $event ],
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		// Return unsubscribe function.
		return function () use ( $event, $callback ) {
			$this->off( $event, $callback );
		};
	}

	/**
	 * Subscribe to event once.
	 *
	 * Automatically unsubscribes after first execution.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $event    Event name.
	 * @param callable $callback Event handler callback.
	 * @param int      $priority Priority.
	 * @return callable Unsubscribe function.
	 */
	public function once(string $event, callable $callback, int $priority = 10): callable {
		$unsubscribe = null;

		$wrapper = function ( $data ) use ( $callback, &$unsubscribe ) {
			$callback( $data );
			if ( null !== $unsubscribe ) {
				$unsubscribe();
			}
		};

		$unsubscribe = $this->on( $event, $wrapper, $priority );

		return $unsubscribe;
	}

	/**
	 * Unsubscribe from an event.
	 *
	 * @since 5.0.0
	 *
	 * @param string        $event    Event name.
	 * @param callable|null $callback Specific callback to remove, or null to remove all.
	 * @return void
	 */
	public function off(string $event, ?callable $callback = null): void {
		if ( ! isset( $this->listeners[ $event ] ) ) {
			return;
		}

		if ( null === $callback ) {
			// Remove all listeners for this event.
			unset( $this->listeners[ $event ] );
			return;
		}

		// Remove specific callback.
		$this->listeners[ $event ] = array_filter(
			$this->listeners[ $event ],
			function ( $listener ) use ( $callback ) {
				return $listener['callback'] !== $callback;
			}
		);

		// Re-index array.
		$this->listeners[ $event ] = array_values( $this->listeners[ $event ] );
	}

	/**
	 * Emit an event.
	 *
	 * Synchronously calls all registered listeners.
	 *
	 * @since 5.0.0
	 *
	 * @param string $event Event name.
	 * @param mixed  $data  Event data.
	 * @return void
	 */
	public function emit(string $event, $data = null): void {
		if ( ! isset( $this->listeners[ $event ] ) ) {
			return;
		}

		foreach ( $this->listeners[ $event ] as $listener ) {
			try {
				call_user_func( $listener['callback'], $data );
			} catch ( \Throwable $e ) {
				// Log error but don't stop other listeners.
				if ( class_exists( '\BbAI_Debug_Log' ) ) {
					\BbAI_Debug_Log::log(
						'error',
						"Error in event listener for '{$event}': {$e->getMessage()}",
						array(
							'event'      => $event,
							'error'      => $e->getMessage(),
							'trace'      => $e->getTraceAsString(),
						)
					);
				}
			}
		}
	}

	/**
	 * Emit an event asynchronously.
	 *
	 * Schedules event emission via WordPress action scheduler or wp_cron.
	 *
	 * @since 5.0.0
	 *
	 * @param string $event Event name.
	 * @param mixed  $data  Event data.
	 * @return void
	 */
	public function emit_async(string $event, $data = null): void {
		// Use WordPress action scheduler if available.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'bbai_async_event',
				array(
					'event' => $event,
					'data'  => $data,
				)
			);
			return;
		}

		// Fallback to wp_cron.
		wp_schedule_single_event(
			time(),
			'bbai_async_event',
			array(
				'event' => $event,
				'data'  => $data,
			)
		);
	}

	/**
	 * Get all registered events.
	 *
	 * @since 5.0.0
	 *
	 * @return array<string> Event names.
	 */
	public function get_events(): array {
		return array_keys( $this->listeners );
	}

	/**
	 * Get listener count for an event.
	 *
	 * @since 5.0.0
	 *
	 * @param string $event Event name.
	 * @return int Listener count.
	 */
	public function listener_count(string $event): int {
		return isset( $this->listeners[ $event ] ) ? count( $this->listeners[ $event ] ) : 0;
	}

	/**
	 * Clear all event listeners.
	 *
	 * Useful for testing.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->listeners = array();
	}
}
