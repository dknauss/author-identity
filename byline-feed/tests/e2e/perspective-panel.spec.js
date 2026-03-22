const { test, expect } = require( '@playwright/test' );
const {
	dismissWelcomeGuide,
	getFixturePostId,
	login,
	openPerspectivePanel,
} = require( './helpers' );

test( 'perspective panel saves and persists post meta', async ( { page } ) => {
	const postId = getFixturePostId();

	await login( page );
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.locator( '.edit-post-layout, .interface-interface-skeleton' ).first().waitFor();
	await dismissWelcomeGuide( page );

	await openPerspectivePanel( page );

	const perspectiveField = page.getByRole( 'combobox' ).last();

	await expect( perspectiveField ).toBeVisible();
	await perspectiveField.selectOption( 'analysis' );

	const saveButton = page.locator( '.editor-post-save-draft' );
	await expect( saveButton ).toBeEnabled();
	await saveButton.click();

	await expect(
		page.locator( '.editor-post-saved-state.is-saved' )
	).toBeVisible();

	await page.reload();
	await page.locator( '.edit-post-layout, .interface-interface-skeleton' ).first().waitFor();
	await dismissWelcomeGuide( page );

	await openPerspectivePanel( page );
	await expect( perspectiveField ).toHaveValue( 'analysis' );
} );

test( 'classic editor perspective metabox saves and persists', async ( {
	page,
} ) => {
	const postId = getFixturePostId();

	await login(
		page,
		`/wp-admin/post.php?post=${ postId }&action=edit&byline_force_classic=1`
	);

	const perspectiveField = page.locator(
		'select[name="byline_feed_perspective"]'
	);

	await expect( perspectiveField ).toBeVisible();
	await perspectiveField.selectOption( 'tutorial' );

	const saveButton = page.locator( '#save-post, #publish' ).first();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		saveButton.click(),
	] );

	await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
	await page.reload();
	await expect( perspectiveField ).toHaveValue( 'tutorial' );
} );
