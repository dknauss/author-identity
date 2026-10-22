# Byline Spec Alignment — Byline Feed 0.1.0

Decisions on every known difference between Byline Feed's output and the
[Byline specification](https://bylinespec.org) (namespace
`https://bylinespec.org/1.0`, spec changelog version 0.1.0, January 2026;
mirrored in [dknauss/byline](https://github.com/dknauss/byline)). Tracked in
[#71](https://github.com/dknauss/author-identity/issues/71).

Each item is marked:

- **Fixed** — changed in 0.1.0, with tests.
- **Accept** — a deliberate difference, kept and documented.
- **Defer** — not implemented in 0.1.0; planned for a later release.

Byline Feed 0.1.0 is a stable *plugin* release. It does not claim full
conformance to a stable Byline 1.0: the spec is itself pre-1.0, and the
accepted differences below are open questions for the spec author.

## Decisions

| # | Area | Spec | Byline Feed before 0.1.0 | Decision |
|---|------|------|--------------------------|----------|
| 1 | JSON Feed: feed `_byline` | `version` (namespace URI), `contributors[]` of Person objects | `spec_version: "1.0"`; persons only inside JSON Feed `authors[]._byline` | **Fixed.** Adds `version` and `contributors[]` with the spec's keys (`id`, `name`, `context`, `url`, `avatar`, `profiles`, `now`, `uses`). `spec_version` stays for rc consumers. |
| 2 | JSON Feed: item author | `_byline.author` (person id) and `_byline.role` | Authors only in JSON Feed `authors[]` with `_byline.id` / `_byline.role` | **Fixed.** Single-author items get `_byline.author` and `_byline.role` exactly as in the spec. `authors[]` is unchanged. |
| 3 | Multiple authors per item | One `author` per item | RSS2/Atom: one `<byline:author ref>` + `<byline:role>` pair per author, in byline order | **Accept.** Multi-author attribution is the reason the plugin exists. XML keeps the repeated pairs; JSON Feed uses `_byline.author` as an ordered array of ids for multi-author items, with roles per author in `authors[]._byline.role`. To raise with the spec author: an explicit multi-author binding (role as an attribute of `author`, or a wrapper element). |
| 4 | JSON Feed: author extension keys | `now`, `uses` on Person | `authors[]._byline.now_url`, `uses_url`; also `is_guest`, `fediverse` | **Accept.** `authors[]._byline` is the plugin's own extension on JSON Feed author objects and keeps its rc keys; the spec-shaped Person lives in `_byline.contributors[]` (item 1). |
| 5 | Feed organization | `<byline:org>` inside `<byline:organizations>`; JSON `organizations[]` with `id`, `type` | No XML org; JSON `_byline.org` with `name`, `url` only | **Defer** to 0.2.0, together with feed handling of the `organization` author mode, which needs an org to reference. |
| 6 | Affiliation | `<byline:affiliation>` with `org-ref`, `relationship`, `title` | Not emitted | **Defer.** Needs per-post editorial UI and an org model (item 5). |
| 7 | Theme | `<byline:theme>` on person and org | Not emitted | **Defer.** No WordPress source of truth yet; low reader value. |
| 8 | Inline author definitions | `<byline:author>` may wrap an inline `<byline:person>` | Always feed-level `<byline:contributors>` plus `ref` | **Accept.** References are valid per the spec, and guest authors are listed in `contributors` like anyone else. |
| 9 | Rights | Not in the spec | `<byline:rights>` in feeds; `_byline.rights` in JSON | **Accept** as a Byline Feed extension in the `byline` namespace, documented in the output reference. To propose upstream. |
| 10 | Terminology | `org` / organization | Prose mixed "organization", "publication", "publisher" | **Fixed** in docs. See below. |

## Terms

Used consistently across the plugin, its docs, and the readme:

- **Organization**: the entity a feed or post can be attributed to. In Byline
  output it is the spec's `org`; in JSON-LD it is the schema.org
  `Organization`. The `organization` author mode attributes a post to it.
- **Publisher**: the organization's *role* in JSON-LD (`Article.publisher`).
  Only used when talking about schema.org output.
- **Publication**: the site or feed as a body of work. Not an output entity;
  avoid it when an organization is meant.
- **Author**: a person attributed on a post, in byline order. **Contributor**
  is both a Byline role value and the feed-level list of persons
  (`byline:contributors`); say which is meant.

## Upstream

Items 3 and 9 go to the spec author as implementation feedback, with this
document and the [output reference](../../byline-feed/docs/output-reference.md)
as evidence. Item 5 depends on the spec's org model staying as written.
