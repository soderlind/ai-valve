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
					label={ __( 'Plugin', 'ai-valve' ) }
					value={ local.plugin_slug }
					options={ [
						{ label: __( 'All', 'ai-valve' ), value: '' },
						...( filterOptions.plugins || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'plugin_slug', v ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Provider', 'ai-valve' ) }
					value={ local.provider_id }
					options={ [
						{ label: __( 'All', 'ai-valve' ), value: '' },
						...( filterOptions.providers || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'provider_id', v ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Model', 'ai-valve' ) }
					value={ local.model_id }
					options={ [
						{ label: __( 'All', 'ai-valve' ), value: '' },
						...( filterOptions.models || [] ).map( ( v ) => ( {
							label: v,
							value: v,
						} ) ),
					] }
					onChange={ ( v ) => update( 'model_id', v ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Context', 'ai-valve' ) }
					value={ local.context }
					options={ [
						{ label: __( 'All', 'ai-valve' ), value: '' },
						{ label: 'admin', value: 'admin' },
						{ label: 'frontend', value: 'frontend' },
						{ label: 'cron', value: 'cron' },
						{ label: 'rest', value: 'rest' },
						{ label: 'ajax', value: 'ajax' },
						{ label: 'cli', value: 'cli' },
					] }
					onChange={ ( v ) => update( 'context', v ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Status', 'ai-valve' ) }
					value={ local.status }
					options={ [
						{ label: __( 'All', 'ai-valve' ), value: '' },
						{
							label: __( 'Allowed', 'ai-valve' ),
							value: 'allowed',
						},
						{
							label: __( 'Denied', 'ai-valve' ),
							value: 'denied',
						},
					] }
					onChange={ ( v ) => update( 'status', v ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'From', 'ai-valve' ) }
					type="date"
					value={ local.date_from }
					onChange={ ( v ) => update( 'date_from', v ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'To', 'ai-valve' ) }
					type="date"
					value={ local.date_to }
					onChange={ ( v ) => update( 'date_to', v ) }
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" type="submit">
					{ __( 'Filter', 'ai-valve' ) }
				</Button>
			</div>
		</form>
	);
}
