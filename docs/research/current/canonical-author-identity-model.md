# Recommended Canonical Author Identity Model

## Purpose

This document recommends a canonical data model for the Author Identity vision project, based on the current protocol and metadata research in this repository.

It is not a protocol spec. It is a source-model recommendation: the internal shape that WordPress author, publication, role, and identifier data should take **before** being routed into feeds, JSON-LD, fediverse metadata, and future scholarly integrations.

It synthesizes lessons from:

- [`protocol-coverage-map.md`](protocol-coverage-map.md)
- [`metadata-models-for-publishers.md`](metadata-models-for-publishers.md)
- [`multi-author-matrix.md`](multi-author-matrix.md)
- [`nlweb-yoast-context.md`](nlweb-yoast-context.md)
- [`../../vision/author-identity-vision.md`](../../vision/author-identity-vision.md)
- exploratory graph notes in `docs/research/exploratory/`

---

## Executive summary

The project should treat author identity as a **graph of entities and relationships**, not as a display string and not as a single protocol's schema.

The recommended canonical model has these primary entities:

- **Person**
- **Organization**
- **Publication / Work**
- **Contribution / Role**
- **Identifier**
- **Profile / verification link**

The strongest lesson from scholarly metadata systems is not citation formatting. It is the use of:

- persistent identifiers
- explicit contributor roles
- work/person/organization separation
- machine-readable multi-author structures
- ordered relationships between people and works

For this project, that means:

- **ORCID-style person identity matters more than citation-string parsing**
- **DOI-style work identity matters more than bibliography text generation**
- **roles should be explicit objects or structured relations, not loose labels**
- **output protocols are adapters, not the source of truth**

---

## What the research has already established

### 1. No single protocol is enough

[`protocol-coverage-map.md`](protocol-coverage-map.md) shows that author identity leaves WordPress through multiple channels:

- feeds
- HTML head / JSON-LD
- HTTP headers
- well-known files
- federation

No single format covers all of these. The plugin's job is therefore to maintain **one normalized source of truth** and route it outward.

### 2. Multi-author is not optional

[`multi-author-matrix.md`](multi-author-matrix.md), [`nlweb-yoast-context.md`](nlweb-yoast-context.md), and [`author-identity-vision.md`](../../vision/author-identity-vision.md) all point to the same conclusion:

- multiple authors must be first-class
- author order matters
- contributor roles matter
- person vs organization attribution matters

This rules out any canonical model that collapses authors into a single comma-separated field.

### 3. Authorship is not the same as control

A major conclusion in [`author-identity-vision.md`](../../vision/author-identity-vision.md) is that systems often conflate:

- attribution
- control / authorization
- provenance
- intellectual property

The canonical model must keep these separable. In particular, "who wrote this" must not be reduced to "who controls the object".

### 4. Scholarly infrastructure points toward identifiers + relations

[`metadata-models-for-publishers.md`](metadata-models-for-publishers.md) and the exploratory graph docs show a consistent pattern:

- **ORCID** for person identity
- **ROR** for organization identity
- **DOI** for publication identity
- SPAR / FaBiO / PRO for bibliographic and role modeling

This is the most important transferable lesson from the scholarly ecosystem.

---

## Recommended canonical entity model

## 1. Person

A person is an attributed human author/contributor identity.

Recommended fields:

- internal stable ID
- display name
- structured personal name parts where known
  - given name
  - family name
  - literal / display-only name when decomposition is not appropriate
- description / bio
- profile URL
- avatar URL
- profile links
- persistent identifiers
  - ORCID
  - ISNI
  - future DID support
- fediverse identity
  - handle
  - actor URL when available
- consent / rights preferences
- visibility / identity mode
  - named
  - pseudonymous
  - anonymous / collective

Notes:

- Keep both structured and literal naming available.
- Do not assume every person has separable given/family names.
- Do not assume legal-name identity is required for valid attribution.

## 2. Organization

An organization is distinct from a person and may appear in several roles:

- publisher
- employer / affiliation
- collective byline
- sponsoring organization
- fediverse Group / publication account

Recommended fields:

- internal stable ID
- name
- URL
- description
- logo / image
- persistent identifiers
  - ROR
  - ISNI where relevant
- profile links
- organization type
  - publisher
  - newsroom
  - lab
  - institute
  - collective

## 3. Publication / Work

A publication is the authored thing the model is about.

Recommended fields:

- internal stable ID
- title
- subtitle
- work type
  - article
  - post
  - page
  - book chapter
  - review
  - dataset
  - report
  - other supported work types
- publication URL
- publication date(s)
- language
- summary / abstract
- publication hierarchy when relevant
  - publication / periodical / issue / volume / section
- persistent identifiers
  - DOI
  - ISBN
  - other external identifiers as needed
- publisher / container relationships

Important:

- The publication is not the same thing as the WordPress post object, even if a WordPress post is often its origin.
- The model should allow one WordPress-originated work to map cleanly into external scholarly/publication identity systems.

## 4. Contribution / Role

This should be a structured relationship, not just a string field.

A contribution links:

- a **Person** or **Organization**
- to a **Publication / Work**
- with a **Role**
- and an **Order / prominence**

Recommended fields:

- contributor entity reference
- work reference
- role type
  - author
  - co-author
  - editor
  - translator
  - illustrator
  - reviewer
  - commentator
  - staff
  - guest
  - bot
  - future extensions
- order / sequence
- primary / corresponding / lead flags where relevant
- attribution text override where needed
- source of assertion
  - authored
  - editorially assigned
  - imported / external

This is where the scholarly graph model is most useful.

Instead of:

- `post.authors = [ "Jane Doe", "Alex Roe" ]`

prefer:

- `Publication <- Contribution(order=1, role=author) <- Person`
- `Publication <- Contribution(order=2, role=author) <- Person`
- `Publication <- Contribution(role=editor) <- Person`

## 5. Identifier

Identifiers should be explicit, typed objects or typed slots, not opaque strings scattered across the model.

Recommended identifier categories:

- person identifiers
  - ORCID
  - ISNI
  - DID (future)
- organization identifiers
  - ROR
  - ISNI
- work identifiers
  - DOI
  - ISBN
  - internal canonical URL / URI

Each identifier should preserve:

- type
- value
- resolvable URL if applicable
- provenance/source
- verification status if known

## 6. Profile / verification link

The current docs make clear that declared identity links and verified identity links should not be conflated.

Recommended distinction:

- declared profile link
- social / platform profile link
- `rel="me"` style mutual-verification link
- fediverse actor URL
- future DID document / verification URI

This follows the repo's broader distinction between authored identity claims and stronger trust/verification models.

---

## Recommended core relationships

The canonical model should support at least these relationships:

- **Person → memberOf → Organization**
- **Organization → publishes → Publication**
- **Person/Organization → contributesTo → Publication**
- **Contribution → appliesRole → Publication**
- **Person/Organization/Publication → identifiedBy → Identifier**
- **Publication → cites → Publication** (future-ready)
- **Publication → isPartOf → Publication / container**
- **Person → sameAs / profileLink → external identity**

This is consistent with the exploratory graph docs and with the publishing metadata survey.

---

## What to borrow from Crossref, ORCID, and SPAR

## Crossref / DOI

Use Crossref-style thinking for:

- publication identity
- contributor lists
- container relationships
- citation graph potential

But do not make Crossref's API or schema the source of truth.

Crossref should be treated as:

- an external authority / enrichment source
- an interoperability target
- a useful publication-identifier model

not as the internal author data model.

## ORCID

ORCID is the strongest precedent for portable author identity.

The project should learn from ORCID that:

- a person needs a stable external identifier
- that identifier should survive platform changes
- the identifier is not the whole profile, but it anchors identity claims across systems

## SPAR / PRO / FaBiO

These ontologies matter because they model:

- bibliographic entities
- contributor roles
- publication structure
- citation relations

The most relevant lesson is conceptual, not implementation-specific:

- **role and contribution should be first-class relations**

---

## What not to make canonical

## COinS / OpenURL

COinS is useful only as a compatibility/export layer.

It is not a good canonical model because it is:

- flattened
- limited
- old resolver-oriented metadata
- weak for multi-author and relationship-rich identity

It should not drive the internal author identity architecture.

## CSL-JSON

CSL-JSON is useful for work/citation metadata interchange, especially in bibliographic tooling.

But for this project it should be treated as:

- a possible import/export representation for publication metadata
- not the canonical author identity graph

Why:

- it is optimized for citation rendering and reference metadata interchange
- it does not express the broader trust, role, verification, and cross-channel identity concerns central to this project

## Plain citation strings and legacy author strings

These are presentation or legacy-compatibility surfaces, not semantic identity models.

Examples:

- RSS `<dc:creator>` plain text
- RSS `<author>` email-style field
- comma-separated author bylines
- bibliography text strings

These may still need to be emitted or preserved, but they should always be derived from structured data.

---

## Recommended authored vs derived distinction

The vision doc already pushes in this direction. The canonical model should explicitly classify fields as:

- **Authored** — user-entered and authoritative
- **Derived** — system-generated convenience values
- **Composite** — user-entered with fallback behavior

This matters especially for:

- profile URLs
- avatars
- fediverse handles vs derived actor URLs
- external identifier links
- schema output

A derived fallback should never silently gain the same semantic weight as an authored identity claim.

---

## Output mapping principle

The internal model should be richer than any single output protocol.

Outputs should be adapters over the canonical model:

- **Byline feeds**
  - structured contributors, roles, perspective, profile links
- **JSON-LD / schema.org**
  - `Article.author` arrays, `Person`, `Organization`, `sameAs`, `worksFor`
- **fediverse metadata**
  - `fediverse:creator`, actor URLs, future AP integrations
- **legacy feed metadata**
  - `dc:creator`, `<author>`, Atom person constructs
- **future scholarly outputs**
  - ORCID-aligned person data
  - DOI-aligned publication data
  - citation-graph relationships

The source model should never be forced to flatten itself just because one output channel is weak.

---

## Minimal practical canonical shape

A practical near-term shape for implementation could look like:

```text
AuthorIdentityRecord
  people[]
  organizations[]
  publications[]
  contributions[]
  identifiers[]
  profileLinks[]
```

Or, per publication:

```text
PublicationRecord
  work
  contributors[]
    contributor
      entityRef
      role
      order
      attributionLabel?
  publisher?
  container?
  identifiers[]
  rights?
  provenance?
```

The exact storage shape can vary, but the semantics should stay the same.

---

## Near-term implications for the WordPress project

This recommendation does **not** mean the plugin should immediately become a full scholarly graph system.

It means the near-term implementation should avoid dead-end assumptions.

### Near-term requirements

- keep normalized multi-author arrays ordered
- distinguish people from organizations where possible
- keep roles structured
- reserve explicit identifier slots for ORCID / ROR / DOI even if not fully surfaced yet
- keep authored vs derived fields distinct
- make all current outputs adapters over the same normalized record

### Later opportunities

- richer publication models beyond post bylines
- organization affiliation modeling
- identifier verification states
- citation/publication graph features
- OpenAlex / Crossref / ORCID interoperability
- optional scholarly metadata exports

---

## Recommended decision

The Author Identity vision project should adopt this rule:

> **Canonical author identity data is a structured graph of people, organizations, works, contributions, and identifiers. All feed, schema, federation, and scholarly metadata outputs are derived adapter views over that graph.**

That is the cleanest way to incorporate what this repository has learned from:

- Crossref
- ORCID
- DOI-based scholarly infrastructure
- JSON-LD / schema.org
- feed standards
- WordPress multi-author reality

without letting any single output format dictate the whole architecture.
