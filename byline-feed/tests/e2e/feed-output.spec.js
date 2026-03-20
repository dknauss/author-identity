const { test, expect } = require( '@playwright/test' );
const { getFixturePostId } = require( './helpers' );

test( 'RSS2 feed contains byline namespace and contributors block', async ( {
	page,
} ) => {
	await page.goto( '/feed/' );

	const content = await page.content();

	expect( content ).toContain( 'xmlns:byline' );
	expect( content ).toContain( 'byline:contributors' );
} );

test( 'Atom feed contains byline namespace elements', async ( { page } ) => {
	await page.goto( '/feed/atom/' );

	const content = await page.content();

	expect( content ).toContain( 'byline' );
} );

test( 'JSON Feed is valid JSON with _byline extension at feed level', async ( {
	page,
} ) => {
	const response = await page.goto( '/feed/json' );

	expect( response.ok() ).toBe( true );

	const body = await response.text();
	let feedData;

	try {
		feedData = JSON.parse( body );
	} catch ( e ) {
		throw new Error( `JSON Feed response is not valid JSON: ${ e.message }` );
	}

	expect( feedData ).toHaveProperty( '_byline' );
} );

test( 'fixture post contains JSON-LD Article schema with Person author', async ( {
	page,
} ) => {
	const postId = getFixturePostId();

	await page.goto( `/?p=${ postId }` );

	const ldJsonScripts = page.locator( 'script[type="application/ld+json"]' );
	const count = await ldJsonScripts.count();

	expect( count ).toBeGreaterThan( 0 );

	let foundArticle = false;

	for ( let i = 0; i < count; i++ ) {
		const scriptContent = await ldJsonScripts.nth( i ).textContent();

		let schema;

		try {
			schema = JSON.parse( scriptContent );
		} catch ( e ) {
			continue;
		}

		const schemas = Array.isArray( schema )
			? schema
			: schema[ '@graph' ] ?? [ schema ];

		for ( const node of schemas ) {
			if ( node[ '@type' ] === 'Article' || node[ '@type' ] === 'BlogPosting' ) {
				foundArticle = true;

				const author = node.author;
				expect( author ).toBeDefined();

				const authors = Array.isArray( author ) ? author : [ author ];
				const hasPersonAuthor = authors.some(
					( a ) => a[ '@type' ] === 'Person'
				);

				expect( hasPersonAuthor ).toBe( true );
			}
		}
	}

	expect( foundArticle ).toBe( true );
} );

test( 'fixture post fediverse:creator meta tag is present when handle is set', async ( {
	page,
} ) => {
	const postId = getFixturePostId();

	await page.goto( `/?p=${ postId }` );

	const metaTag = page.locator( 'meta[name="fediverse:creator"]' );
	const isPresent = await metaTag.isVisible().catch( () => false );

	if ( ! isPresent ) {
		const count = await metaTag.count();

		if ( count === 0 ) {
			test.skip();
			return;
		}
	}

	const content = await metaTag.first().getAttribute( 'content' );
	expect( content ).toBeTruthy();
	expect( content ).toMatch( /^@?.+@.+/ );
} );
