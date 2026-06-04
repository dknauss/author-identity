#!/usr/bin/env node

const fs = require( 'fs' );
const path = require( 'path' );

const candidates = [
	path.join(
		__dirname,
		'..',
		'node_modules',
		'@wordpress',
		'env',
		'lib',
		'runtime',
		'docker',
		'docker-config.js'
	),
	path.join(
		__dirname,
		'..',
		'node_modules',
		'@wordpress',
		'env',
		'lib',
		'runtime',
		'docker',
		'init-config.js'
	),
	path.join(
		__dirname,
		'..',
		'node_modules',
		'@wordpress',
		'env',
		'lib',
		'init-config.js'
	),
];

const target = candidates.find( ( candidate ) => fs.existsSync( candidate ) );

if ( ! target ) {
	console.log( 'No local @wordpress/env install found to patch.' );
	process.exit( 0 );
}

const original = fs.readFileSync( target, 'utf8' );
const search =
	'RUN composer global require --dev phpunit/phpunit:"^5.7.21 || ^6.0 || ^7.0 || ^8.0 || ^9.0 || ^10.0"';
const replacement = [
	'RUN composer global config audit.block-insecure false',
	search,
].join( '\\n' );

if ( original.includes( replacement ) ) {
	console.log( `@wordpress/env already patched: ${ target }` );
	process.exit( 0 );
}

if ( ! original.includes( search ) ) {
	console.warn(
		`Unable to find the wp-env Composer bootstrap command in ${ target }.`
	);
	process.exit( 0 );
}

fs.writeFileSync( target, original.replace( search, replacement ) );
console.log( `Patched @wordpress/env Composer config in ${ target }` );
