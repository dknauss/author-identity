# Playground

This directory holds reproducible WordPress Playground assets for `byline-feed`.

## Current target

- [output-demo](./output-demo/README.md)
  - primary stable demo target
  - showcases deterministic inspection routes for:
    - RSS2, Atom, and JSON Feed output
    - `fediverse:creator` and JSON-LD on a singular page
    - denied rights signaling via `robots` meta, `TDMRep`, and `ai.txt`
  - backs the public `Try in Playground` CTA through `output-demo.blueprint.json` on `main`, which pins the plugin to a release ZIP

## Later target

- adapter-demo
  - secondary later blueprint
  - should showcase real Co-Authors Plus and PublishPress Authors integration specifically
  - stays deferred so the public Playground experience does not depend on third-party plugin setup or adapter drift
