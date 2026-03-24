/**
 * AI Valve — React admin entry point.
 *
 * Rendered into #ai-valve-root by AdminPage::render_page().
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './admin.css';

const container = document.getElementById( 'ai-valve-root' );
if ( container ) {
	createRoot( container ).render( <App /> );
}
