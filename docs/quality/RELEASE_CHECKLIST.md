# Release Checklist

Short operational checklist for publishing a Byline Feed release.

## Preconditions

1. `main` is clean and pushed.
2. The latest CI run on `main` is green.
3. `CHANGELOG.md` and `RELEASE_NOTES.md` reflect the intended release.
4. Plugin version metadata in [`byline-feed/byline-feed.php`](../../byline-feed/byline-feed.php) matches the release target.
5. WordPress.org-facing metadata in [`byline-feed/readme.txt`](../../byline-feed/readme.txt) matches the release target.

## Build

From [`byline-feed/`](../../byline-feed/):

```bash
bin/build-plugin-zip.sh
```

Expected output:

- `dist/byline-feed-<version>.zip`
- `dist/byline-feed-<version>.zip.sha256`

## Sanity check

Inspect the built zip once before publishing.

The release archive should contain only runtime files:

- `byline-feed.php`
- `readme.txt`
- `build/`
- `inc/`

It should not contain development-only material such as:

- `node_modules/`
- `vendor/`
- `tests/`
- local temp files
- Playwright artifacts

## Publish

1. Create a GitHub release or prerelease with both the built zip and `.sha256` checksum attached.
2. Use the summary from [`RELEASE_NOTES.md`](../../RELEASE_NOTES.md).
3. Include direct links to the current Playground demo and release-feedback issue.
4. Include the AI-assistance disclosure note when applicable.
5. If this is a prerelease, open or refresh a public stabilization/feedback issue.

## Post-release

1. Smoke-check the published release page and attached zip once.
2. Confirm the Playground demo link is still valid.
3. Watch CI and issue feedback briefly before starting the next feature tranche.
