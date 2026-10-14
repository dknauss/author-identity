/**
 * Block editor sidebar panel for Attribution (author mode).
 *
 * Stores the editor's explicit choice in `_byline_author_mode`. "Automatic"
 * stores nothing, leaving the decision to the `byline_feed_author_mode`
 * filter (by default, the authors).
 *
 * @package Byline_Feed
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { RadioControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const AUTHOR_MODE_OPTIONS = [
	{ label: __( 'Automatic', 'byline-feed' ), value: '' },
	{ label: __( 'The authors', 'byline-feed' ), value: 'person' },
	{
		label: __( 'The site (no personal byline)', 'byline-feed' ),
		value: 'organization',
	},
];

function AuthorModePanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const mode = meta?._byline_author_mode || '';

	return (
		<PluginDocumentSettingPanel
			name="byline-feed-author-mode"
			title={ __( 'Attribution', 'byline-feed' ) }
		>
			<RadioControl
				label={ __( 'Attribute this post to', 'byline-feed' ) }
				selected={ mode }
				options={ AUTHOR_MODE_OPTIONS }
				onChange={ ( value: string ) =>
					setMeta( { ...meta, _byline_author_mode: value } )
				}
				help={ __(
					'Who structured data and link previews credit with this post. Automatic lets the site or another plugin decide; it credits the authors unless told otherwise.',
					'byline-feed'
				) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'byline-feed-author-mode', {
	render: AuthorModePanel,
	icon: 'admin-users',
} );
