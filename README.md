# Author Identity

[![CI](https://github.com/dknauss/Author-Identity/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/dknauss/Author-Identity/actions/workflows/ci.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL%202.0%2B-blue.svg)](LICENSE)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](byline-feed/composer.json)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)](byline-feed/readme.txt)

Structured author identity that travels with the work across feeds, search, the fediverse, and AI from one source of truth in WordPress.

## What this project is

This repository ("Author Identity") houses the **[Byline Feed](byline-feed/)** WordPress plugin, which implements the [Byline extension vocabulary](https://bylinespec.org) plus additional author-identity output channels. A normalized author-data layer in WordPress drives multiple outputs.

Current shipped scope:

- normalized author resolution for core WordPress, Co-Authors Plus, and PublishPress Authors
- Byline output in RSS2, Atom, and JSON Feed
- perspective storage and editor UI
- `fediverse:creator` meta tag output for singular content
- multi-author JSON-LD Article + Person output for singular content

Next planned tranches:

- HM Authorship adapter support
- WP-06 rights and AI-consent output

Longer-range identity work such as `did:web:` remains in the vision/research layer, not the active roadmap.

**The mental model:** WordPress is a Personal Data Server for authors. The author's WP profile is their everything folder. The plugin makes that PDS speak the open web's formats. Output channels are reactive to the normalized author data — none of them own it.

## What this repository contains

This repository has two related parts:

1. A documentation set defining the vision, protocol landscape, implementation strategy, and quality gates for portable author identity in WordPress.
2. A WordPress plugin, [byline-feed](byline-feed/), that implements the MVP portion of that strategy.

The current implementation focus is the `byline-feed` plugin:

- Normalize author data from core WordPress, Co-Authors Plus, and PublishPress Authors.
- Emit structured Byline metadata in RSS2, Atom, and JSON Feed.
- Emit `fediverse:creator` meta tags in HTML heads for authors with configured fediverse handles.
- Expose content perspective metadata for feed consumers.
- Preserve standard feed elements so Byline output remains additive.
- Prepare the next output and adapter tranches without expanding the active roadmap into broader identity-framework work.

## Documentation map

| Area | Items |
| --- | --- |
| Vision | [author-identity-vision.md](docs/vision/author-identity-vision.md): Full project vision and positioning |
| Planning | [implementation-spec.md](Implementation%20Strategy/implementation-spec.md): Authoritative plugin implementation spec, roadmap, and release gates<br>[byline-spec-plan.md](docs/planning/byline-spec-plan.md): Byline spec assessment — what the plugin validates, current divergences, and pre-1.0 priorities<br>[byline-adoption-strategy.md](docs/planning/byline-adoption-strategy.md): Adoption strategy — audiences, workstreams, and post-Gate-A product direction |
| Research | [docs/README.md](docs/README.md): Documentation tree index<br>[docs/research/README.md](docs/research/README.md): Curated research index with current vs exploratory tiers<br>[multi-author-matrix.md](docs/research/current/multi-author-matrix.md): Comparison of WordPress multi-author systems<br>[protocol-coverage-map.md](docs/research/current/protocol-coverage-map.md): Protocol coverage by output channel<br>[architecture.md](docs/research/current/architecture.md): HM Authorship architecture notes<br>[landscape.md](docs/research/current/landscape.md): Plugin ecosystem and historical lineage<br>[metadata-models-for-publishers.md](docs/research/current/metadata-models-for-publishers.md): WP-05 JSON-LD background and longer-term publication metadata context |
| Playground | [playground/README.md](playground/README.md): Playground demo index<br>[playground/output-demo/README.md](playground/output-demo/README.md): Stable output-demo bundle for feeds, `fediverse:creator`, and JSON-LD |
| Quality | [ASSESSMENT.md](docs/quality/ASSESSMENT.md): Project assessment and recommendations<br>[TEST_COVERAGE_MATRIX.md](docs/quality/TEST_COVERAGE_MATRIX.md): Coverage status and remaining gaps<br>[TDD_TESTING_STANDARD.md](docs/quality/TDD_TESTING_STANDARD.md): Testing workflow and definition of done |
| Work Packages | [wp-01.md](Implementation%20Strategy/wp-01.md) to [wp-06.md](Implementation%20Strategy/wp-06.md): Detailed delivery specs by package<br>[gap-analysis.md](Implementation%20Strategy/gap-analysis.md): Audit of code against the specs<br>[implementation-spec.md](Implementation%20Strategy/implementation-spec.md): Supplemental strategy details and cross-cutting concerns<br>[code-review-plan.md](Implementation%20Strategy/code-review-plan.md): Post-Gate-A hardening backlog from source/test review |
| Legacy | [Byline RSS Spec Adoption/](Byline%20RSS%20Spec%20Adoption/): Earlier planning and legacy positioning documents |

## Plugin status

| Status | Items |
| --- | --- |
| Implemented | adapter interface plus core, Co-Authors Plus, and PublishPress Authors adapters<br>RSS2, Atom, JSON Feed, and JSON-LD output, including `profile` / `now` / `uses` for linked WordPress users via plugin-owned meta<br>content perspective storage and editor UI<br>`fediverse:creator` meta tags for authors with configured fediverse handles<br>conservative `ap_actor_url` resolution for linked WordPress users when ActivityPub identity can be resolved<br>runtime validation for the normalized author contract<br>PHPUnit, PHPCS, Playwright E2E, and GitHub Actions CI scaffolding |
| Not yet implemented | AI consent and rights output<br>Molongui and HM Authorship adapters |
| Primary references | [byline-feed/](byline-feed/)<br>[byline-feed/docs/output-reference.md](byline-feed/docs/output-reference.md)<br>[implementation-spec.md](Implementation%20Strategy/implementation-spec.md)<br>[wp-01.md](Implementation%20Strategy/wp-01.md) to [wp-06.md](Implementation%20Strategy/wp-06.md) |

## Plugin layout

```text
byline-feed/
|-- byline-feed.php
|-- composer.json
|-- package.json
|-- phpunit.xml.dist
|-- bin/
|   `-- install-wp-tests.sh
|-- inc/
|   |-- interface-adapter.php
|   |-- class-adapter-core.php
|   |-- class-adapter-cap.php
|   |-- class-adapter-ppa.php
|   |-- namespace.php
|   |-- feed-common.php
|   |-- feed-rss2.php
|   |-- feed-atom.php
|   |-- feed-json.php
|   |-- fediverse.php
|   |-- schema.php
|   |-- author-meta.php
|   `-- perspective.php
|-- src/
|   `-- perspective-panel.tsx
|-- build/
|   |-- perspective-panel.tsx.asset.php
|   `-- perspective-panel.tsx.js
`-- tests/phpunit/
    |-- bootstrap.php
    |-- test-adapter-core.php
    |-- test-adapter-cap.php
    |-- test-adapter-ppa.php
    |-- test-author-contract.php
    |-- test-fediverse.php
    |-- test-feed-atom.php
    |-- test-feed-json.php
    |-- test-feed-rss2.php
    |-- test-schema.php
    `-- test-perspective.php
```

## Development and verification

### PHP tooling

From [byline-feed/](byline-feed/):

```bash
composer install
composer lint
WP_TESTS_DIR=/tmp/byline-wp-tests composer test
```

### JavaScript build

From [byline-feed/](byline-feed/):

```bash
npm install
npm run build
```

Development-tooling note:

- current npm advisory state is clean after targeted dependency overrides
- `npm run start` still uses development-only tooling and should be treated separately from shipped plugin runtime behavior
- current policy and maintenance expectations are documented in [SECURITY.md](SECURITY.md)

### CI

| Check | Scope |
| --- | --- |
| PHPUnit | PHP 7.4 through 8.3 and WordPress 6.0, 6.4, and latest |
| PHPCS | WordPress Coding Standards |
| Node build | Asset build validation |
| Workflow | [.github/workflows/ci.yml](.github/workflows/ci.yml) |

## Playground

The current Playground strategy is intentionally two-tiered:

- [playground/output-demo/](playground/output-demo/) is the stable source-of-truth bundle for demonstrating shipped outputs
- a later adapter-demo blueprint will showcase real Co-Authors Plus and PublishPress Authors integration without burdening the primary public demo

[![Try in Playground](https://img.shields.io/badge/Try%20in-Playground-21759B?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no)

Use the output-demo bundle locally with:

```bash
npx @wp-playground/cli@latest server --blueprint=playground/output-demo/blueprint.json
```

Build a shareable snapshot ZIP with:

```bash
playground/bin/build-output-demo-snapshot.sh
```

Refresh the published public demo assets with:

```bash
playground/bin/publish-output-demo.sh
```

The public `Try in Playground` CTA now targets the stable `codex/playground-assets` blueprint URL. Each refresh republishes that stable blueprint while pinning the installed plugin to a fresh immutable source tag for the current commit. Local snapshot ZIPs remain useful for offline sharing, archived demos, and reproducible local inspection.

## Governance

| File | Purpose |
| --- | --- |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Development, verification, and AI-attribution guidance |
| [CHANGELOG.md](CHANGELOG.md) | Human-readable project change history and release baseline |
| [RELEASE_NOTES.md](RELEASE_NOTES.md) | Release-note convention and release checklist, including AI-assistance disclosure |
| [.github/CODEOWNERS](.github/CODEOWNERS) | Default review ownership |
| [.github/dependabot.yml](.github/dependabot.yml) | Automated dependency update policy |
| [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) | PR checklist and verification prompts |
| [SECURITY.md](SECURITY.md) | Security reporting guidance and accepted-risk notes for development tooling |

Release discipline:

- update `CHANGELOG.md` when shipping real releases
- draft public release notes using `RELEASE_NOTES.md`
- include the AI-assistance disclosure note in release notes when AI materially affected the release

## Design constraints

Key architectural rules carried through the docs and plugin:

- Byline output is additive and must not replace standard feed elements.
- All output channels consume the same normalized author contract.
- Optional upstream plugin integrations remain optional adapters, not hard dependencies.
- Scope is intentionally constrained to the defined work packages.

## AI assistance disclosure

This repository includes material changes produced with AI assistance (OpenAI Codex and Anthropic Claude).

Preferred attribution model:

- Repository ownership and accountability remain with the human maintainer.
- Commits with substantial AI-generated changes may include an `Assisted-by:` trailer naming the tool used.
- AI assistance is disclosed explicitly rather than represented as human co-authorship.

Practical note:

- GitHub's contributor graph reflects commit author identity, not assistance trailers.
- AI tools are disclosed in repository documentation and commit trailers, but are not represented as separate GitHub contributor accounts.

See also:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CHANGELOG.md](CHANGELOG.md)
- [RELEASE_NOTES.md](RELEASE_NOTES.md)
