<?php
/**
 * Activation and database schema.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the plugin tables.
 */
class Install {

	const DB_VERSION        = '2';
	const DB_VERSION_OPTION = 'eobots_db_version';
	const REDIRECT_KEY      = 'eobots_activation_redirect';

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		set_transient( self::REDIRECT_KEY, get_current_user_id(), MINUTE_IN_SECONDS );
	}

	/**
	 * Creates the tables again when the schema version changed (plugin updated without reactivation).
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Returns the full table names.
	 *
	 * @return array{conversations: string, messages: string, journal: string}
	 */
	public static function tables() {
		global $wpdb;

		return array(
			'conversations' => $wpdb->prefix . 'eobots_conversations',
			'messages'      => $wpdb->prefix . 'eobots_messages',
			'journal'       => $wpdb->prefix . 'eobots_journal',
		);
	}

	/**
	 * Creates or updates the tables with dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta(
			"CREATE TABLE {$t['conversations']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				title varchar(190) NOT NULL DEFAULT '',
				history longtext NOT NULL,
				pending longtext NULL,
				notes longtext NULL,
				steps smallint(5) unsigned NOT NULL DEFAULT 0,
				tokens_in int(10) unsigned NOT NULL DEFAULT 0,
				tokens_out int(10) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_updated (user_id,updated_at)
			) $charset;
			CREATE TABLE {$t['messages']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				conversation_id bigint(20) unsigned NOT NULL,
				role varchar(20) NOT NULL,
				content longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY conversation_id (conversation_id)
			) $charset;
			CREATE TABLE {$t['journal']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				ability varchar(100) NOT NULL,
				object_type varchar(20) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				summary text NOT NULL,
				undo_data longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'done',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY conversation_id (conversation_id)
			) $charset;"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}
}
