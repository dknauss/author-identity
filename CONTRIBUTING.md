# Contributing

## Scope

This repository contains both project documentation and the `byline-feed` WordPress plugin. Changes should preserve consistency between:

- repository-level docs in `docs/`
- work package specs in `implementation-strategy/`
- user-facing plugin details in `byline-feed/readme.txt`

## Development expectations

- keep Byline output additive to core feed elements
- preserve the normalized author contract across adapters and outputs
- prefer small, reviewable changes over mixed-purpose edits
- run the relevant checks before merging

## Verification

From `byline-feed/`:

```bash
composer install
composer lint
WP_TESTS_DIR=/tmp/byline-wp-tests composer test
npm install
npm run build
```

## AI assistance attribution

This project allows AI-assisted development, with explicit disclosure.

Preferred convention:

- human maintainers remain the commit authors of record
- substantial AI-assisted commits may include an `Assisted-by: Codex` trailer
- AI assistance should be disclosed in release notes when materially relevant

What not to do:

- do not create fake human co-authors for AI tools
- do not treat GitHub's contributor graph as the place for AI attribution
- do not hide meaningful AI assistance on substantive changes

## Commit guidance

- use focused commit messages
- keep documentation, code, and CI changes separate when practical
- include `Assisted-by: Codex` when AI materially contributed to the final change

## Release discipline

When preparing a real release:

- update [CHANGELOG.md](CHANGELOG.md) first
- move shipped changes out of `Unreleased` into a dated version section
- use [RELEASE_NOTES.md](RELEASE_NOTES.md) when drafting the public release notes
- include the AI-assistance disclosure in the release notes when materially relevant
- keep release notes high signal: user-visible behavior, compatibility, CI/security changes, and notable docs changes

## Documentation updates

When behavior, scope, or process changes, update the relevant docs in the same change set:

- `README.md` for repository-level orientation
- `byline-feed/readme.txt` for plugin-facing user documentation
- `docs/` for vision, planning, research, and quality materials
- `implementation-strategy/` when a work package or delivery rule changes
