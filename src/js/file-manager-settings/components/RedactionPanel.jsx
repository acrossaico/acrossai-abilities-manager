/**
 * RedactionPanel — toggle built-in secret patterns and add custom literals.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const RedactionPanel = ( { data, onSave } ) => {
	const [ patterns, setPatterns ] = useState( () => ( data.config?.patterns || {} ) );
	const [ literals, setLiterals ] = useState(
		() => ( data.config?.custom_literals || [] ).join( '\n' )
	);
	const [ saving, setSaving ]     = useState( false );
	const [ status, setStatus ]     = useState( '' );

	useEffect( () => {
		setPatterns( data.config?.patterns || {} );
		setLiterals( ( data.config?.custom_literals || [] ).join( '\n' ) );
	}, [ data ] );

	const togglePattern = ( id, next ) => {
		setPatterns( ( prev ) => ( { ...prev, [ id ]: next } ) );
	};

	const doSave = () => {
		setSaving( true );
		setStatus( '' );
		const custom_literals = literals
			.split( /\r?\n/ )
			.map( ( s ) => s.trim() )
			.filter( Boolean );
		onSave( { patterns, custom_literals } )
			.then( () => {
				setSaving( false );
				setStatus( __( 'Saved.', 'acrossai-abilities-manager' ) );
			} )
			.catch( ( err ) => {
				setSaving( false );
				setStatus( err.message || __( 'Save failed.', 'acrossai-abilities-manager' ) );
			} );
	};

	const available = data.available || {};

	return (
		<section className="acrossai-fm-panel">
			<h2>{ __( 'Secret redaction', 'acrossai-abilities-manager' ) }</h2>
			<p className="description">
				{ __(
					'Every file the AI reads is scrubbed for known secret patterns before the response leaves the site. Toggle any pattern class off if it produces false positives; add custom literal strings that should always be redacted.',
					'acrossai-abilities-manager'
				) }
			</p>

			<h3>{ __( 'Built-in patterns', 'acrossai-abilities-manager' ) }</h3>
			<ul className="acrossai-fm-patterns">
				{ Object.entries( available ).map( ( [ id, info ] ) => (
					<li key={ id }>
						<label>
							<input
								type="checkbox"
								checked={ !! patterns[ id ] }
								onChange={ ( ev ) => togglePattern( id, ev.target.checked ) }
							/>{ ' ' }
							<strong>{ info.label }</strong>
						</label>
						<p className="description">{ info.description }</p>
					</li>
				) ) }
			</ul>

			<h3>{ __( 'Custom literal strings', 'acrossai-abilities-manager' ) }</h3>
			<p className="description">
				{ __(
					'One string per line. Any exact match in read content is replaced with ***REDACTED***.',
					'acrossai-abilities-manager'
				) }
			</p>
			<textarea
				className="widefat code"
				rows={ 5 }
				value={ literals }
				onChange={ ( ev ) => setLiterals( ev.target.value ) }
				placeholder="my-secret-abc"
			/>

			<p className="submit">
				<button type="button" className="button button-primary" disabled={ saving } onClick={ doSave }>
					{ saving ? __( 'Saving…', 'acrossai-abilities-manager' ) : __( 'Save redaction settings', 'acrossai-abilities-manager' ) }
				</button>
				{ status && <span className="acrossai-fm-status"> { status }</span> }
			</p>
		</section>
	);
};

export default RedactionPanel;
