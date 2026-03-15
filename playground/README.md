# Playground

This directory holds reproducible WordPress Playground assets for `byline-feed`.

## Current target

- [output-demo](./output-demo/README.md)
  - primary stable demo target
  - showcases shipped outputs with deterministic injected author fixtures
  - now backs the public `Try in Playground` CTA through an immutable published blueprint
  - refreshed with `playground/bin/publish-output-demo.sh`

## Later target

- adapter-demo
  - secondary later blueprint
  - should showcase real Co-Authors Plus and PublishPress Authors integration specifically
  - stays deferred so the public Playground experience does not depend on third-party plugin setup or adapter drift
