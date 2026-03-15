const fs = require( 'fs' );
const path = require( 'path' );
const { expect } = require( '@playwright/test' );

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
	await page
		.getByLabel( /username or email address/i )
		.fill( process.env.WP_E2E_USER || 'admin' );
	await page
		.getByLabel( /^password$/i )
		.fill( process.env.WP_E2E_PASSWORD || 'password' );
	await page.getByRole( 'button', { name: /log in/i } ).click();
	await expect( page ).toHaveURL( /\/wp-admin/ );
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

	const panelButton = page
		.getByRole( 'button', { name: 'Content Perspective' } )
		.last();
	await panelButton.scrollIntoViewIfNeeded();

	const expanded = await panelButton.getAttribute( 'aria-expanded' );
	if ( 'true' !== expanded ) {
		await panelButton.click();
	}
}

module.exports = {
	dismissWelcomeGuide,
	getFixturePostId,
	login,
	openPerspectivePanel,
};
