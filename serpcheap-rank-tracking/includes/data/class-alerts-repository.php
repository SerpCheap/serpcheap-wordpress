<?php
/**
 * CRUD for the alerts table.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Data;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AlertsRepository {

	private function table(): string {
		return Plugin::table( 'alerts' );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
			$this->table(),
			array(
				'tracker_id' => (int) $data['tracker_id'],
				'type'       => $data['type'],
				'severity'   => isset( $data['severity'] ) ? $data['severity'] : 'warning',
				'old_rank'   => isset( $data['old_rank'] ) ? $data['old_rank'] : null,
				'new_rank'   => isset( $data['new_rank'] ) ? $data['new_rank'] : null,
				'message'    => $data['message'],
				'is_read'    => 0,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/** @return array<int,array<string,mixed>> */
	public function recent( int $limit = 30 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT a.*, t.keyword, t.gl FROM {$this->table()} a
				 LEFT JOIN " . Plugin::table( 'trackers' ) . " t ON t.id = a.tracker_id
				 ORDER BY a.created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	public function unread_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE is_read = 0' ); // phpcs:ignore WordPress.DB
	}

	public function mark_all_read(): void {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . $this->table() . ' SET is_read = 1 WHERE is_read = 0' ); // phpcs:ignore WordPress.DB
	}

	public function mark_read( int $id ): void {
		global $wpdb;
		$wpdb->update( $this->table(), array( 'is_read' => 1 ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	}

	public function delete_for_tracker( int $tracker_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'tracker_id' => $tracker_id ) ); // phpcs:ignore WordPress.DB
	}
}
