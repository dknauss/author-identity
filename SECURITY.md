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

## Current accepted development-tooling risk

As of March 13, 2026, the repository has two moderate GitHub Dependabot alerts for `webpack-dev-server` inherited through `@wordpress/scripts` in `byline-feed/package-lock.json`.

Relevant advisories:

- [GHSA-9jgg-88mc-972h](https://github.com/advisories/GHSA-9jgg-88mc-972h)
- [GHSA-4v9v-hfq4-rm2v](https://github.com/advisories/GHSA-4v9v-hfq4-rm2v)

Assessment:

- the affected package is development-only
- the shipped WordPress plugin does not include `webpack-dev-server`
- practical exposure exists when running `npm run start`, not when using the built plugin in production
- `@wordpress/scripts@31.6.0` is the current latest stable package and still depends on the vulnerable `webpack-dev-server` line

Current mitigation stance:

- prefer `npm run build` for normal verification and CI
- use `npm run start` only when actively working on the editor asset
- avoid treating these alerts as plugin-runtime release blockers
- track removal through either upstream `@wordpress/scripts` updates or a local toolchain replacement

Exit criteria:

1. `@wordpress/scripts` adopts a non-vulnerable `webpack-dev-server` version, or removes the dependency.
2. This repository replaces `@wordpress/scripts` with a smaller build stack that eliminates the vulnerable dependency path.
3. A tested local override proves safe and maintainable across the build workflow.
