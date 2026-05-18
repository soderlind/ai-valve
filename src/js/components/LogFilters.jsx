import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function LogFilters( { filters, onChange, filterOptions = {} } ) {
	const [ local, setLocal ] = useState( { ...filters } );

	function update( key, value ) {
		setLocal( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	function handleSubmit( e ) {
		e.preventDefault();
		onChange( local );
	}

	return (
		<form
			onSubmit={ handleSubmit }
			style={ { marginBottom: '1em' } }
		>
			<div
				style={ {
					display: 'flex',
					flexWrap: 'wrap',
					gap: '8px 16px',
					alignItems: 'flex-end',
				} }
			>
				<SelectControl
					label={ __( 'Plugin', 'soderlind-aivalve' ) }
					value={ local.plugin_slug }
					options={ [
						{ label: __( 'All', 'soderlind-aivalve' ), value: '' },
						...( filterOptions.plugins || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'plugin_slug', v ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Provider', 'soderlind-aivalve' ) }
					value={ local.provider_id }
					options={ [
						{ label: __( 'All', 'soderlind-aivalve' ), value: '' },
						...( filterOptions.providers || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'provider_id', v ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Model', 'soderlind-aivalve' ) }
					value={ local.model_id }
					options={ [
						{ label: __( 'All', 'soderlind-aivalve' ), value: '' },
						...( filterOptions.models || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'model_id', v ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Context', 'soderlind-aivalve' ) }
					value={ local.context }
					options={ [
						{ label: __( 'All', 'soderlind-aivalve' ), value: '' },
						{ label: 'admin', value: 'admin' },
						{ label: 'frontend', value: 'frontend' },
						{ label: 'cron', value: 'cron' },
						{ label: 'rest', value: 'rest' },
						{ label: 'ajax', value: 'ajax' },
						{ label: 'cli', value: 'cli' },
					] }
					onChange={ ( v ) => update( 'context', v ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Status', 'soderlind-aivalve' ) }
					value={ local.status }
					options={ [
						{ label: __( 'All', 'soderlind-aivalve' ), value: '' },
						{
							label: __( 'Allowed', 'soderlind-aivalve' ),
							value: 'allowed',
						},
						{
							label: __( 'Denied', 'soderlind-aivalve' ),
							value: 'denied',
						},
					] }
					onChange={ ( v ) => update( 'status', v ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __( 'From', 'soderlind-aivalve' ) }
					type="date"
					value={ local.date_from }
					onChange={ ( v ) => update( 'date_from', v ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'To', 'soderlind-aivalve' ) }
					type="date"
					value={ local.date_to }
					onChange={ ( v ) => update( 'date_to', v ) }
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" type="submit">
					{ __( 'Filter', 'soderlind-aivalve' ) }
				</Button>
			</div>
		</form>
	);
}
