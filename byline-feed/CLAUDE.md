# CLAUDE.md

Persistent architectural decisions for the byline-feed plugin project. Read before working on any file in this repo. Keep this file minimal — decisions and constraints only, not explanations the code already provides.

## What this project is

A WordPress plugin that normalizes author identity data from supported multi-author plugins (Co-Authors Plus, PublishPress Authors, core WP today; HM Authorship and Molongui later) and routes it to multiple output channels: Byline RSS/Atom/JSON Feed, JSON-LD schema, fediverse:creator meta tags, and later TDM/AI consent headers. One adapter layer, many outputs.

The mental model: **WordPress is a Personal Data Server for authors.** The author's WP profile is their everything folder. The plugin makes that PDS speak the open web's formats. Output channels are reactive to the normalized author data — none of them own it.

## Active work packages

MVP (wp.org submission target): WP-01, WP-02, WP-03
Shipped post-MVP: WP-04, WP-05, HM Authorship
Shipped WP-06: consent resolution, HTML/header/ai.txt signals, feed-level rights, block editor consent panel
Remaining WP-06: audit logging for consent state changes
Later roadmap: Molongui adapter, additional testing hardening
Reserved/deferred: WP-07 (`did:web:`)

Gate A is complete. WP-04, WP-05, HM Authorship, and WP-06 (minus audit logging) now ship. The sole remaining code deliverable before release-candidate stabilization is WP-06 audit logging. See `../implementation-strategy/implementation-spec.md § Release gates`.

## Normalized author object — field constraints

Three field categories. Annotate any new fields accordingly in code comments:

- **Authored** — user-entered. Authoritative. Emit as-is.
- **Derived** — system-computed fallback (Gravatar, auto-constructed URLs). Not authoritative. Flag clearly in filter names and docs. Never silently promote to the same semantic weight as authored data.
- **Composite** — user-entered with system fallback. Preserve the distinction in output layer.

This matters most in WP-05 (JSON-LD Person objects indexed by Google). A Gravatar URL must not appear in `sameAs` alongside a Mastodon handle — one is declared identity, the other is a display convenience.

## ap_actor_url — first-class field, first-class constraints

`ap_actor_url` is cryptographically meaningful. It is NOT a social profile link. Do not put it in `profiles`. Do not derive it from `profiles`.

- Guest authors (`is_guest: true`) MUST return empty string — no domain-anchored AP identity.
- Adapter resolution: check user meta for AP plugin's stored actor URL; construct it if AP plugin is active and meta is absent.
- This plugin may surface `ap_actor_url` as supporting identity data for WP-04 and WP-05, but `fediverse:creator` tags are driven by the authored `fediverse` handle. Do not substitute one for the other.

## did:web: — reserved future work, not in the current contract

`did:web:` is deferred. It is not part of the active normalized author object contract and must not be added opportunistically. Do not use `id` or `ap_actor_url` as a substitute. Different trust model entirely.

WP-07 target remains future work: possible `did:web:` support in JSON-LD `sameAs` and an optional `/.well-known/did.json` endpoint only after the current post-MVP roadmap ships and there is a concrete consumer.

## ActivityPub plugin jurisdiction boundary

Hard boundary. Do not cross it.

| Signal | Owner |
|---|---|
| `fediverse:creator` meta tags (per author) | This plugin (it removes Jetpack Social's tag in organization mode, and when it prints its own) |
| `ap_actor_url` in JSON-LD `sameAs` | This plugin |
| `attributedTo` on federated AP object | ActivityPub plugin |
| HTTP Signatures, WebFinger, actor JSON | ActivityPub plugin |

No mechanism currently exists for our multi-author data to influence the AP plugin's `attributedTo` output. That requires a filter hook the AP plugin would need to expose. Do not attempt to work around this. Document it for users instead. See `known-gaps.md § ActivityPub plugin: attributedTo is out of scope`.

## Author mode (attribution) — post-level, stored once

- `byline_feed_get_author_mode()` returns `person` or `organization`. Resolution: the editor's choice in `_byline_author_mode` (Attribution panel), then the `byline_feed_author_mode` filter (which receives `$stored`), then `person`.
- It is a post-level decision, not a field of the normalized author contract. Do not add it to author objects.
- In `organization` mode: JSON-LD `author` is the publisher Organization; no `fediverse:creator`, `og:author`, `article:author`, or `<meta name="author">`. Feeds do not follow it yet (open work; see `known-gaps.md`).
- Suggesters (e.g. First Person, `dknauss/first-person-byline`) answer the filter and must defer when `$stored` is not empty. Byline Feed must never depend on any suggester; it only offers the filter and the public function.
- Stable `@id`s: article `<permalink>#article`, publisher `<home>/#organization`. Other plugins (e.g. Borges) attach to these rather than emitting page-level nodes.
- Why: a shared counterfoil.org note was credited "by Albertina" because a cited `WebPage` was a top-level JSON-LD node, and Mastodon reads the first top-level `WebPage`/`NewsArticle` node's author, falling back to `og:author` (`mastodon/mastodon` `app/lib/link_details_extractor.rb`).

## Key cross-references

- Architectural decisions in full: `../docs/vision/author-identity-vision.md § WordPress as a Personal Data Server`
- Author object contract: `../implementation-strategy/implementation-spec.md § Normalized author object contract`
- AP boundary detail: `../docs/research/current/known-gaps.md § Byline Feed plugin — integration boundaries`
- Work package scaffolds: `../implementation-strategy/wp-01.md` through `../implementation-strategy/wp-06.md`

## What not to do

- Do not add fields to the normalized author object without classifying them as authored/derived/composite.
- Do not use `id` as an AP actor URL or DID.
- Do not output `ap_actor_url` in `attributedTo` — that is the AP plugin's field.
- Do not implement WP-07 scope (`did:web:`, `/.well-known/did.json`) until HM Authorship and WP-06 are shipped and there is a concrete consumer.
- Do not add Molongui before HM Authorship. HM Authorship is the next planned adapter tranche after the now-shipped WP-04/WP-05 work.
