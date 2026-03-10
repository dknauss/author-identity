# Author Identity Vision

Structured author identity that travels with the work — across feeds, search, the fediverse, and AI — from one source of truth in WordPress.

## Repository layout

### Vision and research

- [author-identity-vision.md](author-identity-vision.md) — Full vision document
- [multi-author-matrix.md](multi-author-matrix.md) — **Implementation comparison matrix** across all multi-author systems
- [protocol-coverage-map.md](protocol-coverage-map.md) — **Protocol coverage map** — which specs carry which identity signals across which channels
- [architecture.md](architecture.md) — Authorship plugin architecture reference
- [landscape.md](landscape.md) — Multi-author plugin landscape analysis
- [known-gaps.md](known-gaps.md) — Known gaps and open questions
- [implementation-spec.md](implementation-spec.md) — Byline Feed plugin implementation spec and roadmap
- [byline-spec-plan.md](byline-spec-plan.md) — Byline RSS spec plan
- [byline-adoption-strategy.md](byline-adoption-strategy.md) — Byline spec adoption strategy
- [authorship-architecture.mermaid](authorship-architecture.mermaid) — Architecture diagram
- [Implementation Strategy/](Implementation%20Strategy/) — Work package specs (WP-01 through WP-06)
- [Byline RSS Spec Adoption/](Byline%20RSS%20Spec%20Adoption/) — Byline RSS spec adoption documents

### Plugin

- [byline-feed/](byline-feed/) — WordPress plugin implementing the Byline spec

```
byline-feed/
├── byline-feed.php               # Bootstrap, adapter detection on plugins_loaded
├── composer.json                 # PHPUnit, WPCS dev deps
├── package.json                  # @wordpress/scripts for block editor build
├── readme.txt                    # wp.org readme
├── inc/
│   ├── interface-adapter.php     # Adapter contract
│   ├── class-adapter-core.php    # Core WP fallback
│   ├── class-adapter-cap.php     # Co-Authors Plus
│   ├── class-adapter-ppa.php     # PublishPress Authors
│   ├── namespace.php             # Public API: byline_feed_get_authors(), get_perspective(), role mapping
│   ├── feed-rss2.php             # xmlns:byline namespace, contributors, per-item refs
│   ├── feed-atom.php             # Parallel Atom implementation
│   └── perspective.php           # Meta registration, classic metabox, block editor asset enqueue
├── src/
│   └── perspective-panel.tsx     # Block editor PluginDocumentSettingPanel
└── tests/phpunit/
    ├── test-adapter-core.php     # Author resolution, roles, zero-value fields
    ├── test-feed-rss2.php        # Namespace, contributors, refs, perspective, XML well-formedness
    └── test-perspective.php      # Validation, allowed values, filter override
```

