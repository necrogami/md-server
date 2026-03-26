# md-server

A portable Markdown documentation server. Point it at a directory of `.md` files and get a browsable, styled documentation site with syntax highlighting, diagrams, and math rendering.

Ships as a single static binary — no PHP, Node, or dependencies required.

## Install

### Static binary (recommended)

Download the latest release for your platform from [Releases](https://github.com/necrogami/md-server/releases):

```bash
# Linux x86_64
curl -sL https://github.com/necrogami/md-server/releases/latest/download/md-server-linux-x86_64 -o md-server
chmod +x md-server
sudo mv md-server /usr/local/bin/

# macOS Apple Silicon
curl -sL https://github.com/necrogami/md-server/releases/latest/download/md-server-darwin-aarch64 -o md-server
chmod +x md-server
sudo mv md-server /usr/local/bin/
```

Available binaries: `linux-x86_64`, `linux-aarch64`, `darwin-x86_64`, `darwin-aarch64`, `windows-x86_64.exe`

### PHAR (requires PHP 8.4+)

```bash
curl -sL https://github.com/necrogami/md-server/releases/latest/download/md-server.phar -o md-server.phar
php md-server.phar serve
```

## Usage

```bash
# Serve the current directory
md-server serve

# Serve a specific directory
md-server serve --root=./docs

# Custom port and host
md-server serve --port=3000 --host=0.0.0.0

# Dark theme
md-server serve --theme=dark

# Disable sidebar navigation
md-server serve --no-tree
```

## Features

- **GitHub-style themes** — light and dark, with automatic OS preference detection
- **GFM support** — tables, task lists, strikethrough, autolinks
- **Syntax highlighting** — PHP, JavaScript, TypeScript, Python, Go, Rust, Java, Ruby, C/C++, Bash, SQL, YAML, JSON, Docker, and more (via Prism.js)
- **Mermaid diagrams** — flowcharts, sequence diagrams, etc. rendered client-side
- **KaTeX math** — block math (`$$...$$`) rendered with full LaTeX support
- **Sidebar navigation** — auto-generated file tree with collapsible directories
- **Frontmatter** — YAML frontmatter for page titles and metadata
- **Link rewriting** — internal `.md` links become clean URLs, external links open in new tabs
- **Path traversal protection** — secure file serving with `realpath` validation
- **Static file serving** — images, CSS, and other assets served from the docs root
- **Self-updating** — `md-server self-update` downloads the latest release

## Configuration

### Project config (`.mdrc`)

Create a `.mdrc` file in your docs root:

```yaml
server:
  host: 127.0.0.1
  port: 8080

theme:
  mode: auto  # light, dark, or auto

navigation:
  show_tree: true
  ignore:
    - vendor/
    - node_modules/
    - .git/
    - "*.log"

markdown:
  extensions:
    gfm: true
    frontmatter: true
    mermaid: true
    math: true
    syntax_highlight: true
```

### Custom themes

Place CSS files in a `.themes/` directory in your docs root to add custom themes to the theme switcher. Create a `.md-theme.css` file to add CSS overrides applied on top of any theme.

### Environment variables

| Variable | Description |
|----------|-------------|
| `MD_SERVER_ROOT` | Serving root directory |
| `MD_SERVER_THEME` | Theme mode (`light`, `dark`, `auto`) |
| `MD_SERVER_NO_TREE` | Set to `1` to disable sidebar |

## Self-update

```bash
# Check for updates
md-server self-update --check

# Update to latest version
md-server self-update

# With sudo if installed globally
sudo md-server self-update
```

The updater detects whether you're running a static binary or PHAR and downloads the correct artifact for your platform.

## Development

Requires PHP 8.4+.

```bash
git clone https://github.com/necrogami/md-server.git
cd md-server
composer install
php bin/md-server serve --root=tests/fixtures/docs
```

### Tests

```bash
vendor/bin/pest
```

### Build PHAR

```bash
curl -sL https://github.com/box-project/box/releases/latest/download/box.phar -o tools/box.phar
php -d phar.readonly=0 tools/box.phar compile
```

## License

MIT
