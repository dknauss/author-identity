# Security Policy

## Supported surface

This repository contains:

- documentation
- the `byline-feed` WordPress plugin
- development and CI tooling used to build and test the plugin

Security impact should be evaluated separately for:

- shipped plugin runtime behavior
- development-only tooling and local build workflows

## Reporting

For security issues in shipped plugin behavior, open a private security advisory or contact the maintainer through the repository's available security reporting channel.

For development-tooling advisories that do not affect shipped runtime behavior, open a normal GitHub issue unless private disclosure is warranted.

## Current development-tooling posture

As of March 15, 2026, the repository does not have open npm advisories in the committed `byline-feed/package-lock.json`.

Current posture:

- continue treating `npm run start` as development-only tooling, distinct from shipped plugin runtime behavior
- prefer `npm run build` for routine verification and CI
- review future npm advisories separately from shipped PHP runtime risk
- keep dependency overrides under review so they remain necessary, safe, and compatible with `@wordpress/scripts`
