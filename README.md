# harphp

A command-line tool for working with HAR (HTTP Archive) files.

## Features

- **cat** - Output HAR file contents, optionally filtered
- **requests** - List all requests with their indices
- **view** - View full details of a specific request

## Installation

### Pre-built Binary (Recommended)

Download a standalone executable from the [Releases](../../releases) page. No PHP installation required.

```bash
# Download the latest release (Linux x86_64)
curl -LO https://github.com/linkorb/harphp/releases/latest/download/harphp-linux-x86_64
chmod +x harphp-linux-x86_64

# Move to PATH (optional)
sudo mv harphp-linux-x86_64 /usr/local/bin/harphp
```

Available binaries:
| Platform | Binary | Description |
|----------|--------|-------------|
| Linux x86_64 | `harphp-linux-x86_64` | Fully static (musl), most portable |
| Linux ARM64 | `harphp-linux-aarch64` | Fully static (musl), for ARM64/aarch64 |

### From Source (requires PHP 8.1+)

```bash
composer install
chmod +x bin/harphp
```

## Usage

> **Note:** If using the standalone binary, replace `bin/harphp` with:
> ```bash
> ./harphp-linux-x86_64 php-cli /app/bin/harphp
> ```

### List requests

```bash
bin/harphp requests capture.har
```

Output:
```
 0 │ GET    │ 200 │    45.23ms │ https://example.com/
 1 │ GET    │ 200 │    12.10ms │ https://example.com/style.css
 2 │ POST   │ 201 │   120.50ms │ https://api.example.com/users
...
```

### View request details

```bash
# View request at index 0
bin/harphp view capture.har 0

# Output as JSON
bin/harphp view capture.har 0 --json

# Include request and response bodies
bin/harphp view capture.har 0 --request-body --response-body
```

### Filter and output HAR

```bash
# Output filtered HAR to stdout (reads harphp.yaml from cwd if present)
bin/harphp cat capture.har

# Output compact JSON
bin/harphp cat capture.har --compact

# Use a specific config file
bin/harphp cat capture.har --config=myconfig.yaml

# Save filtered HAR to file
bin/harphp cat capture.har -o filtered.har
```

## Configuration

### Config File (harphp.yaml)

By default, harphp looks for `harphp.yaml` in:
1. Current working directory
2. The directory containing `bin/harphp`

You can specify a different config file with `--config=path/to/config.yaml`.

Create a configuration file to include or exclude requests based on:

- **domains** - Match request host/domain
- **paths** - Match request URL path
- **extensions** - Match file extensions
- **urls** - Match full URL

Patterns support:

- **Glob patterns**: `*.jpg`, `api/*`, `**/images/**`
- **Regular expressions**: `/pattern/flags` or `#pattern#flags`

Example `harphp.yaml`:

```yaml
filters:
  ignore:
    domains:
      - "*.google-analytics.com"
      - "fonts.googleapis.com"
    
    paths:
      - "/analytics/*"
      - "*/pixel.gif"
    
    extensions:
      - jpg
      - png
      - gif
      - woff2
    
    urls:
      - "*utm_source=*"
      - "/.*\\/api\\/health\\/?$/i"
```

Whitelist mode (only include matching requests):

```yaml
filters:
  include:
    domains:
      - "api.example.com"
    paths:
      - "/api/**"
```

### Environment Configuration

You can also set the config file path via environment variable in `.env.local`:

```bash
HARPHP_FILTER=path/to/config.yaml
```

This is only used as a fallback if no `harphp.yaml` is found in cwd or base directory.

## Command Reference

### `harphp cat`

```
Usage:
  cat [options] <file>

Arguments:
  file                  Path to HAR file

Options:
  -c, --config=CONFIG   Path to config YAML file (default: harphp.yaml)
      --compact         Output compact JSON (default: pretty-printed)
  -o, --output=OUTPUT   Output file path (default: stdout)
```

### `harphp requests`

```
Usage:
  requests [options] <file>

Arguments:
  file                  Path to HAR file

Options:
  -c, --config=CONFIG   Path to config YAML file (default: harphp.yaml)
```

### `harphp view`

```
Usage:
  view [options] <file> <index>

Arguments:
  file                  Path to HAR file
  index                 Request index to view

Options:
  -c, --config=CONFIG   Path to config YAML file (default: harphp.yaml)
  -j, --json            Output raw JSON
      --request-body    Show request body
      --response-body   Show response body
```

## Building Standalone Executable

harphp can be compiled into a fully standalone executable using FrankenPHP. The resulting binary includes the PHP runtime and all dependencies, requiring no external PHP installation.

### Prerequisites

- Docker (for building)

### Build Commands

```bash
# Build for your current platform (Linux musl - fully static)
./build.sh

# Build with glibc (for dynamic extension support)
LIBC=gnu ./build.sh

# Cross-compile for different architectures
TARGET_ARCH=aarch64 ./build.sh   # ARM64
TARGET_ARCH=x86_64 ./build.sh    # x86_64
```

### Using the Standalone Binary

After building, the executable is available at `dist/harphp`:

```bash
# Run commands using the embedded PHP CLI
./dist/harphp php-cli /app/bin/harphp requests capture.har
./dist/harphp php-cli /app/bin/harphp view capture.har 0
./dist/harphp php-cli /app/bin/harphp cat capture.har -o filtered.har
```

### Build Options

| Environment Variable | Description | Default |
|---------------------|-------------|---------|
| `LIBC` | C library variant: `musl` (static) or `gnu` (dynamic) | `musl` |
| `TARGET_OS` | Target OS: `linux` or `mac` | Current OS |
| `TARGET_ARCH` | Target architecture: `x86_64` or `aarch64` | Current arch |

### Binary Size

The standalone binary is approximately 30-50MB depending on included extensions. For production deployment, you can further reduce size using UPX compression:

```bash
upx --best dist/harphp
```

## Contributing

This project uses [Conventional Commits](https://www.conventionalcommits.org/) for automatic versioning and changelog generation.

### Commit Message Format

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Commit Types

| Type | Description | Version Bump |
|------|-------------|--------------|
| `feat` | New feature | Minor (0.x.0) |
| `fix` | Bug fix | Patch (0.0.x) |
| `docs` | Documentation only | None |
| `style` | Code style (formatting) | None |
| `refactor` | Code refactoring | None |
| `perf` | Performance improvement | Patch |
| `test` | Adding tests | None |
| `chore` | Maintenance tasks | None |
| `ci` | CI/CD changes | None |

### Breaking Changes

Add `!` after the type or include `BREAKING CHANGE:` in the footer for major version bumps:

```
feat!: remove deprecated filter syntax

BREAKING CHANGE: The old filter syntax is no longer supported.
```

### Examples

```bash
# Feature (minor version bump)
git commit -m "feat: add JSON output format for requests command"

# Bug fix (patch version bump)
git commit -m "fix: handle empty HAR files gracefully"

# Breaking change (major version bump)
git commit -m "feat!: change default output format to compact JSON"
```

### Release Process

Releases are automated via GitHub Actions:

1. Push commits to `main` using conventional commit messages
2. Release Please creates/updates a release PR with changelog
3. Merge the release PR to trigger:
   - Version bump in `composer.json` and `src/Application.php`
   - Git tag creation
   - GitHub release with binaries attached

## License

MIT
