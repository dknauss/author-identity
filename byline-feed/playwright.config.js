const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	workers: 1,
	expect: {
		timeout: 10_000,
	},
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8896',
		headless: process.env.PLAYWRIGHT_HEADLESS !== '0',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	reporter: 'line',
	projects: [
		{
			name: 'chromium',
			use: {
				browserName: 'chromium',
			},
		},
	],
} );
