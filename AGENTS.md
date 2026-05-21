<!-- THIS FILE IS AUTO-GENERATED. Edit AGENTS-source.adoc instead. -->

# Project Notes

## Extension overview

DisplayTitle is a MediaWiki extension that allows users to set a custom
display title for a page via `{{DISPLAYTITLE:…​}}` and to use that
display title in links and other contexts throughout the wiki.

This is a fork of the upstream
[wikimedia/mediawiki-extensions-DisplayTitle](https://github.com/wikimedia/mediawiki-extensions-DisplayTitle)
that adds a feature not present in upstream: **automatic purging of
incoming links** when a page’s display title changes.

### Fork-specific feature: purge incoming links on display-title change

When a page is saved and its display title has changed, a background job
(`DisplayTitlePurgeIncomingLinksJob`) is pushed to the MediaWiki job
queue. The job re-parses all pages that link to the edited page so that
their rendered display-title references stay up to date.

Implementation entry points:

- `DisplayTitleHooks::onParserAfterParse` — detects whether the display
  title changed (compares stored value with new parser output) and
  stores the result as extension data on the `ParserOutput`.

- `DisplayTitleHooks::onRevisionDataUpdates` — reads that flag after the
  revision is saved and pushes the job if needed.

- `DisplayTitlePurgeIncomingLinksJob` — resolves all incoming links and
  calls `WikiPage::doPurge()` on each of them.

### Branch / compatibility matrix

| Branch | MW compatibility | PHP      |
|--------|------------------|----------|
| `1.35` | MediaWiki 1.35   | PHP 7.4  |
| `main` | MediaWiki 1.39+  | PHP 8.1+ |

The `main` branch is the primary development branch and is the target
for all new work. The `1.35` branch receives only bug-fixes backported
from `main`.

### CI

CI uses the `docker-compose-ci` submodule (checked out under `build/`).
The Makefile delegates to `build/Makefile`. The relevant targets are:

``` console
make ci 2>&1 | tee /tmp/ci.log; echo "EXIT:$?"
make ci-coverage 2>&1 | tee /tmp/ci-coverage.log; echo "EXIT:$?"
```

Always pipe `make` through `tee` — never pipe directly into `grep`. Log
first, analyse after.

# Coding Conventions

**Coding Conventions — General**

All source files regardless of language must follow these baseline
rules.

- Encoding: UTF-8 without BOM

- Line endings: Unix-style LF (not CR+LF)

- Maximum line length: 120 characters

- No trailing whitespace

- Newline at end of file

**Coding Conventions — PHP**

Tooling:
[mediawiki-codesniffer](https://github.com/wikimedia/mediawiki-tools-codesniffer)
via PHPCS. Run locally: `make composer-phpcs` (or `make ci`).

**File structure**

- Every file starts with `declare( strict_types=1 );`

- No closing `?>` tag

- One class per file; filename matches class name (UpperCamelCase, e.g.
  `MyClass.php`)

- New code belongs in `src/` following PSR-4; `includes/` is legacy and
  should be migrated incrementally

**Namespaces and autoloading**

- PSR-4 via Composer (`autoload.psr-4` in `composer.json`)

- Top-level namespace = extension name (e.g.
  `MediaWiki\Extension\FooBar...`)

- Acronyms treated as single words: `HtmlId`, not `HTMLId`

**Naming**

| Element                     | Convention     | Example                |
|-----------------------------|----------------|------------------------|
| Classes, interfaces, traits | UpperCamelCase | `PageFormParser`       |
| Methods, variables          | lowerCamelCase | `getFormContent()`     |
| Constants                   | UPPER_CASE     | `MAX_FORM_SIZE`        |
| Global variables            | `$wg` prefix   | `$wgPageFormsSettings` |

**Type system**

- Use native type declarations on all parameters, properties, and return
  types

- PHPDoc only when native types are insufficient (e.g. `string[]`,
  `array<string, Foo>`)

- Nullable parameters: `?Type`, not `Type $x = null`

- Prefer `??` (null coalescing) and `??=` over ternary isset checks

- Use arrow functions `fn( $x ) ⇒ $x * 2` for single-expression closures

**Modern PHP features (target: PHP 8.1+)**

- Constructor property promotion

- `readonly` properties for immutable value objects

- `enum` instead of class constant groups

- `match()` instead of `switch` when returning a value

**Code style**

- Indentation: tabs, not spaces

- 1TBS brace style — opening brace on same line, `else`/`elseif` on
  closing brace line

- Always use braces, even for single-line blocks

- Spaces inside parentheses: `getFoo( $bar )`, empty: `getBar()`

- Spaces around binary operators: `$a = $b + $c`

- Single quotes preferred; double quotes for string interpolation

- `===` strict equality; `==` only when type coercion is intentional

- No Yoda conditions: `$a === 'foo'`, not `'foo' === $a`

- `elseif` not `else if`

- `true`, `false`, `null` always lowercase

**Architecture**

- `private` by default; `protected` only when subclass access is needed

- Dependency injection over direct instantiation — delegate `new Foo()`
  to factories

- Single Responsibility: one class, one concern

- No superglobals (`$_GET`, `$_POST`) — use `WebRequest` via
  `RequestContext`

- No new global functions — use static utility classes (`Html`, `IP`) if
  needed

- Order class members: `public` → `protected` → `private`

**Coding Conventions — JavaScript**

Tooling: [ESLint](https://eslint.org/) with
[eslint-config-wikimedia](https://github.com/wikimedia/eslint-config-wikimedia).
Run locally: `npm run lint:js` (or `make ci`).

**ESLint configuration**

Every repository must have a `.eslintrc.json` at root with
`"root": true`:

``` json
{
  "root": true,
  "extends": [
    "wikimedia/client/es2016",
    "wikimedia/jquery",
    "wikimedia/mediawiki"
  ],
  "env": { "commonjs": true }
}
```

**Module system**

- CommonJS modules: `require()` for imports, `module.exports` for
  exports

- Register modules with ResourceLoader; bundle name pattern:
  `ext.myExtension`

- JS class files match the class name exactly (`TitleWidget.js` for
  `TitleWidget`)

**Naming**

- Variables and methods: lowerCamelCase

- Constructors / classes: UpperCamelCase

- jQuery objects: `$`-prefix (`$button`, not `button`)

- Constants: `ALL_CAPS`

- Acronyms as single words: `getHtmlApiSource`, not `getHTMLAPISource`

**Code style**

- Tabs for indentation; single quotes for string literals

- `===` and `!==`; no Yoda conditions

- Spaces inside parentheses: `if ( foo )`, `getFoo( bar )`

- `const` and `let` — never `var` in new code

- Arrow functions for callbacks

**jQuery**

- Prefer ES6/DOM equivalents over deprecated jQuery methods (`.each` →
  `forEach`, etc.)

- Never search the full DOM with `$( '#id' )` or `$( '.selector' )`; use
  hook-provided `$content` and call `.find()` on it *(full-DOM queries
  match stale or foreign nodes, break hook-lifecycle isolation, and
  waste performance by traversing the entire document)*

- Prefer `$( '<div>' ).text( value )` over `$( '<div>text</div>' )` to
  avoid XSS

**MediaWiki APIs**

- Access configuration via `mw.config.get( 'wgFoo' )`, never direct
  globals

- Expose public API via `module.exports` or within the `mw` namespace
  (e.g. `mw.echo.Foo`)

- Use `mw.storage` / `mw.storage.session` for
  localStorage/sessionStorage

- Storage keys: `mw`-prefix + camelCase/hyphens (e.g.
  `mwedit-state-foo`)

**Coding Conventions — CSS / LESS**

Tooling: [stylelint](https://stylelint.io/) via `npm run lint:styles`
(or `make ci`). ResourceLoader natively compiles `.less` files; prefer
LESS over plain CSS.

**Naming**

- Classes and IDs: all-lowercase, hyphen-separated

- Use an extension-specific prefix to avoid conflicts (e.g. `pf-`,
  `smw-`, `mw-`)

- LESS mixin names: `mixin-` prefix + hyphen-case (e.g.
  `mixin-screen-reader-text`)

**Whitespace and formatting**

- One selector per line, one property per line

- Opening brace on the same line as the last selector

- Tab indentation for properties and nested rules

- Semicolon after every declaration, including the last

- Empty line between rule sets

**Colors**

- Lowercase hex shorthand preferred: `#fff`, `#252525`

- `rgba()` when alpha transparency is needed; `transparent` keyword
  otherwise

- No named color keywords (except `transparent`), no `rgb()`, `hsl()`,
  `hsla()`

- Ensure color contrast meets [WCAG 2.0
  AA](https://www.w3.org/TR/WCAG20/)

**LESS specifics**

- CSS custom properties (design tokens) preferred over LESS variables
  for new code

- `@import` only for mixins and variables (`variables.less`,
  `mixins.less`); do not use `@import` for bundling conceptually related
  files

- Omit `.less` extension in `@import` statements

- Bundle related files via the `styles` array in `skin.json` /
  `extension.json`

**Anti-patterns to avoid**

- `!important` — avoid except when overriding upstream code that also
  uses it

- `z-index` — use natural DOM stacking order where possible; document
  exceptions

- Inline `style` attributes — always use stylesheet classes instead

- `float` / `text-align: left` hardcoded — use `/* @noflip */`
  annotation when needed, otherwise ResourceLoader’s CSSJanus handles
  RTL automatically

# Test Workflow

**Test-first approach**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**Test environment setup**

All tests run inside a containerized MediaWiki environment managed via
[docker-compose-ci](https://github.com/gesinn-it-pub/docker-compose-ci)
(the `build/` submodule). Never run tests directly against a local PHP
or Node.js installation.

Always run `make install` before executing tests to ensure that the
latest file changes are copied into the container. Changes to source or
test files on the host are **not** automatically reflected in a running
container.

``` console
make install
```

**PHPUnit tests**

Run all PHPUnit tests:

``` console
make install composer-phpunit
```

Run a single test class or method (filtered):

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --filter YourTestName"
```

Run a specific test suite:

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --testsuite your-suite-name"
```

For interactive use, bash into the running container:

``` console
make bash
> composer phpunit -- --filter YourTestName
```

**Pre-commit validation gate**

Before every commit, run the full CI suite to confirm nothing is broken:

``` console
make ci
```

## Architecture Documentation

Architecture documentation lives in the `docs/architecture/` directory
of each repository. It follows an adapted, lightweight subset of the
[arc42](https://arc42.org) template, focused on what is most relevant
for MediaWiki extensions and similar projects.

### Directory Structure

    docs/architecture/
    ├── ARCHITECTURE-source.adoc      <-- source file; edit this only
    ├── ARCHITECTURE.adoc             <-- generated (auto); never edit manually
    ├── introduction-goals.adoc
    ├── context-scope.adoc
    ├── building-blocks.adoc
    ├── decisions.adoc
    └── glossary.adoc                 <-- optional

`ARCHITECTURE-source.adoc` ties all sections together via `include::`
directives. It is flattened by a GitHub Workflow into
`ARCHITECTURE.adoc` — a self-contained file without `include::`
directives that renders correctly on GitHub. `ARCHITECTURE.adoc` must
never be edited manually.

### Sections

We use the following subset of [arc42](https://arc42.org). Sections with
low value for typical MediaWiki extensions (Runtime View, Deployment
View, Risks, etc.) may be omitted.

| \#  | File                      | Purpose                                                                                                    |
|-----|---------------------------|------------------------------------------------------------------------------------------------------------|
| 1   | `introduction-goals.adoc` | Short system description. Top 3–5 quality goals. Key stakeholders and their expectations.                  |
| 2   | `context-scope.adoc`      | The system boundary. External systems, users, and interfaces (what is *outside* the box). Context diagram. |
| 3   | `building-blocks.adoc`    | Static internal structure: components, hooks, extension points, modules. Building block diagram.           |
| 4   | `decisions.adoc`          | Architectural Decision Records (ADRs) — important non-trivial decisions with rationale.                    |
| 5   | `glossary.adoc`           | Domain and technical terms used in the project.                                                            |

### Diagrams

UML tools are not required. Use **ASCII art** inside AsciiDoc literal
blocks (`…​.`). This renders correctly everywhere (GitHub, asciidoctor,
editors) without any additional tooling.

#### Context Diagram

Shows the system boundary: what is *inside* (the extension), what is
*outside* (users, MediaWiki core, databases, external services).

<div class="formalpara-title">

**Example: Context diagram**

</div>

``` asciidoc
.Context: PageForms Extension
....
  +----------------+          +------------------------------+          +---------------+
  |  Wiki Editor   |  form     |  MediaWiki Core              |          |  MySQL        |
  |  (Browser)     +---------> |                              +--------> |  (Database)   |
  +----------------+  submit   |  +------------------------+  |          +---------------+
                               |  |  PageForms Extension   |  |
  +----------------+  Special  |  |                        |  |
  |  Wiki Admin    +---------> |  |  Special Pages         |  |
  |  (Browser)     |  Pages    |  |  API (action=pfautoedit|  |
  +----------------+           |  |        pf_submit, ...) |  |
                               |  +------------------------+  |
  +----------------+  REST/    |                              |
  |  External App  +---------> |                              |
  |  / Bot         |  API      +------------------------------+
  +----------------+
....
```

#### Building Block View

Shows the static internal structure of the extension: its main
components and how they relate.

<div class="formalpara-title">

**Example: Building block diagram**

</div>

``` asciidoc
.Building Blocks: PageForms Extension
....
  +----------------------------------------------------------+
  |  PageForms Extension                                     |
  |                                                          |
  |  +------------------+    +----------------------------+  |
  |  |  Special Pages   |    |  Form Definition Parser    |  |
  |  |  (RunQuery,      |    |  (PF_FormPrinter,          |  |
  |  |   FormEdit, ...) |    |   PF_TemplateField, ...)   |  |
  |  +--------+---------+    +-------------+--------------+  |
  |           |                            |                  |
  |           v                            v                  |
  |  +------------------+    +----------------------------+  |
  |  |  Form HTML       |    |  Storage Layer             |  |
  |  |  Renderer        |    |  (Page save via MW API)    |  |
  |  +------------------+    +----------------------------+  |
  |                                                          |
  +----------------------------------------------------------+
....
```

#### Sequence / Flow Diagram

Shows a key runtime interaction between components or actors step by
step.

<div class="formalpara-title">

**Example: Form submission flow**

</div>

``` asciidoc
.Sequence: Form Submission
....
  Browser          Special:FormEdit      PageForms         MediaWiki API
     |                    |                  |                   |
     |  POST /FormEdit    |                  |                   |
     +------------------>+                  |                   |
     |                    | parseFormData()  |                   |
     |                    +---------------->+                   |
     |                    |                  | savePage()        |
     |                    |                  +----------------->+|
     |                    |                  |                   |
     |                    |                  |    page saved     |
     |                    |                  +<-----------------+|
     |                    |    redirect      |                   |
     |<-------------------+                  |                   |
....
```

### Architectural Decision Records (ADRs)

ADRs document important architectural decisions that are non-trivial,
costly to reverse, or affect multiple components. Trivial implementation
choices do not need an ADR.

Each ADR is a section in `04-decisions.adoc`, using the following
structure:

``` asciidoc
===== ADR-001: <Short title of the decision>

*Status:* accepted | proposed | deprecated | superseded by ADR-XXX

*Context:* +
Describe the situation and forces at play. Why was a decision needed?

*Decision:* +
State the decision that was made, clearly and directly.

*Consequences:* +
What becomes easier, harder, or must be accepted as a result?
```

===== ADR-001: Use asciidoctor-reducer to flatten README

**Status:** accepted

**Context:**  
The README was growing large and duplicating content that is centrally
maintained in `gesinn-it-docs-master-pub`. Direct `include::` directives
in a GitHub-rendered `.adoc` file are not resolved by GitHub.

**Decision:**  
Use `asciidoctor-reducer` in a GitHub Workflow to flatten
`README-source.adoc` (which uses `include::`) into a self-contained
`README.adoc` that renders correctly on GitHub.

**Consequences:**  
`README.adoc` must never be edited manually. All changes go into
`README-source.adoc`. The workflow runs automatically on every push that
touches the source file.

## Generated Documentation Files

Certain documentation files are **auto-generated** from corresponding
source files and **must never be edited manually**.

### Naming Convention

Source files use the suffix `-source` in their filename:

- `README-source.adoc` → generates `README.adoc`

- `.github/copilot-instructions-source.adoc` → generates
  `.github/copilot-instructions.md`

### Generation via GitHub Workflow

Generation is always performed by a **GitHub Workflow**
(`.github/workflows/`). The workflow is triggered automatically when the
source file changes.

<div class="warning">

Never edit generated files directly.  
Always modify the corresponding `*-source.adoc` file instead.  
The generated file will be overwritten by the next workflow run.

</div>

### Toolchain

Depending on the target format, the generation pipeline uses:

- [asciidoctor-reducer](https://github.com/asciidoctor/asciidoctor-reducer)
  — Flattens AsciiDoc `include::` directives into a single
  self-contained `.adoc` file

- [asciidoctor](https://asciidoctor.org/) +
  [pandoc](https://pandoc.org/) — Converts `.adoc` to Markdown (`.md`)
  via DocBook as intermediate format

### Source File Header

Source files should carry a comment block at the top to clearly identify
them as the authoritative source:

``` asciidoc
// ============================================================
// THIS IS THE SOURCE FILE. Edit this file only.
// <generated-file> is AUTO-GENERATED by the GitHub
// workflow .github/workflows/<workflow>.yml.
// NEVER edit or regenerate <generated-file> manually.
// ============================================================
```

# Commit Convention

# Conventional Commits Policy

Commit messages follow the [Conventional Commits
specification](https://www.conventionalcommits.org/).

Commit format:

`type(scope): short description`

The scope is optional and should describe the affected subsystem,
module, or dependency when useful.

Examples:

- feat(api): add autocomplete endpoint

- fix(parser): handle empty token lists

- docs(readme): explain input architecture

- refactor(parser): simplify token parsing

- deps(smw): bump from 5.1.0 to 5.2.0

- ci(github): update workflow configuration

- test(api): add autocomplete tests

Recommended commit types:

- `feat` — new functionality

- `fix` — bug fixes

- `deps` — dependency updates

- `docs` — documentation changes

- `refactor` — internal code changes without behavioral change

- `test` — tests added or updated

- `ci` — changes to continuous integration configuration

- `chore` — repository maintenance tasks without impact on runtime
  behavior

Dependency updates:

- Use the `deps` type for dependency upgrades

- The scope should identify the dependency being updated

- Include the version change when applicable

Example:

- deps(smw): bump from 5.1.0 to 5.2.0

Guidelines:

- Use the imperative mood (e.g. "add feature", not "added feature")

- Keep the subject line concise

- Use the commit body to explain **why**, not only **what**

- Scopes should be short, lowercase identifiers (e.g. `api`, `parser`,
  `smw`, `mediawiki`, `docker`)

- Use `chore` only for repository maintenance tasks that do not affect
  runtime behavior, dependencies, CI configuration, or tests

# Versioning and Releases

# Versioning and Releases

This project follows [Semantic Versioning](https://semver.org/).

Version numbers follow the format:

`MAJOR.MINOR.PATCH`

Version increment rules:

- MAJOR — incompatible or breaking changes

- MINOR — backwards-compatible feature additions

- PATCH — backwards-compatible bug fixes

Breaking changes include (but are not limited to):

- incompatible API changes

- removal or renaming of public interfaces

- behavior changes that may break existing integrations

- increased minimum runtime or dependency requirements

- incompatible configuration or data format changes

- dependency upgrades that introduce breaking changes for users

Breaking changes must always increment the MAJOR version.
