const { test, expect } = require( '@playwright/test' );
const { getFixturePostId, login } = require( './helpers' );

test( 'user profile ai consent field saves and persists', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/profile.php' );

	const consentField = page.locator( '#byline-feed-ai-consent' );

	await expect( consentField ).toBeVisible();
	await consentField.selectOption( 'deny' );

	await page.getByRole( 'button', { name: /update profile/i } ).click();

	await expect(
		page.locator( '#message.updated, .notice-success' ).first()
	).toBeVisible();

	await page.reload();
	await expect( consentField ).toHaveValue( 'deny' );
} );

test( 'classic editor ai consent metabox saves and persists', async ( {
	page,
} ) => {
	const postId = getFixturePostId();

	await login( page );
	await page.goto(
		`/wp-admin/post.php?post=${ postId }&action=edit&byline_force_classic=1`
	);

	const consentField = page.locator( '#byline-feed-ai-consent' );

	await expect( consentField ).toBeVisible();
	await consentField.selectOption( 'deny' );

	const saveButton = page.locator( '#save-post, #publish' ).first();
	await saveButton.click();

	await expect(
		page.locator( '#message.updated, .notice-success' ).first()
	).toBeVisible();

	await page.reload();
	await expect( consentField ).toHaveValue( 'deny' );
} );
