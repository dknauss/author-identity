# CLAUDE.md

Persistent architectural decisions for the byline-feed plugin project. Read before working on any file in this repo. Keep this file minimal — decisions and constraints only, not explanations the code already provides.

## What this project is

A WordPress plugin that normalizes author identity data from any multi-author plugin (Co-Authors Plus, PublishPress Authors, Molongui, HM Authorship, core WP) and routes it to multiple output channels: Byline RSS/Atom feeds, JSON-LD schema, fediverse:creator meta tags, TDM/AI consent headers. One adapter layer, many outputs.

The mental model: **WordPress is a Personal Data Server for authors.** The author's WP profile is their everything folder. The plugin makes that PDS speak the open web's formats. Output channels are reactive to the normalized author data — none of them own it.

## Active work packages

MVP (wp.org submission target): WP-01, WP-02, WP-03
Post-MVP gated: WP-04, WP-05, WP-06
Reserved/deferred: WP-07 (did:web:)

Do not expand scope into WP-04+ until Gate A is passed. See `implementation-spec.md § Release gates`.

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
- This plugin outputs `ap_actor_url` in `fediverse:creator` tags and JSON-LD `sameAs`. That is the full extent of our jurisdiction.

## did field — reserved, do not implement yet

`did` field exists in the author object contract. MUST return empty string in all adapters until WP-07 ships. Do not use `id` as a substitute. Do not conflate with `ap_actor_url`. Different trust model entirely.

WP-07 target: `did:web:` support — DID URI in JSON-LD `sameAs` and Byline feed, optional `/.well-known/did.json` endpoint. Scaffold deferred. See `author-identity-vision.md § did:web: as post-MVP bridge`.

## ActivityPub plugin jurisdiction boundary

Hard boundary. Do not cross it.

| Signal | Owner |
|---|---|
| `fediverse:creator` meta tags (per author) | This plugin |
| `ap_actor_url` in JSON-LD `sameAs` | This plugin |
| `attributedTo` on federated AP object | ActivityPub plugin |
| HTTP Signatures, WebFinger, actor JSON | ActivityPub plugin |

No mechanism currently exists for our multi-author data to influence the AP plugin's `attributedTo` output. That requires a filter hook the AP plugin would need to expose. Do not attempt to work around this. Document it for users instead. See `known-gaps.md § ActivityPub plugin: attributedTo is out of scope`.

## Key cross-references

- Architectural decisions in full: `author-identity-vision.md § WordPress as a Personal Data Server`
- Author object contract: `implementation-spec.md § Normalized author object contract`
- AP boundary detail: `known-gaps.md § Byline Feed plugin — integration boundaries`
- Work package scaffolds: `wp-01.md` through `wp-06.md`

## What not to do

- Do not add fields to the normalized author object without classifying them as authored/derived/composite.
- Do not use `id` as an AP actor URL or DID.
- Do not output `ap_actor_url` in `attributedTo` — that is the AP plugin's field.
- Do not implement WP-07 scope (did:web:, /.well-known/did.json) until WP-05 and WP-06 are shipped and gated.
- Do not add Molongui or HM Authorship adapters until CAP and PPA adapters are proven in production (Gate B).
