# md-server — Design Specification

A portable, self-contained Markdown server for developers. Drop a PHAR (or static binary) into a project, run it, and get a local web UI to browse and read markdown documentation.

## Requirements

- Serve markdown files from a configurable root directory
- Auto-generated sidebar tree navigation from folder structure
- In-document link following with breadcrumb trail
- CommonMark + GFM + frontmatter + Mermaid + math/LaTeX support
- 4-tier theming: built-in light/dark (GitHub-style), global shared themes, project override CSS, project theme directory
- 3-tier configuration: `~/.config/md-server/` → `.mdrc` → CLI flags
- Packaged as PHAR, optionally compiled to static binary via php-static-cli
- PHP 8.2+ / Symfony 7.x
- No caching by default — files are live-edited and users expect immediate changes

## Architecture

### Approach: Symfony Runtime + Micro-kernel

Uses `symfony/runtime` with `MicroKernelTrait`. Provides DI container, config system, and console commands with minimal wiring. The runtime component handles different execution contexts (dev server, PHAR, static binary) cleanly.

### Core Components

| Component | Responsibility |
|---|---|
| `Kernel` | Micro-kernel — registers routes, services, config |
| `ServeCommand` | Console command: `md-server serve [--port] [--root] [--host]` |
| `ConfigLoader` | 3-tier config cascade: global → `.mdrc` → CLI flags (each overrides the previous) |
| `MarkdownRenderer` | Parses MD → HTML using `league/commonmark` with GFM + extras |
| `FileTreeBuilder` | Scans root directory, builds navigation tree (respects ignores) |
| `ThemeResolver` | 4-tier theme resolution: built-in → global themes → `.md-theme.css` → `.themes/*.css` |
| `PageController` | Serves rendered markdown pages and static files from the doc root |
| `AssetController` | Serves built-in CSS/JS/vendor assets under the `/_md/` URL namespace |

### Key Libraries

- `league/commonmark` — CommonMark + GFM, frontmatter, extensible
- `symfony/http-kernel`, `symfony/routing`, `symfony/console`, `symfony/runtime`
- `symfony/process` — launching the built-in PHP server from `ServeCommand`
- `symfony/yaml` — `.mdrc` and config parsing
- `symfony/mime` — MIME type detection for static file serving (transitively available via `symfony/http-kernel`)
- `twig/twig` — page layout templates
- Client-side (vendored into PHAR): Prism.js (syntax highlighting), Mermaid.js (diagrams), KaTeX (math). All client-side libraries are bundled — no CDN dependencies. This keeps the tool fully self-contained but adds to PHAR size.

### Models

- **`Page`**: Value object holding `path` (relative), `title` (from frontmatter or filename), `rawMarkdown`, `renderedHtml`, `frontmatter` (array), `breadcrumbs` (array of path segments).
- **`TreeNode`**: Value object holding `name`, `path`, `type` (file/directory), `children` (array of `TreeNode`), `title` (from frontmatter or filename).
- **`Config`**: Immutable value object built from the merged 3-tier config result.

### Directory Structure

```
src/
  Kernel.php
  Command/
    ServeCommand.php
  Controller/
    PageController.php
    AssetController.php
  Service/
    ConfigLoader.php
    MarkdownRenderer.php
    FileTreeBuilder.php
    ThemeResolver.php
  Model/
    Page.php
    TreeNode.php
    Config.php
templates/
  layout.html.twig
  page.html.twig
  index.html.twig
  404.html.twig
assets/
  css/
    light.css
    dark.css
  js/
    app.js
  vendor/
    prism/
    mermaid/
    katex/
bin/
  md-server
config/
  defaults.yaml
router.php
```

## Configuration System

### 3-Tier Cascade

Later sources override earlier (deep-merged for maps, replaced for lists):

1. **Global**: `~/.config/md-server/config.yaml` (XDG on Linux, `~/Library/Application Support/` on macOS, `%APPDATA%` on Windows)
2. **Project**: `.mdrc` (YAML) in the resolved serving root directory (the value of `--root`, defaulting to CWD). Only the serving root is checked — there is no upward directory traversal.
3. **CLI flags**: `--port`, `--host`, `--root`, `--theme`, `--no-tree`, etc.

**Merge strategy**: Associative/map keys are deep-merged (project overrides specific keys from global). List values (e.g., `navigation.ignore`) are replaced entirely — a project `.mdrc` with `ignore: [build/]` replaces the default ignore list, it does not append to it.

### Config Shape

All fields optional. Sensible defaults applied.

```yaml
server:
  host: "127.0.0.1"
  port: 8080

markdown:
  extensions:
    gfm: true
    frontmatter: true
    mermaid: true
    math: true
    syntax_highlight: true

navigation:
  show_tree: true
  ignore:
    - vendor/
    - node_modules/
    - .git/
    - "*.log"

theme:
  mode: "auto"           # "light", "dark", or "auto" (follows OS preference)
  custom_file: null       # override path to a single CSS file
  custom_dir: null        # override path to themes directory
```

### Defaults

Serve on `127.0.0.1:8080`, auto theme (OS preference via `prefers-color-scheme`), GFM + all extras enabled, common junk directories ignored.

### Resolution Logic

`ConfigLoader` loads global config → deep-merges project `.mdrc` → overlays CLI flags. Produces an immutable `Config` value object.

## Markdown Rendering Pipeline

### Flow

```
.md file → frontmatter extraction → CommonMark parse → HTML
    → Twig template (layout + nav tree + rendered HTML) → Response
```

### Processing Stages

1. **Frontmatter extraction**: YAML metadata (title, description, tags) extracted via `league/commonmark` front-matter extension. `title` used in `<title>` and tree nav instead of filename.
2. **CommonMark + GFM parsing**: Tables, task lists, strikethrough, autolinks, fenced code blocks.
3. **Syntax highlighting**: Fenced code blocks get language CSS classes. Prism.js highlights client-side.
4. **Mermaid diagrams**: ` ```mermaid ` blocks rendered as `<pre class="mermaid">`. Mermaid.js renders client-side.
5. **Math/LaTeX**: Block `$$...$$` supported in MVP via a custom CommonMark extension that wraps content in KaTeX-compatible containers. Inline `$...$` is deferred post-MVP due to parsing ambiguity with currency/shell contexts. KaTeX renders client-side.
6. **Link rewriting**: Internal `.md` links rewritten to clean route URLs (e.g., `./other.md` → `/other`). External links left untouched, open in new tab.

### Static File Serving

Non-markdown files in the serving root (images, PDFs, etc.) referenced by markdown content are served by `PageController` with appropriate MIME types via `symfony/mime`. Falls back to `application/octet-stream` for unknown types. This allows `![diagram](./img/arch.png)` to work as expected.

### Raw HTML in Markdown

Raw HTML in markdown is rendered as-is (CommonMark default). Since this is a local-only dev tool serving the user's own files, HTML sanitization is not applied. If binding to non-localhost, users accept responsibility for content trust.

### Caching

Disabled by default. Files are read and rendered on each request. These files are likely edited while the server is live and users expect to see changes immediately. A browser refresh shows the latest content. Responses include `Cache-Control: no-store` headers. Caching may be added as an opt-in feature in the future.

## Navigation & File Tree

### FileTreeBuilder

- Recursively scans serving root, builds `TreeNode` tree
- Each node: `name`, `path`, `type` (file/directory), `children`, `title` (from frontmatter or filename)
- Title extraction: reads only the first 10 lines of each file to find YAML frontmatter `title` field. Falls back to filename if no frontmatter or no title.
- The tree is built once per request. For MVP this is acceptable — PHP's built-in server is single-threaded, and reading first-10-lines of each file is fast.
- Directories sorted before files, both alphabetical
- Ignore patterns from config applied
- Only `.md` files included in the tree

### Sidebar Tree

- Rendered as collapsible sidebar using `<details>/<summary>` — no JS framework required
- Currently viewed page highlighted, parent directories auto-expanded
- Passed to every page render via Twig

### Index Files

If a directory contains `index.md` or `README.md`, clicking the directory navigates to that file. Priority: `index.md` > `README.md`.

### Breadcrumbs

Path from root to current file, rendered above content. Each segment is a clickable link.

### URL Scheme

Clean URLs: `/path/to/file` maps to `<root>/path/to/file.md`. Root URL `/` serves `index.md` or `README.md` from root. If neither exists, a generated index page is rendered using `index.html.twig` — listing top-level `.md` files (with titles) and subdirectories, with the sidebar tree as primary navigation.

### Asset URL Namespace

All server-internal assets are served under the `/_md/` URL prefix to avoid collisions with doc-root paths. Routes:

- `/_md/css/<theme>.css` — built-in and resolved theme stylesheets
- `/_md/js/app.js` — application JavaScript
- `/_md/vendor/prism/*` — Prism.js assets
- `/_md/vendor/mermaid/*` — Mermaid.js assets
- `/_md/vendor/katex/*` — KaTeX assets
- `/_md/theme/<name>.css` — resolved user theme files (global or project)

Twig templates reference these paths for `<link>` and `<script>` tags. `AssetController` handles all `/_md/*` routes.

## Theming System

### 4-Tier Resolution

The theme system has two concepts: **selectable themes** (Tiers 1, 2, 4) and **override layers** (Tier 3). Selectable themes are mutually exclusive — only one base theme is active at a time. The override layer applies on top of whichever base theme is selected.

**Tier 1 — Built-in themes**: `light.css` and `dark.css` bundled in PHAR. Based on GitHub's markdown styling. Complete styling for typography, code blocks, tables, nav, layout. `theme.mode` config controls which is active. `auto` uses `prefers-color-scheme` media query to select between light and dark.

**Tier 2 — Global shared themes**: `~/.config/md-server/themes/*.css` (XDG on Linux, platform equivalent on macOS/Windows). Selectable themes shared across all projects. Available alongside built-in themes in the theme switcher. Useful for organization-wide or personal themes.

**Tier 3 — Single project override file**: `.md-theme.css` in serving root. Loaded *after* the active base theme (whether built-in, global, or project). This is a CSS layer, not a selectable theme — it always applies. For tweaking variables/rules without full replacement.

**Tier 4 — Project theme directory**: `.themes/` in serving root. Each `.css` file becomes a selectable theme. Project themes take precedence over global themes with the same name. Theme selection is persisted client-side via `localStorage`; JS swaps the stylesheet link on page load.

### Theme Switcher Behavior

When only built-in themes are available (no Tier 2 or Tier 4 themes), a simple dark/light toggle is shown in the header.

When custom themes exist (Tier 2 or Tier 4), the toggle is replaced by a theme switcher dropdown listing all available themes: built-in light, built-in dark, plus all global and project themes. The last-selected theme is persisted in `localStorage`. On first visit, the `auto` mode selects the built-in theme matching OS preference.

### CSS Architecture

Built-in themes use CSS custom properties for colors, spacing, fonts. Tier 3 overrides are trivial:

```css
/* .md-theme.css */
:root {
  --md-primary: #2563eb;
  --md-font-body: "Inter", sans-serif;
  --md-bg: #fafafa;
}
```

## Server Launch Mechanism

### PHAR Mode

`ServeCommand` uses `symfony/process` to launch `php -S <host>:<port> <router>` as a child process. The router path uses the `phar://` stream wrapper: `phar:///path/to/md-server.phar/router.php`. PHP's built-in server supports `phar://` paths as router scripts. The command holds the process open and forwards SIGINT for clean shutdown.

### Static Binary Mode

When compiled via php-static-cli, the resulting binary is a full PHP CLI interpreter with the PHAR embedded. It can re-execute itself as a server: `<self> -S <host>:<port> <router>`. The `ServeCommand` uses `PHP_BINARY` constant to find its own path (portable across platforms). It detects static binary mode via presence/absence of the `phar://` wrapper and adjusts the launch command accordingly.

### Router Script

`router.php` at project root (bundled into PHAR) acts as the front controller for PHP's built-in server. All requests are routed through the Symfony kernel. The kernel handles both markdown page rendering and static file serving (doc-root images via `PageController`, internal assets via `AssetController`).

### Process Management

`ServeCommand` registers a shutdown handler for clean process management:
- On SIGINT/SIGTERM: sends SIGTERM to the child PHP server process and exits cleanly.
- If the child process exits unexpectedly (e.g., port already in use): prints the error output from stderr and exits with a non-zero code. Port-in-use errors are detected by checking the child process exit code/stderr within the first second of startup.
- On Windows: `symfony/process` handles platform signal differences.

## Error Handling

- **Invalid frontmatter**: Malformed YAML in frontmatter is ignored — the file renders as plain markdown with the frontmatter block visible as text.
- **Broken internal links**: Links to non-existent `.md` files render as normal links. Clicking them returns a 404 page with a back link.
- **Missing/unreadable root**: `ServeCommand` exits immediately with a clear error message if the root path doesn't exist or isn't readable.
- **Permission errors**: Files that can't be read are excluded from the tree and return 404 if accessed directly.
- **HTTP responses**: Pages return `200` with `Content-Type: text/html; charset=utf-8`. Missing pages return `404`. Static files return appropriate MIME types.
- **Favicon/robots**: `/favicon.ico` returns a built-in icon (or 204 No Content). `/robots.txt` is not served (returns 404).

## Security

- **Path traversal protection**: All requested paths are resolved to their real path and verified to be within the serving root. Requests outside the root return 404.
- **Symlinks**: Symlinks are followed only if their resolved target is within the serving root. Symlinks pointing outside the root are ignored (excluded from tree, 404 if accessed).
- **No directory listing**: Directories without `index.md` or `README.md` show the generated index page, not a raw file listing.
- **Local-only by default**: Server binds to `127.0.0.1` by default. Binding to `0.0.0.0` requires explicit `--host` flag.

## PHAR Packaging & Distribution

### Build Tool

`humbug/box` via `box.json` config. Bundles `src/`, `templates/`, `assets/`, `config/`, `vendor/`, `router.php`, `bin/md-server`. Excludes dev dependencies, tests, docs.

### Build Pipeline

```
composer install --no-dev
box compile → md-server.phar
php-static-cli → md-server (single binary)
```

### Usage

```bash
# As PHAR
php md-server.phar serve --root=./docs --port=3000

# As static binary
./md-server serve --root=./docs

# From project root (defaults to current directory)
./md-server serve
```

## Out of Scope (MVP)

- Search (full-text or otherwise)
- Server-side caching
- File watching / live reload
- Authentication / access control
- PDF export
- Edit-in-browser
- Inline `$...$` math (block `$$...$$` only in MVP)
