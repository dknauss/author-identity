const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );

function getFixturePostId() {
	const postIdFile = path.join( __dirname, '../../.tmp/e2e-post-id' );

	if ( ! fs.existsSync( postIdFile ) ) {
		throw new Error(
			'Missing E2E fixture post ID. Run `npm run env:setup` first.'
		);
	}

	return fs.readFileSync( postIdFile, 'utf8' ).trim();
}

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( /username or email address/i ).fill( process.env.WP_E2E_USER || 'admin' );
	await page.getByLabel( /^password$/i ).fill( process.env.WP_E2E_PASSWORD || 'password' );
	await page.getByRole( 'button', { name: /log in/i } ).click();
	await expect( page ).toHaveURL( /\/wp-admin/ );
}

async function openPerspectivePanel( page ) {
	const settingsToggle = page.getByRole( 'button', { name: /^Settings$/ } );

	if ( await settingsToggle.isVisible().catch( () => false ) ) {
		const pressed = await settingsToggle.getAttribute( 'aria-pressed' );

		if ( 'false' === pressed ) {
			await settingsToggle.click();
		}
	}

	const documentTab = page.getByRole( 'tab', { name: /^Post$/ } );

	if ( await documentTab.isVisible().catch( () => false ) ) {
		await documentTab.click();
	}

	const panelButton = page.getByRole( 'button', { name: 'Content Perspective' } ).last();
	await panelButton.scrollIntoViewIfNeeded();

	const expanded = await panelButton.getAttribute( 'aria-expanded' );
	if ( 'true' !== expanded ) {
		await panelButton.click();
	}
}

async function dismissWelcomeGuide( page ) {
	const welcomeDialog = page.getByRole( 'dialog', {
		name: /welcome to the editor/i,
	} );

	if ( ! ( await welcomeDialog.isVisible().catch( () => false ) ) ) {
		return;
	}

	await welcomeDialog.getByRole( 'button', { name: 'Close' } ).click();
	await expect( welcomeDialog ).toBeHidden();
}

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
