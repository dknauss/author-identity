# Byline Feed Plugin — Adoption Strategy

## Purpose

This document covers the ecosystem and adoption strategy for `byline-feed` now that Gate A is complete. It complements the active implementation roadmap in [implementation-spec.md](implementation-spec.md).

Use this document to answer:

- why the plugin exists beyond its current feed MVP
- which ecosystems matter most for adoption
- what evidence should be generated next
- how post-Gate-A product work supports adoption without depending on feed-reader support

For the authoritative execution order, use [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) and [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md).

## Current state

The project is no longer in a speculative pre-launch phase.

Current shipped baseline:

- core WordPress, Co-Authors Plus, and PublishPress Authors adapters
- RSS2, Atom, and JSON Feed output
- perspective storage and editor UI
- contract validation, PHPUnit coverage, PHPCS, and GitHub Actions CI
- live verification against real local feeds

What is still not shipped:

- `fediverse:creator` output (WP-04)
- JSON-LD output (WP-05)
- rights and AI-consent output (WP-06)
- HM Authorship and Molongui adapters

Adoption therefore has two tracks:

1. strengthen supply-side evidence for the existing Byline feed implementation
2. expand the plugin into adjacent output channels that deliver value even before reader-side Byline adoption arrives

## What adoption means now

The early adoption problem has changed.

The plugin is no longer trying to prove that a WordPress implementation is possible. It now needs to prove three narrower things:

1. real publishers can emit valid, structured author identity from common WordPress setups
2. the plugin is useful even if feed-reader adoption is slow
3. the implementation experience is concrete enough to influence the upstream Byline spec

## Primary audiences

### 1. Current WordPress feed publishers

These are the sites that can emit Byline data now or soonest:

| Segment | Why it matters | Current status |
| --- | --- | --- |
| Core WordPress sites | Baseline supply of single-author feeds | Supported |
| Co-Authors Plus sites | Large legacy multi-author install base | Supported |
| PublishPress Authors sites | Large active multi-author install base | Supported |
| HM Authorship sites | Cleaner upstream API, high-capability users | Next adapter tranche after WP-04/05 |
| Molongui sites | Additional multi-author reach | Backlog after higher-value work |

This implies the immediate supply-side focus is not "support every authorship plugin." It is:

- keep core/CAP/PPA stable
- ship the next value-driving output channels
- then add HM Authorship as the next adapter tranche

### 2. Feed reader and spec stakeholders

These are the people who can turn emitted metadata into visible user value:

- the Byline spec author and future implementors
- feed reader maintainers
- technically engaged publishers and IndieWeb-adjacent users

The pitch is no longer hypothetical:

- here is a working plugin
- here are real feed examples
- here are concrete spec issues found during implementation

### 3. Publishers who care about identity beyond feeds

This is why WP-04 and WP-05 matter strategically.

- `fediverse:creator` has deployed consumers today
- JSON-LD has deployed consumers today
- both validate the adapter architecture without waiting for feed-reader support

That makes adoption broader than "get readers to parse Byline." It becomes:

- make WordPress the authoritative source of portable author identity
- prove the same normalized contract works across channels

## Product strategy after Gate A

### Keep the feed layer credible

The feed MVP should remain the reference implementation for Byline in WordPress:

- keep CI green
- keep feed output additive and stable
- preserve output-reference docs and examples
- keep testing specific rather than generic

### Ship adjacent outputs that have consumers now

The next product sequence remains:

1. WP-04 `fediverse:creator`
2. WP-05 JSON-LD
3. HM Authorship adapter tranche
4. WP-06 rights and AI-consent work

This order is deliberate.

- WP-04 and WP-05 expand utility without waiting for Byline reader support
- HM Authorship then extends adapter reach using a cleaner upstream model
- WP-06 remains valuable but is the most policy-sensitive and stateful work

### Keep the identity model disciplined

The active planning boundary is:

- `ap_actor_url` is a cross-cutting WP-04/WP-05 design field
- `did:web:` remains vision-level future work

This matters for adoption because it prevents the roadmap from inflating into a generalized identity framework before the current outputs ship.

## Adoption workstreams

### Workstream 1: supply-side proof

Produce and maintain evidence that real WordPress feeds emit Byline data correctly.

Artifacts:

- validated example feeds
- local/live feed verification notes
- consumer-facing output reference
- compatibility notes for core/CAP/PPA

Success signal:

- someone evaluating the spec can inspect the plugin and immediately see non-theoretical output

### Workstream 2: upstream spec feedback

The implementation has already surfaced real spec issues:

- multi-author-per-item structure
- JSON Feed structure model
- terminology drift around `organization`, `publication`, and `publisher`

This is now one of the plugin's strongest adoption contributions. It is not just "another implementation." It is a source of concrete implementation evidence for the spec itself.

Success signal:

- upstream discussion resolves or narrows these divergences before the plugin is positioned as stable 1.0

### Workstream 3: channel expansion

Use WP-04 and WP-05 to show that the normalized author contract is useful beyond feeds.

Why this helps adoption:

- publishers get immediate value even if Byline readers lag
- the adapter layer becomes harder to dismiss as "feed-only"
- identity consistency across feeds, HTML metadata, and fediverse discovery becomes demonstrable

Success signal:

- the project is valuable to publishers even before reader-side Byline support arrives

### Workstream 4: next adapter tranche

HM Authorship is the next adapter tranche after WP-04/05, not because of broad install count, but because:

- it has a clean upstream `Authorship\get_authors( WP_Post )` API
- it represents a high-capability WordPress publishing environment
- existing prior art already exists in the separate `authorship` repo

This should be framed as an implementation-quality expansion, not a mass-adoption play.

Success signal:

- the plugin supports both the dominant wp.org multi-author plugins and the strongest enterprise/developer-oriented model

## Messaging guidance

### What to say

- "WordPress can emit structured multi-author identity today."
- "The plugin already proves the adapter model on real feeds."
- "The next outputs (`fediverse:creator`, JSON-LD) extend that same identity model to channels with deployed consumers."
- "The implementation has already surfaced concrete spec issues worth resolving before stable 1.0."

### What not to say

- do not pitch the project as if it still has no implementation
- do not imply the plugin already ships WP-06
- do not present `did:web:` or broader graph work as near-term roadmap
- do not treat HM Authorship as either a must-have MVP dependency or as permanently out of scope

## Near-term adoption priorities

1. Keep the current feed implementation stable and well-documented.
2. Keep WP-04 and WP-05 stable, documented, and well-tested.
3. Continue upstream Byline-spec conversations using implementation evidence.
4. Implement HM Authorship after the now-shipped WP-04/WP-05 tranches.
5. Revisit stable-release positioning only after the known spec divergences are addressed or consciously accepted.

## Related documents

- [implementation-spec.md](implementation-spec.md) — active roadmap summary
- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — authoritative work-package sequence and release gates
- [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md) — current status and next priorities
- [byline-spec-plan.md](byline-spec-plan.md) — current Byline-spec assessment and spec-alignment issues
- [author-identity-vision.md](../vision/author-identity-vision.md) — broader long-range product vision
