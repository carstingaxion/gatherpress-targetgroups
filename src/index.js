import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Returns true when the block is the target-group variation.
 *
 * @param {Object} props Block props passed to the edit component.
 * @return {boolean}
 */
function isTargetGroupVariation( props ) {
	return props.name === 'core/post-terms' && props.attributes.term === 'gatherpress_targetgroup';
}

/**
 * Adds the depthDisplay inspector control to the target-group variation.
 *
 * Three options:
 *  - all    Show every assigned term (default).
 *  - top    Show only root-level terms (e.g. Kids, Teens, Adults).
 *  - leaves Show only leaf terms — those not acting as a parent of another
 *           assigned term (e.g. 0–5, 5–9, 9–12).
 */
const withDepthControl = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( ! isTargetGroupVariation( props ) ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const { depthDisplay = 'all' } = attributes;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody title={ __( 'Depth', 'gatherpress-targetgroups' ) }>
						<RadioControl
							label={ __( 'Show terms', 'gatherpress-targetgroups' ) }
							selected={ depthDisplay }
							options={ [
								{
									label: __( 'All levels', 'gatherpress-targetgroups' ),
									value: 'all',
								},
								{
									label: __( 'Top level only', 'gatherpress-targetgroups' ),
									value: 'top',
								},
								{
									label: __( 'Leaves only', 'gatherpress-targetgroups' ),
									value: 'leaves',
								},
							] }
							onChange={ ( value ) => setAttributes( { depthDisplay: value } ) }
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withDepthControl' );

addFilter(
	'editor.BlockEdit',
	'gatherpress/target-group-depth-control',
	withDepthControl
);
