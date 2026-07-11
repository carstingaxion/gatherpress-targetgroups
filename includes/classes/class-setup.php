<?php
/**
 * Registers the Target Group taxonomy and ensures ancestor terms are always
 * stored alongside any assigned child term.
 *
 * @package GatherPress_Targetgroups
 */

declare(strict_types=1);

namespace GatherPress_Targetgroups;

use GatherPress\Core;

/**
 * Main plugin class using Singleton pattern.
 *
 * @since 0.1.0
 */
class Setup {

	use Core\Traits\Singleton;

	/**
	 * Taxonomy slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const TAXONOMY_NAME = 'gatherpress_targetgroup';

	/**
	 * Constructor — wires up all hooks.
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'set_object_terms', array( $this, 'ensure_ancestors_saved' ), 10, 4 );
	}

	/**
	 * Load the plugin's text domain for translations.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'gatherpress-targetgroups',
			false,
			'gatherpress-targetgroups/languages'
		);
	}

	/**
	 * Register the gatherpress_targetgroup taxonomy.
	 *
	 * Hierarchical and rewrite-disabled: archive URLs are meaningless for
	 * this organisational taxonomy, so the Post Terms block variation
	 * neutralises the term links on the frontend.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Target Groups', 'taxonomy general name', 'gatherpress-targetgroups' ),
			'singular_name'              => _x( 'Target Group', 'taxonomy singular name', 'gatherpress-targetgroups' ),
			'search_items'               => __( 'Search Target Groups', 'gatherpress-targetgroups' ),
			'popular_items'              => __( 'Popular Target Groups', 'gatherpress-targetgroups' ),
			'all_items'                  => __( 'All Target Groups', 'gatherpress-targetgroups' ),
			'parent_item'                => __( 'Parent Target Group', 'gatherpress-targetgroups' ),
			'parent_item_colon'          => __( 'Parent Target Group:', 'gatherpress-targetgroups' ),
			'name_field_description'     => __( 'The name is how it appears on your site.', 'gatherpress-targetgroups' ),
			'slug_field_description'     => __( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'gatherpress-targetgroups' ),
			'parent_field_description'   => __( 'Assign a parent term to create a hierarchy. The term &#8220;Kids&#8221;, for example, would be the parent of &#8220;0&#8211;5&#8221; and &#8220;5&#8211;9&#8221;.', 'gatherpress-targetgroups' ),
			'desc_field_description'     => __( 'The description is not prominent by default; however, some themes may show it.', 'gatherpress-targetgroups' ),
			'edit_item'                  => __( 'Edit Target Group', 'gatherpress-targetgroups' ),
			'view_item'                  => __( 'View Target Group', 'gatherpress-targetgroups' ),
			'update_item'                => __( 'Update Target Group', 'gatherpress-targetgroups' ),
			'add_new_item'               => __( 'Add New Target Group', 'gatherpress-targetgroups' ),
			'new_item_name'              => __( 'New Target Group Name', 'gatherpress-targetgroups' ),
			'template_name'              => __( 'Target Group Archives', 'gatherpress-targetgroups' ),
			'separate_items_with_commas' => __( 'Separate target groups with commas', 'gatherpress-targetgroups' ),
			'add_or_remove_items'        => __( 'Add or remove target groups', 'gatherpress-targetgroups' ),
			'choose_from_most_used'      => __( 'Choose from the most used target groups', 'gatherpress-targetgroups' ),
			'not_found'                  => __( 'No target groups found.', 'gatherpress-targetgroups' ),
			'no_terms'                   => __( 'No target groups', 'gatherpress-targetgroups' ),
			'filter_by_item'             => __( 'Filter by target group', 'gatherpress-targetgroups' ),
			'items_list_navigation'      => __( 'Target groups list navigation', 'gatherpress-targetgroups' ),
			'items_list'                 => __( 'Target groups list', 'gatherpress-targetgroups' ),
			'most_used'                  => __( 'Most Used', 'gatherpress-targetgroups' ),
			'back_to_items'              => __( '&larr; Go to Target Groups', 'gatherpress-targetgroups' ),
			'item_link'                  => _x( 'Target Group Link', 'navigation link block title', 'gatherpress-targetgroups' ),
			'item_link_description'      => _x( 'A link to a target group.', 'navigation link block description', 'gatherpress-targetgroups' ),
			'menu_name'                  => __( 'Target Groups', 'gatherpress-targetgroups' ),
		);

		register_taxonomy(
			self::TAXONOMY_NAME,
			array( 'gatherpress_event', 'gatherpress_play' ),
			array(
				'hierarchical'       => true,
				'labels'             => $labels,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'publicly_queryable' => true,
				'query_var'          => true,
				'rewrite'            => false,
				// Automatically applied to every wp_get_object_terms() call
				// for this taxonomy, including get_the_terms(). Ensures parents
				// are always returned before their children without any
				// per-call sorting.
				'args'               => array(
					'orderby' => 'parent',
					'order'   => 'ASC',
				),
			)
		);
	}

	/**
	 * Ensures all ancestor terms are stored against the post whenever
	 * gatherpress_targetgroup terms are assigned.
	 *
	 * WordPress only saves the exact term IDs passed to wp_set_object_terms().
	 * When an editor assigns only a child term (e.g. "0–5"), its parent term
	 * (e.g. "Kids") is not automatically stored. This hook walks up the full
	 * ancestor chain for every assigned term and appends any missing ancestors,
	 * so that depth-level filtering in the block (top / leaves) always has the
	 * complete hierarchy available.
	 *
	 * Uses $append = true so existing term relationships are not disturbed.
	 * wp_set_object_terms() is idempotent for already-assigned terms, so
	 * calling it with ancestors that are already stored is harmless. The
	 * $missing diff prevents a redundant DB write when nothing is absent.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $object_id Object ID (post ID).
	 * @param array  $terms     Term IDs or slugs that were just set.
	 * @param array  $tt_ids    Term taxonomy IDs that were just set.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return void
	 */
	public function ensure_ancestors_saved( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		if ( self::TAXONOMY_NAME !== $taxonomy ) {
			return;
		}

		if ( empty( $tt_ids ) ) {
			return;
		}

		// Resolve term_ids from the term taxonomy IDs just committed.
		$assigned_term_ids = array_filter(
			array_map(
				function ( int $tt_id ): ?int {
					$term = get_term_by( 'term_taxonomy_id', $tt_id, self::TAXONOMY_NAME );
					return $term ? (int) $term->term_id : null;
				},
				$tt_ids
			)
		);

		// Walk up the ancestor chain for every assigned term.
		$ancestor_ids = array();
		foreach ( $assigned_term_ids as $term_id ) {
			$ancestor_ids = array_merge(
				$ancestor_ids,
				get_ancestors( $term_id, self::TAXONOMY_NAME, 'taxonomy' )
			);
		}

		if ( empty( $ancestor_ids ) ) {
			return;
		}

		// Only append ancestors that are not already stored for this post.
		$already_assigned = array_map(
			'intval',
			(array) wp_get_object_terms( $object_id, self::TAXONOMY_NAME, array( 'fields' => 'ids' ) )
		);
		$missing = array_diff( array_unique( $ancestor_ids ), $already_assigned );

		if ( empty( $missing ) ) {
			return;
		}

		wp_set_object_terms( $object_id, array_values( $missing ), self::TAXONOMY_NAME, true );
	}
}
