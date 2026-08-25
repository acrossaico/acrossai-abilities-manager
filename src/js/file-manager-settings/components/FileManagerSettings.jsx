/**
 * FileManagerSettings — top-level orchestrator.
 *
 * Loads the current settings + enumeration data from three REST endpoints
 * and renders three panels (write allowlist, read allowlist, redaction).
 * Each panel manages its own local state and posts back to its endpoint
 * on Save.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import WriteAllowlistPanel from './WriteAllowlistPanel.jsx';
import ReadAllowlistPanel from './ReadAllowlistPanel.jsx';
import RedactionPanel from './RedactionPanel.jsx';

const FileManagerSettings = ( { restNamespace, restBase } ) => {
	const base = `/${ restNamespace }/${ restBase }`;

	const [ writeData, setWriteData ] = useState( null );
	const [ readData, setReadData ]   = useState( null );
	const [ redaction, setRedaction ] = useState( null );
	const [ loading, setLoading ]     = useState( true );
	const [ error, setError ]         = useState( null );

	useEffect( () => {
		let cancelled = false;
		Promise.all( [
			apiFetch( { path: `${ base }/write-allowlist` } ),
			apiFetch( { path: `${ base }/read-allowlist` } ),
			apiFetch( { path: `${ base }/redaction` } ),
		] )
			.then( ( [ w, r, red ] ) => {
				if ( cancelled ) {
					return;
				}
				setWriteData( w );
				setReadData( r );
				setRedaction( red );
				setLoading( false );
			} )
			.catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				setError( err.message || __( 'Failed to load settings.', 'acrossai-abilities-manager' ) );
				setLoading( false );
			} );
		return () => {
			cancelled = true;
		};
	}, [ base ] );

	if ( loading ) {
		return <p>{ __( 'Loading settings…', 'acrossai-abilities-manager' ) }</p>;
	}
	if ( error ) {
		return (
			<div className="notice notice-error inline">
				<p>{ error }</p>
			</div>
		);
	}

	const saveWrite = ( allowed_paths ) =>
		apiFetch( {
			path: `${ base }/write-allowlist`,
			method: 'POST',
			data: { allowed_paths },
		} ).then( setWriteData );

	const saveRead = ( allowed_paths ) =>
		apiFetch( {
			path: `${ base }/read-allowlist`,
			method: 'POST',
			data: { allowed_paths },
		} ).then( setReadData );

	const saveRedaction = ( config ) =>
		apiFetch( {
			path: `${ base }/redaction`,
			method: 'POST',
			data: config,
		} ).then( setRedaction );

	return (
		<div className="acrossai-file-manager-settings">
			<div className="acrossai-fm-intro">
				<p>{ __(
					'Control which folders the AI can write to, which folders it can read, and which secrets get scrubbed from every read response. Reads default to unrestricted; writes default to wp-content only.',
					'acrossai-abilities-manager'
				) }</p>
			</div>

			<WriteAllowlistPanel data={ writeData } onSave={ saveWrite } />
			<ReadAllowlistPanel data={ readData } onSave={ saveRead } />
			<RedactionPanel data={ redaction } onSave={ saveRedaction } />
		</div>
	);
};

export default FileManagerSettings;
