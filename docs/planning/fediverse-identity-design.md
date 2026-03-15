# Fediverse Identity Source Design

## Purpose

This document records the recommended long-term design for how Byline Feed should source and emit `fediverse:creator` handles for linked WordPress users.

It exists because the current implementation is intentionally conservative:

- `fediverse:creator` is driven only by the explicit `fediverse` field on the normalized author object
- `ap_actor_url` remains a distinct derived identity field
- ActivityPub identity does not currently auto-populate or auto-drive `fediverse:creator`

That separation is correct for the current release, but it is not the best long-term UX for sites that already run the ActivityPub plugin and expose local federated author identities.

## Current behavior

Today, Byline Feed emits `<meta name="fediverse:creator">` only when a resolved author has a valid explicit fediverse handle.

Important current rules:

- `profiles[]` does not substitute for the handle
- `ap_actor_url` does not substitute for the handle
- the user profile stores the explicit handle in `byline_feed_fediverse`
- the ActivityPub plugin is used only to derive `ap_actor_url`, not the fediverse handle

This is intentionally strict. It avoids guessing identity from arbitrary profile URLs or from partially configured ActivityPub state.

## Recommendation summary

Long term, Byline Feed should move to a **source-based fediverse identity model**:

1. If the ActivityPub plugin is active and the current user has a confidently resolvable **local ActivityPub actor identity**, Byline Feed should default to using that local identity for `fediverse:creator`.
2. If ActivityPub is not active, Byline Feed should allow manual entry of a valid full fediverse handle.
3. If ActivityPub is active, the UI should present the local fediverse identity as **derived/read-only by default**, but should still allow an explicit manual override.
4. The plugin should not silently copy derived ActivityPub identity into stored manual meta as its primary data model.
5. The plugin should never infer a fediverse handle from arbitrary social profile URLs alone.

This preserves the distinction between:

- **manual authored identity**
- **derived local ActivityPub identity**
- **effective output identity**

## Why this is the recommended model

### Why not just store and lock a copied handle?

Copying the ActivityPub-derived handle into user meta and locking it looks simple, but it creates drift:

- site domain changes can invalidate the stored copy
- ActivityPub actor routing can change
- username or author slug changes can invalidate the stored copy
- ActivityPub plugin behavior can change independently of Byline Feed

The more defensible pattern is:

- derive when possible
- store only manual overrides
- compute the effective emitted handle at render time

### Why not always force the local ActivityPub identity?

A local ActivityPub actor is not always the identity the author wants attributed.

Example:

- the site has ActivityPub enabled
- the author has a valid local actor on the site
- but the author wants `fediverse:creator` to point to a different real-world fediverse account they control elsewhere

If Byline Feed hard-locks attribution to the local actor, it removes a legitimate editorial/identity use case.

So the right model is:

- default to local AP identity when confidently available
- allow explicit override
- allow opting out

## Proposed data model

### Keep

- `byline_feed_fediverse`
  - manual handle storage
  - current meaning should become: **manual override handle**, not universal truth

- `ap_actor_url`
  - derived ActivityPub actor URL
  - remains distinct from the handle

### Add

- `byline_feed_fediverse_source`
  - enum-like string
  - allowed values:
    - `auto`
    - `manual`
    - `none`

### Effective output field

The normalized author object should continue to expose:

- `fediverse`

But it should become the **effective emitted handle**, resolved from source rules rather than simply copied from manual user meta.

## Proposed resolution logic

For linked WordPress users:

1. Read `byline_feed_fediverse_source`.
2. If the source is `manual`:
   - return `byline_feed_fediverse` after validation/normalization
3. If the source is `none`:
   - return empty string
4. If the source is `auto`:
   - attempt to derive a local fediverse handle from ActivityPub state
   - if that derivation is confident, return it
   - otherwise return empty string

### Defaulting rules

Recommended defaults:

- if ActivityPub is active and local user actor identity is confidently resolvable:
  - default source = `auto`
- if ActivityPub is not active:
  - default source = `manual` when a valid stored manual handle exists
  - otherwise no effective handle

### Existing-user migration rule

For existing installs:

- if `byline_feed_fediverse` already has a value, preserve that as `manual`
- do not silently reinterpret old stored values as `auto`

This avoids breaking currently configured attribution.

## What counts as “confidently resolvable”

Byline Feed should only auto-source fediverse identity from ActivityPub when all of the following are true:

1. The ActivityPub plugin is active.
2. The user is a linked WordPress user, not a guest-only author.
3. The ActivityPub plugin exposes a local actor URL for that user.
4. Byline Feed can derive the local handle from trusted ActivityPub state, not from unrelated profile URLs.
5. The plugin is confident the result is a local identity for that user on this site.

If any of those are false, Byline Feed should not auto-emit a handle from ActivityPub state.

## Recommended helper functions

Future implementation should split the logic into three functions:

- `get_byline_feed_manual_fediverse_for_user( int $user_id ): string`
- `get_byline_feed_auto_fediverse_for_user( int $user_id ): string`
- `get_byline_feed_effective_fediverse_for_user( int $user_id ): string`

That keeps source selection explicit and makes tests easier to reason about.

## Normalized author contract implications

No new normalized author field is strictly required yet.

Recommended approach:

- keep `fediverse` as the effective emitted handle
- keep `ap_actor_url` as a separate derived identity field
- do **not** add `fediverse_source` to the normalized output contract unless external consumers actually need it

The source selection can stay internal to author-meta and adapter resolution for now.

## UI recommendation

### If ActivityPub is active and local actor identity is available

Show a fediverse attribution section like this:

**Section title**

`Fediverse attribution`

**Field**

`How should Byline Feed attribute you on the fediverse?`

**Options**

1. `Use this site's ActivityPub identity (Recommended)`
   - description:
     `Byline Feed will derive your fediverse handle from this site's local ActivityPub identity for your account.`

2. `Use a custom fediverse handle`
   - description:
     `Use this if you want attribution to point to a different account, such as an external Mastodon profile you control.`

3. `Do not emit fediverse attribution`
   - description:
     `Byline Feed will not emit fediverse:creator meta tags for content attributed to you.`

**Read-only derived field**

`Local ActivityPub identity`

Example help text:

`This value is derived from the ActivityPub plugin and cannot be edited here.`

**Manual override field**

`Custom fediverse handle`

Example help text:

`Enter a full handle like @you@mastodon.social. This will override the local ActivityPub identity when "Use a custom fediverse handle" is selected.`

### If ActivityPub is not active

Show a simpler manual field:

**Field**

`Fediverse handle`

**Help text**

`Enter a full handle like @you@mastodon.social. Byline Feed uses this for fediverse:creator author attribution on singular content.`

**Validation message**

`Enter a full fediverse handle in the form @user@domain.`

## Explicit non-goals

The future design should not:

- infer fediverse identity from arbitrary `profiles[]` values
- use `ap_actor_url` as a drop-in substitute for the handle
- silently overwrite manual stored values with auto-derived values
- force local ActivityPub identity when an author intentionally wants a different fediverse attribution target

## Backlog recommendation

This should be treated as a **future WP-04 maintenance/design tranche**, not as a blocking release issue.

Recommended backlog tasks:

1. Add `byline_feed_fediverse_source` user meta and UI.
2. Add source-aware helper functions for manual, auto, and effective fediverse resolution.
3. Add ActivityPub-aware auto derivation only for confidently resolvable local user actors.
4. Preserve existing manual `byline_feed_fediverse` values as manual overrides during migration.
5. Add PHPUnit coverage for source selection and fallback rules.
6. Add browser coverage for the fediverse attribution profile UI.

## Decision

Recommended decision:

- **Do not** implement “copy ActivityPub identity into user meta and lock it” as the primary design.
- **Do** implement a source-based model with:
  - auto-derived local ActivityPub identity when confidently available
  - manual override when needed
  - explicit opt-out

That keeps the identity model technically defensible and editorially usable.
