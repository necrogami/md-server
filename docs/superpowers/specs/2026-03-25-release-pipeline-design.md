# md-server Release Pipeline Design

## Goal

Ship md-server as a PHAR and self-contained FrankenPHP static binaries for all supported platforms, with automated CI/CD via GitHub Actions triggered by semver tags.

## Distribution Formats

| Format | Usage | Platforms |
|--------|-------|-----------|
| PHAR | `php md-server.phar serve --root=.` | Any OS with PHP 8.2+ |
| FrankenPHP static binary | `MD_SERVER_ROOT=. ./md-server php-server` | linux-x86_64, linux-aarch64, macos-x86_64, macos-aarch64 |

Windows: PHAR only (FrankenPHP doesn't support Windows static builds yet).

## PHAR Build

- Tool: `humbug/box` (dev dependency)
- Config: existing `box.json`
- Output: `md-server.phar`
- Build: `composer install --no-dev` → `vendor/bin/box compile`

## FrankenPHP Static Binary

FrankenPHP bundles Caddy web server + PHP interpreter + app source into a single executable. The binary serves HTTP natively via Caddy — no `php -S` subprocess needed.

### Required App Structure

FrankenPHP expects a `public/` directory with a front controller:

- `public/index.php` — Symfony front controller (returns Kernel via symfony/runtime)
- `Caddyfile` — Routing config: root at `public/`, `php_server` directive
- `php.ini` — Production PHP settings (opcache, error reporting)

### Binary UX

```bash
# Serve docs (Caddy-powered HTTP)
MD_SERVER_ROOT=/path/to/docs ./md-server php-server

# Serve with HTTPS/HTTP2/HTTP3
MD_SERVER_ROOT=/path/to/docs ./md-server php-server --domain localhost

# Run CLI commands
./md-server php-cli bin/md-server list
```

### Build Process (Linux)

Uses Docker with `dunglas/frankenphp:static-builder-musl`:

```dockerfile
FROM --platform=linux/amd64 dunglas/frankenphp:static-builder-musl
WORKDIR /go/src/app/dist/app
COPY . .
WORKDIR /go/src/app/
RUN EMBED=dist/app/ ./build-static.sh
```

Extract: `docker cp <container>:/go/src/app/dist/frankenphp-linux-x86_64 md-server-linux-x86_64`

### Build Process (macOS)

Clone FrankenPHP repo, run `EMBED=/path/to/app ./build-static.sh` on macOS runner.

### PHP Extensions

Set via `PHP_EXTENSIONS` env var during build. Required: `mbstring`, `xml`, `dom`, `ctype`, `iconv`, `tokenizer`, `filter`, `phar`, `zlib`, `opcache`, `yaml`, `simplexml`.

## Versioning

- Semver: `vMAJOR.MINOR.PATCH`
- Initial release: `v0.1.0`
- Tags trigger release workflow

## GitHub Actions

### CI Workflow (`.github/workflows/ci.yml`)

- **Trigger:** push to any branch, pull requests
- **Job:** Run `composer install` → `vendor/bin/pest`
- **PHP versions:** 8.2, 8.3, 8.4

### Release Workflow (`.github/workflows/release.yml`)

- **Trigger:** push tag `v*.*.*`
- **Jobs:**
  1. `test` — Run full Pest suite
  2. `build-phar` — `composer install --no-dev`, `box compile`, upload `md-server.phar` as artifact
  3. `build-linux` — Matrix `[x86_64, aarch64]`, Docker-based FrankenPHP static build with `EMBED`, extract binary
  4. `build-macos` — Matrix `[x86_64, arm64]`, `build-static.sh` with `EMBED`, extract binary
  5. `release` — Wait for all build jobs, create GitHub Release, attach all artifacts

### Release Artifacts

| Artifact | Description |
|----------|-------------|
| `md-server.phar` | Cross-platform PHAR |
| `md-server-linux-x86_64` | Linux AMD64 static binary |
| `md-server-linux-aarch64` | Linux ARM64 static binary |
| `md-server-macos-x86_64` | macOS Intel static binary |
| `md-server-macos-aarch64` | macOS Apple Silicon static binary |

## Repository

- Remote: `git@github.com:necrogami/md-server.git`
- Branch: `main`
- Public repo, MIT license
