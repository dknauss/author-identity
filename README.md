# Author Identity Vision

Structured author identity that travels with the work across feeds, search, the fediverse, and AI from one source of truth in WordPress.

## What this repository contains

This repository has two related parts:

1. A documentation set defining the vision, protocol landscape, implementation strategy, and quality gates for portable author identity in WordPress.
2. A WordPress plugin, [byline-feed](byline-feed/), that implements the MVP portion of that strategy.

The current implementation focus is the `byline-feed` plugin:

- Normalize author data from core WordPress, Co-Authors Plus, and PublishPress Authors.
- Emit structured Byline metadata in RSS2, Atom, and JSON Feed.
- Expose content perspective metadata for feed consumers.
- Preserve standard feed elements so Byline output remains additive.

## Documentation map

| Area | Items |
| --- | --- |
| Vision | [author-identity-vision.md](docs/vision/author-identity-vision.md): Full project vision and positioning |
| Planning | [implementation-spec.md](docs/planning/implementation-spec.md): Plugin implementation spec and roadmap<br>[byline-spec-plan.md](docs/planning/byline-spec-plan.md): Byline extension assessment and implementation plan<br>[byline-adoption-strategy.md](docs/planning/byline-adoption-strategy.md): Adoption and ecosystem strategy |
| Research | [multi-author-matrix.md](docs/research/multi-author-matrix.md): Comparison of WordPress multi-author systems<br>[protocol-coverage-map.md](docs/research/protocol-coverage-map.md): Protocol coverage by output channel<br>[architecture.md](docs/research/architecture.md): HM Authorship architecture notes<br>[landscape.md](docs/research/landscape.md): Plugin ecosystem and historical lineage<br>[known-gaps.md](docs/research/known-gaps.md): Risks, gaps, and open issues<br>[authorship-architecture.mermaid](docs/research/authorship-architecture.mermaid): Architecture diagram source |
| Quality | [ASSESSMENT.md](docs/quality/ASSESSMENT.md): Project assessment and recommendations<br>[TEST_COVERAGE_MATRIX.md](docs/quality/TEST_COVERAGE_MATRIX.md): Coverage status and remaining gaps<br>[TDD_TESTING_STANDARD.md](docs/quality/TDD_TESTING_STANDARD.md): Testing workflow and definition of done |
| Work Packages | [wp-01.md](Implementation%20Strategy/wp-01.md) to [wp-06.md](Implementation%20Strategy/wp-06.md): Detailed delivery specs by package<br>[gap-analysis.md](Implementation%20Strategy/gap-analysis.md): Audit of code against the specs<br>[implementation-spec.md](Implementation%20Strategy/implementation-spec.md): Supplemental strategy details and cross-cutting concerns |
| Legacy | [Byline RSS Spec Adoption/](Byline%20RSS%20Spec%20Adoption/): Earlier planning and legacy positioning documents |

## Plugin status

| Status | Items |
| --- | --- |
| Implemented | adapter interface plus core, Co-Authors Plus, and PublishPress Authors adapters<br>RSS2, Atom, and JSON Feed Byline output, including `profile` / `now` / `uses` for linked WordPress users via plugin-owned meta<br>content perspective storage and editor UI<br>runtime validation for the normalized author contract<br>PHPUnit, PHPCS, and GitHub Actions CI scaffolding |
| Not yet implemented | `fediverse:creator` output<br>multi-author JSON-LD output<br>AI consent and rights output<br>Molongui and HM Authorship adapters |
| Primary references | [byline-feed/](byline-feed/)<br>[byline-feed/docs/output-reference.md](byline-feed/docs/output-reference.md)<br>[docs/planning/implementation-spec.md](docs/planning/implementation-spec.md)<br>[wp-01.md](Implementation%20Strategy/wp-01.md) to [wp-06.md](Implementation%20Strategy/wp-06.md) |

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
|   |-- feed-rss2.php
|   |-- feed-atom.php
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
    |-- test-feed-atom.php
    |-- test-feed-rss2.php
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

- the remaining open Dependabot alerts are moderate `webpack-dev-server` advisories inherited from `@wordpress/scripts`
- they affect local development workflows around `npm run start`, not the shipped plugin runtime
- current policy and exit criteria are documented in [SECURITY.md](SECURITY.md)

### CI

| Check | Scope |
| --- | --- |
| PHPUnit | PHP 7.4 through 8.3 and WordPress 6.0, 6.4, and latest |
| PHPCS | WordPress Coding Standards |
| Node build | Asset build validation |
| Workflow | [.github/workflows/ci.yml](.github/workflows/ci.yml) |

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
- include the Codex disclosure note in release notes when AI materially affected the release

## Design constraints

Key architectural rules carried through the docs and plugin:

- Byline output is additive and must not replace standard feed elements.
- All output channels consume the same normalized author contract.
- Optional upstream plugin integrations remain optional adapters, not hard dependencies.
- Scope is intentionally constrained to the defined work packages.

## AI assistance disclosure

This repository includes material changes produced with OpenAI Codex assistance.

Preferred attribution model:

- Repository ownership and accountability remain with the human maintainer.
- Commits with substantial AI-generated changes may include an `Assisted-by: Codex` trailer.
- AI assistance is disclosed explicitly rather than represented as human co-authorship.

Practical note:

- GitHub's contributor graph reflects commit author identity, not assistance trailers.
- Codex is disclosed in repository documentation and commit trailers, but is not represented as a separate GitHub contributor account.

See also:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CHANGELOG.md](CHANGELOG.md)
- [RELEASE_NOTES.md](RELEASE_NOTES.md)
