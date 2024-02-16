/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { recordEvent } from '@woocommerce/tracks';
import { useEntityProp } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { PublishButton } from '../header/publish-button';
import { PrepublishPanelProps } from './types';
import { store as productEditorUiStore } from '../../store/product-editor-ui';
import { TRACKS_SOURCE } from '../../constants';
import { VisibilitySection } from './visibility-section';
import { ScheduleSection } from './schedule-section';
import { ShowPrepublishChecksSection } from './show-prepublish-checks-section';

export function PrepublishPanel( {
	productId,
	productType = 'product',
	title = __( 'Are you ready to publish this product?', 'woocommerce' ),
	description = __(
		'Double-check your settings before sharing this product with customers.',
		'woocommerce'
	),
}: PrepublishPanelProps ) {
	const [ editedDate, , date ] = useEntityProp< string >(
		'postType',
		productType,
		'date_created'
	);

	const lastPersistedProduct = useSelect(
		( select ) => {
			const { getEntityRecord } = select( 'core' );
			return getEntityRecord( 'postType', productType, productId );
		},
		[ productType, productId ]
	);

	const { closePrepublishPanel } = useDispatch( productEditorUiStore );

	if ( editedDate !== date ) {
		title = __( 'Are you ready to schedule this product?', 'woocommerce' );
		description = __(
			'Your product will be published at the specified date and time.',
			'woocommerce'
		);
	}

	return (
		<div className="woocommerce-product-publish-panel">
			<div className="woocommerce-product-publish-panel__header">
				<PublishButton
					productType={ productType }
					productStatus={ lastPersistedProduct?.status }
				/>
				<Button
					variant={ 'secondary' }
					onClick={ () => {
						recordEvent( 'product_prepublish_panel', {
							source: TRACKS_SOURCE,
							action: 'cancel',
						} );
						closePrepublishPanel();
					} }
				>
					{ __( 'Cancel', 'woocommerce' ) }
				</Button>
			</div>
			<div className="woocommerce-product-publish-panel__title">
				<h4>{ title }</h4>
				<span>{ description }</span>
			</div>
			<div className="woocommerce-product-publish-panel__content">
				<VisibilitySection productType={ productType } />
				<ScheduleSection postType={ productType } />
			</div>
			<div className="woocommerce-product-publish-panel__footer">
				<ShowPrepublishChecksSection />
			</div>
		</div>
	);
}
