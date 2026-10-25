# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [5.0.0] - 2026-10-25

Requires MediaWiki 1.43 or newer and brings the extension up to date with upstream. MediaWiki 1.39 remains supported on the `4.x` line (`maint/4.x`).

### Breaking Changes
- Require MediaWiki 1.43 or newer; drop support for MediaWiki 1.39 [`2cbe97f`](https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/commit/2cbe97f)

### Changed
- Merge upstream changes, including updated translations and the new Scribunto hook handling [`2cbe97f`](https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/commit/2cbe97f)

### Fixed
- Stop purging all linking pages on every edit of a page that has no display title [`790a263`](https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/commit/790a263)

[Unreleased]: https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/compare/5.0.0...HEAD
[5.0.0]: https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/compare/4.2.0...5.0.0
[4.2.0]: https://github.com/gesinn-it-pub/mediawiki-extensions-DisplayTitle/releases/tag/4.2.0
