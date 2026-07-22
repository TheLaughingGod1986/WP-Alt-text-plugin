#!/usr/bin/env node
/**
 * Minify the large hand-written admin scripts to `<name>.min.js`.
 *
 * The asset resolver (trait-core-assets.php) prefers `<name>.min.js` over
 * `<name>.js` in production (SCRIPT_DEBUG off), so producing these `.min`
 * files is all that's needed for production to serve the smaller build — no
 * PHP changes. Run via `npm run build:js`.
 *
 * Add a source file here when it's large enough to be worth minifying.
 */
import { minify } from 'terser';
import { readFile, writeFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );

const TARGETS = [
	'assets/js/bbai-admin.js',
	'assets/js/bbai-dashboard.js',
];

const kb = ( n ) => `${ Math.round( n / 1024 ) } KB`;

let failed = false;
for ( const rel of TARGETS ) {
	const src = join( root, rel );
	const out = src.replace( /\.js$/, '.min.js' );
	try {
		const code = await readFile( src, 'utf8' );
		const result = await minify( code, { compress: true, mangle: true } );
		if ( ! result.code ) {
			throw new Error( 'empty output' );
		}
		await writeFile( out, result.code );
		const before = ( await stat( src ) ).size;
		const after = ( await stat( out ) ).size;
		console.log( `✓ ${ rel }  ${ kb( before ) } → ${ kb( after ) }` );
	} catch ( err ) {
		failed = true;
		console.error( `✗ ${ rel }: ${ err.message }` );
	}
}

process.exit( failed ? 1 : 0 );
