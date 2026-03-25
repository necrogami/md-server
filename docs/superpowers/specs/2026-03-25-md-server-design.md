# md-server — Design Specification

A portable, self-contained Markdown server for developers. Drop a PHAR (or static binary) into a project, run it, and get a local web UI to browse and read markdown documentation.

## Requirements

- Serve markdown files from a configurable root directory
- Auto-generated sidebar tree navigation from folder structure
- In-document link following with breadcrumb trail
- CommonMark + GFM + frontmatter + Mermaid + math/LaTeX support
- 3-tier theming: built-in light/dark (GitHub-style) → `.md-theme.css` → `.themes/` directory
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
| `ConfigLoader` | 3-tier config cascade: CLI flags → `.mdrc` → `~/.config/md-server/config.yaml` |
| `MarkdownRenderer` | Parses MD → HTML using `league/commonmark` with GFM + extras |
| `FileTreeBuilder` | Scans root directory, builds navigation tree (respects ignores) |
| `ThemeResolver` | 3-tier theme resolution: built-in → `.md-theme.css` → `.themes/*.css` |
| `PageController` | Single controller — serves rendered markdown pages |
| `AssetController` | Serves built-in CSS/JS assets and user theme files |

### Key Libraries

- `league/commonmark` — CommonMark + GFM, frontmatter, extensible
- `symfony/http-kernel`, `symfony/routing`, `symfony/console`, `symfony/runtime`
- `symfony/yaml` — `.mdrc` and config parsing
- `twig/twig` — page layout templates
- Client-side: Prism.js (syntax highlighting), Mermaid.js (diagrams), KaTeX (math)

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
  404.html.twig
assets/
  css/
    light.css
    dark.css
  js/
    app.js
bin/
  md-server
config/
  defaults.yaml
```

## Configuration System

### 3-Tier Cascade

Later sources override earlier (merged, not replaced):

1. **Global**: `~/.config/md-server/config.yaml` (XDG on Linux, `~/Library/Application Support/` on macOS, `%APPDATA%` on Windows)
2. **Project**: `.mdrc` (YAML) in the serving root directory
3. **CLI flags**: `--port`, `--host`, `--root`, `--theme`, `--no-tree`, etc.

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
  mode: "light"          # "light", "dark", or "auto" (follows OS preference)
  custom_file: null       # override path to a single CSS file
  custom_dir: null        # override path to themes directory
```

### Defaults

Serve on `127.0.0.1:8080`, auto theme (OS preference), GFM + all extras enabled, common junk directories ignored.

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
5. **Math/LaTeX**: Inline `$...$` and block `$$...$$` wrapped in KaTeX-compatible containers via custom CommonMark extension. KaTeX renders client-side.
6. **Link rewriting**: Internal `.md` links rewritten to clean route URLs (e.g., `./other.md` → `/other`). External links left untouched, open in new tab.

### Caching

Disabled by default. Files are read and rendered on each request. These files are likely edited while the server is live and users expect to see changes immediately. Caching may be added as an opt-in feature in the future.

## Navigation & File Tree

### FileTreeBuilder

- Recursively scans serving root, builds `TreeNode` tree
- Each node: `name`, `path`, `type` (file/directory), `children`, `title` (from frontmatter)
- Directories sorted before files, both alphabetical
- Ignore patterns from config applied
- Only `.md` files included

### Sidebar Tree

- Rendered as collapsible sidebar using `<details>/<summary>` — no JS framework required
- Currently viewed page highlighted, parent directories auto-expanded
- Passed to every page render via Twig

### Index Files

If a directory contains `index.md` or `README.md`, clicking the directory navigates to that file. Priority: `index.md` > `README.md`.

### Breadcrumbs

Path from root to current file, rendered above content. Each segment is a clickable link.

### URL Scheme

Clean URLs: `/path/to/file` maps to `<root>/path/to/file.md`. Root URL `/` serves `index.md` or `README.md` from root, or a generated index page if neither exists.

## Theming System

### 3-Tier Resolution

**Tier 1 — Built-in themes**: `light.css` and `dark.css` bundled in PHAR. Based on GitHub's markdown styling. Complete styling for typography, code blocks, tables, nav, layout. `theme.mode` config controls active theme. `auto` uses `prefers-color-scheme` media query.

**Tier 2 — Single override file**: `.md-theme.css` in serving root, loaded after built-in theme. For tweaking variables/rules without full replacement.

**Tier 3 — Theme directory**: `.themes/` in serving root. Each `.css` file becomes a selectable theme. Theme switcher dropdown appears in UI header. Selected theme replaces built-in theme (full replacement). `.md-theme.css` still layers on top if present.

### CSS Architecture

Built-in themes use CSS custom properties for colors, spacing, fonts. Tier 2 overrides are trivial:

```css
/* .md-theme.css */
:root {
  --md-primary: #2563eb;
  --md-font-body: "Inter", sans-serif;
  --md-bg: #fafafa;
}
```

### Dark/Light Toggle

Visible in header when using built-in themes or `auto` mode. Hidden when Tier 3 custom themes are active.

## PHAR Packaging & Distribution

### Build Tool

`humbug/box` via `box.json` config. Bundles `src/`, `templates/`, `assets/`, `config/`, `vendor/`, `bin/md-server`. Excludes dev dependencies, tests, docs.

### Entry Point

`bin/md-server` is the PHAR stub. `symfony/runtime` bootstraps the kernel and launches PHP's built-in dev server (`php -S`) pointed at a router script.

### Router Script

`router.php` bundled inside the PHAR acts as front controller for PHP's built-in server. Routes requests through the Symfony kernel.

### Static Binary

For `php-static-cli`, the PHAR is compiled into a self-contained binary with PHP embedded. No special code changes needed.

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
