<?php
/**
 * Registers the depthDisplay block attribute on core/post-terms and filters
 * its rendered output for the gatherpress_targetgroup taxonomy.
 *
 * @package GatherPress_Targetgroups
 */

declare(strict_types=1);

namespace GatherPress_Targetgroups;

use GatherPress\Core;

/**
 * Block integration class using Singleton pattern.
 *
 * @since 0.1.0
 */
class Block {

	use Core\Traits\Singleton;

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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'init', array( $this, 'register_block_attributes' ), 100 );
		add_filter( 'render_block', array( $this, 'render_filter' ), 10, 2 );
	}

	/**
	 * Enqueue the editor script that registers the additional panel for the core/post-terms block variation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = GATHERPRESS_TARGETGROUPS_CORE_PATH . '/build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => GATHERPRESS_TARGETGROUPS_VERSION,
			);

		wp_enqueue_script(
			'gatherpress-targetgroups-editor',
			plugins_url( 'build/index.js', GATHERPRESS_TARGETGROUPS_CORE_PATH . '/plugin.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Register the custom depthDisplay attribute on the core/post-terms block.
	 *
	 * Runs at priority 100 so core has already registered the block type.
	 *
	 * Accepted values: 'all' | 'top' | 'leaves'
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_block_attributes(): void {
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( 'core/post-terms' );

		if ( $block_type ) {
			$block_type->attributes['depthDisplay'] = array(
				'type'    => 'string',
				'default' => '',
			);
		}
	}

	/**
	 * Filter the core/post-terms block output for the gatherpress_targetgroup taxonomy.
	 *
	 * Applies depth filtering to the assigned terms before rendering:
	 *
	 *  - 'all'    Show every assigned term regardless of depth (default).
	 *  - 'top'    Show only root-level terms (parent === 0).
	 *  - 'leaves' Show only terms that are not the parent of another assigned
	 *             term. Works correctly with mixed-depth assignments and
	 *             taxonomies deeper than two levels because it reasons about
	 *             the assigned subset only — no taxonomy-wide queries needed.
	 *
	 * Link neutralisation is handled upstream by the post-terms-block-unlinked
	 * plugin, which detects rewrite => false on this taxonomy automatically.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data including name and attributes.
	 * @return string Filtered block HTML.
	 */
	public function render_filter( string $block_content, array $block ): string {
		if ( 'core/post-terms' !== $block['blockName'] ) {
			return $block_content;
		}

		$attrs = $block['attrs'] ?? array();

		if ( Setup::TAXONOMY_NAME !== ( $attrs['term'] ?? '' ) ) {
			return $block_content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $block_content;
		}

		$terms = get_the_terms( $post_id, Setup::TAXONOMY_NAME );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $block_content;
		}

		// ── Depth filtering ───────────────────────────────────────────────
		$depth_display = $attrs['depthDisplay'] ?? 'all';

		if ( 'top' === $depth_display ) {
			$terms = array_values(
				array_filter( $terms, fn( $t ) => 0 === (int) $t->parent )
			);
		} elseif ( 'leaves' === $depth_display ) {
			$parent_ids = array_flip(
				array_filter( array_map( fn( $t ) => (int) $t->parent, $terms ) )
			);
			$terms = array_values(
				array_filter( $terms, fn( $t ) => ! isset( $parent_ids[ $t->term_id ] ) )
			);
		}

		if ( empty( $terms ) ) {
			return '';
		}

		if ( 'all' === $depth_display ) {
			return $block_content;
		}

		return $this->rebuild_output( $block_content, $terms, $attrs );
	}

	/**
	 * Rebuild the block output containing only the filtered subset of terms.
	 *
	 * Extracts the wrapper element (with all editor-applied attributes) from
	 * the existing block_content using the HTML Tag Processor, then re-renders
	 * only the filtered terms inside it. This preserves all block supports
	 * (color, typography, border, spacing) without duplicating
	 * get_block_wrapper_attributes().
	 *
	 * @since 0.1.0
	 *
	 * @param string     $block_content Full rendered block HTML (links already neutralised).
	 * @param \WP_Term[] $terms         Filtered term objects to render.
	 * @param array      $attrs         Block attributes.
	 * @return string Rebuilt block HTML.
	 */
	protected function rebuild_output( string $block_content, array $terms, array $attrs ): string {
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( array( 'tag_name' => 'DIV' ) ) ) {
			return $block_content;
		}

		$full_html   = $processor->get_updated_html();
		$tag_end_pos = strpos( $full_html, '>' );
		$opening_tag = substr( $full_html, 0, $tag_end_pos + 1 );

		$separator = $attrs['separator'] ?? ', ';
		$sep_html  = '<span class="wp-block-post-terms__separator">' . esc_html( $separator ) . '</span>';

		$term_parts = array_map(
			fn( $term ) => '<a href="#">' . esc_html( $term->name ) . '</a>',
			$terms
		);

		$prefix = ! empty( $attrs['prefix'] )
			? '<span class="wp-block-post-terms__prefix">' . esc_html( $attrs['prefix'] ) . '</span>'
			: '';

		$suffix = ! empty( $attrs['suffix'] )
			? '<span class="wp-block-post-terms__suffix">' . esc_html( $attrs['suffix'] ) . '</span>'
			: '';

		return $opening_tag
			. $prefix
			. implode( $sep_html, $term_parts )
			. $suffix
			. '</div>';
	}
}
