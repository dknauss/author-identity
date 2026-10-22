# Release Checklist

Short operational checklist for publishing a Byline Feed release.

## Preconditions

1. `main` is clean and pushed.
2. The latest CI run on `main` is green, and the release path in [`LOCAL_VERIFICATION.md`](LOCAL_VERIFICATION.md) passes locally. If `main`'s last CI run predates the current WordPress or adapter-plugin release, run CI by hand first.
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

Publishing is automated by [`.github/workflows/release.yml`](../../.github/workflows/release.yml):

1. Open a PR that bumps `BYLINE_FEED_VERSION` and the `Version:` header in `byline-feed/byline-feed.php`, and adds a `## Draft: <version>` section to [`RELEASE_NOTES.md`](../../RELEASE_NOTES.md). The workflow uses that section as the release body, so it must include the Playground and feedback links and the AI-assistance note when applicable.
2. Record the verification evidence from [`LOCAL_VERIFICATION.md`](LOCAL_VERIFICATION.md#evidence-to-keep) in the PR.
3. Merging to `main` builds the zip and checksum and publishes the GitHub release and tag. Versions with a `-` suffix publish as prereleases.
4. If this is a prerelease, open or refresh a public stabilization/feedback issue.

## Post-release

1. Smoke-check the published release page and attached zip once.
2. Confirm the Playground demo link is still valid.
3. Watch CI and issue feedback briefly before starting the next feature tranche.
