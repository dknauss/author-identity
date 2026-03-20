/**
 * Block editor sidebar panel for AI Training Consent.
 *
 * Adds an "AI Training Consent" panel to the post sidebar
 * using PluginDocumentSettingPanel, allowing editors to set
 * a per-post AI consent override.
 *
 * @package Byline_Feed
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { SelectControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const CONSENT_OPTIONS = [
	{
		label: __( 'Inherit from authors', 'byline-feed' ),
		value: '',
	},
	{
		label: __( 'Allow AI training', 'byline-feed' ),
		value: 'allow',
	},
	{
		label: __( 'Deny AI training', 'byline-feed' ),
		value: 'deny',
	},
];

function AiConsentPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const consent = meta?._byline_ai_consent || '';

	return (
		<PluginDocumentSettingPanel
			name="byline-feed-ai-consent"
			title={ __( 'AI Training Consent', 'byline-feed' ) }
		>
			<SelectControl
				label={ __( 'Consent', 'byline-feed' ) }
				value={ consent }
				options={ CONSENT_OPTIONS }
				onChange={ ( value: string ) =>
					setMeta( { ...meta, _byline_ai_consent: value } )
				}
				help={ __(
					'Controls machine-readable AI training signals for this post. When unset, the most restrictive linked-author preference wins.',
					'byline-feed'
				) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'byline-feed-ai-consent', {
	render: AiConsentPanel,
	icon: 'shield',
} );
