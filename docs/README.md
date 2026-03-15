# Documentation Index

This directory contains the maintained project documentation for the Author Identity repository.

## Main sections

| Area | Purpose |
| --- | --- |
| [`vision/`](vision/) | Long-form framing for the author-identity problem space and product direction |
| [`planning/`](planning/) | Roadmaps, implementation sequencing, adoption strategy, and spec plans |
| [`research/`](research/) | Source-grounded research inputs, split into current planning references and exploratory semantic-publishing notes |
| [`quality/`](quality/) | Assessment, test coverage, and testing standards |

## Research guidance

The research tree is intentionally split by priority:

- [`research/current/`](research/current/) contains the documents that actively inform the plugin roadmap, adapter strategy, and protocol work.
- [`research/exploratory/`](research/exploratory/) contains longer-range semantic-publishing and identifier-model notes. These strengthen the rationale for later work but do not expand the near-term plugin scope by themselves.

See [`research/README.md`](research/README.md) for the curated research index and de-emphasis/merge notes.

## Planning guidance

- [`Implementation Strategy/implementation-spec.md`](../Implementation%20Strategy/implementation-spec.md) is the authoritative implementation spec, roadmap, and release-gate reference.
- [`planning/implementation-spec.md`](planning/implementation-spec.md) redirects to the authoritative spec. It exists so that in-tree links from the `docs/planning/` directory resolve without breaking.
- [`planning/byline-spec-plan.md`](planning/byline-spec-plan.md) tracks Byline spec alignment: what the plugin validates, current divergences, and pre-1.0 priorities. It does not override the execution order in the authoritative spec.
- [`planning/byline-adoption-strategy.md`](planning/byline-adoption-strategy.md) covers adoption strategy: audiences, workstreams, and post-Gate-A product direction. It does not override the execution order in the authoritative spec.
- [`planning/fediverse-identity-design.md`](planning/fediverse-identity-design.md) records the future source-model recommendation for manual fediverse handles versus derived local ActivityPub identity.

## Release guidance

- [`quality/RELEASE_CHECKLIST.md`](quality/RELEASE_CHECKLIST.md) is the short operational release path for Byline Feed packaging and publishing.
- [`../RELEASE_NOTES.md`](../RELEASE_NOTES.md) remains the wording/convention source for public release notes.
