<?php
/**
 * Plugin Name:       GatherPress Targetgroups
 * Plugin URI:        https://github.com/carstingaxion/gatherpress-targetgroups
 * Description:       Adds a hierarchical Target Group taxonomy to GatherPress events with depth-level filtering in the Post Terms block.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires plugins:  gatherpress
 * Author:            carstenbach
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-targetgroups
 * Domain Path:       /languages
 *
 * @package GatherPress_Targetgroups
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_TARGETGROUPS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_TARGETGROUPS_CORE_PATH', __DIR__ );

/**
 * Adds the GatherPress_Targetgroups namespace to the autoloader.
 *
 * @param array<string, string> $namespaces An associative array of namespaces and their paths.
 * @return array<string, string> Modified array of namespaces and their paths.
 */
function gatherpress_targetgroups_autoloader( array $namespaces ): array {
	$namespaces['GatherPress_Targetgroups'] = GATHERPRESS_TARGETGROUPS_CORE_PATH;

	return $namespaces;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_targetgroups_autoloader' );

/**
 * Initialize the plugin once GatherPress core is loaded.
 *
 * @since 0.1.0
 * @return void
 */
function gatherpress_targetgroups_setup(): void {
	if ( defined( 'GATHERPRESS_VERSION' ) ) {
		\GatherPress_Targetgroups\Setup::get_instance();
		\GatherPress_Targetgroups\Block::get_instance();
	}
}
add_action( 'plugins_loaded', 'gatherpress_targetgroups_setup' );
