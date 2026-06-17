import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const mount = document.getElementById( 'serpcheap-rt-app' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
