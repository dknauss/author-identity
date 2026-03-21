# Byline Feed Plugin — Implementation Spec

The authoritative implementation spec, roadmap, release gates, and cross-cutting concerns live in:

**[implementation-strategy/implementation-spec.md](../../implementation-strategy/implementation-spec.md)**

That document covers:

- plugin identity and naming decision
- architectural overview and normalized author contract
- adapter detection and priority
- work package sequence and post-Gate-A execution order
- release gates (A through D)
- filter/hook API
- file structure (shipped and planned)
- test strategy and testing matrix
- acceptance criteria for wp.org submission
- cross-cutting concerns (CI, adapter validation, contract enforcement, feed validation, consumer docs)
- pre-WP-04 refinements
- delivery schedule

Related planning documents in this directory:

- [byline-spec-plan.md](byline-spec-plan.md) — Byline spec alignment: what the plugin validates, current divergences, and pre-1.0 priorities
- [byline-adoption-strategy.md](byline-adoption-strategy.md) — adoption strategy: audiences, workstreams, and post-Gate-A product direction
