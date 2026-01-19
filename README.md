# harphp

A command-line tool for working with HAR (HTTP Archive) files.

## Features

- **cat** - Output HAR file contents, optionally filtered
- **requests** - List all requests with their indices
- **view** - View full details of a specific request

## Installation

```bash
composer install
chmod +x bin/harphp
```

## Usage

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

## License

MIT
