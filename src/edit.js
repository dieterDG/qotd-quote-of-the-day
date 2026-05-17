import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	// useBlockProps() liest attributes.className (WordPress-nativ) automatisch
	// und fügt es zu den Block-Wrapper-Klassen hinzu.
	const blockProps = useBlockProps( {
		className: 'qotd qotd--preview',
	} );

	return (
		<div { ...blockProps }>
			<div
				className="qotd__text"
				style={ { whiteSpace: 'pre-line' } }
			>
				{ __( 'Quote of the Day will be displayed here', 'qotd-quote-of-the-day' ) }
			</div>
			<div className="qotd__meta">
				<span className="qotd__author">
					{ __( '— Author', 'qotd-quote-of-the-day' ) }
				</span>
				<span className="qotd__source"></span>
			</div>
		</div>
	);
}
