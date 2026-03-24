import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function LogFilters( { filters, onChange } ) {
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
				<TextControl
					label={ __( 'Plugin', 'ai-valve' ) }
					value={ local.plugin_slug }
					onChange={ ( v ) => update( 'plugin_slug', v ) }
					placeholder="e.g. vmfa-ai-organizer"
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Provider', 'ai-valve' ) }
					value={ local.provider_id }
					onChange={ ( v ) => update( 'provider_id', v ) }
					placeholder="e.g. openai"
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Model', 'ai-valve' ) }
					value={ local.model_id }
					onChange={ ( v ) => update( 'model_id', v ) }
					placeholder="e.g. gpt-4o"
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
