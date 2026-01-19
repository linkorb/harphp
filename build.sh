#!/usr/bin/env bash
#
# Build script for harphp standalone executable
# Creates a self-contained binary with embedded PHP runtime using FrankenPHP
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Configuration
APP_NAME="harphp"
BUILD_DIR="build"
DIST_DIR="dist"

# Detect platform
case "$(uname -s)" in
    Linux*)     OS="linux";;
    Darwin*)    OS="mac";;
    *)          OS="linux";;
esac

case "$(uname -m)" in
    x86_64|amd64)   ARCH="x86_64";;
    arm64|aarch64)  ARCH="aarch64";;
    *)              ARCH="x86_64";;
esac

# Default to musl (fully static) for Linux, or gnu if specified
LIBC="${LIBC:-musl}"

# Allow overriding target platform
TARGET_OS="${TARGET_OS:-$OS}"
TARGET_ARCH="${TARGET_ARCH:-$ARCH}"

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           harphp - FrankenPHP Standalone Build               ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Target: ${TARGET_OS}-${TARGET_ARCH} (${LIBC})                          "
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Create dist directory
mkdir -p "$DIST_DIR"

# Determine the builder image
if [[ "$TARGET_OS" == "linux" ]]; then
    if [[ "$LIBC" == "gnu" ]]; then
        BUILDER_IMAGE="dunglas/frankenphp:static-builder-gnu"
    else
        BUILDER_IMAGE="dunglas/frankenphp:static-builder"
    fi
else
    # macOS builds use the default static builder
    BUILDER_IMAGE="dunglas/frankenphp:static-builder"
fi

# Determine Docker platform
if [[ "$TARGET_ARCH" == "aarch64" ]] || [[ "$TARGET_ARCH" == "arm64" ]]; then
    DOCKER_PLATFORM="linux/arm64"
else
    DOCKER_PLATFORM="linux/amd64"
fi

echo "→ Using builder image: $BUILDER_IMAGE"
echo "→ Docker platform: $DOCKER_PLATFORM"
echo ""

# Build the Docker image
echo "▸ Building static binary..."
docker build \
    --platform "$DOCKER_PLATFORM" \
    --build-arg BUILDER_IMAGE="$BUILDER_IMAGE" \
    -t "${APP_NAME}-builder" \
    -f "${BUILD_DIR}/Dockerfile" \
    .

# Determine the output binary name based on target
if [[ "$TARGET_OS" == "linux" ]]; then
    BINARY_NAME="frankenphp-linux-${TARGET_ARCH}"
else
    BINARY_NAME="frankenphp-mac-${TARGET_ARCH}"
fi

# Extract the binary from the container
echo "▸ Extracting binary..."
CONTAINER_ID=$(docker create "${APP_NAME}-builder")
docker cp "${CONTAINER_ID}:/go/src/app/dist/${BINARY_NAME}" "${DIST_DIR}/${APP_NAME}" 2>/dev/null || \
docker cp "${CONTAINER_ID}:/go/src/app/dist/frankenphp-linux-x86_64" "${DIST_DIR}/${APP_NAME}" 2>/dev/null || \
docker cp "${CONTAINER_ID}:/go/src/app/dist/frankenphp-linux-aarch64" "${DIST_DIR}/${APP_NAME}" 2>/dev/null || \
{
    echo "Error: Could not find built binary. Listing available files:"
    docker cp "${CONTAINER_ID}:/go/src/app/dist/" "${DIST_DIR}/debug-output" || true
    ls -la "${DIST_DIR}/debug-output" 2>/dev/null || true
    docker rm "$CONTAINER_ID" >/dev/null
    exit 1
}
docker rm "$CONTAINER_ID" >/dev/null

# Make it executable
chmod +x "${DIST_DIR}/${APP_NAME}"

# Get binary size
BINARY_SIZE=$(du -h "${DIST_DIR}/${APP_NAME}" | cut -f1)

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  ✓ Build complete!                                           ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Output: ${DIST_DIR}/${APP_NAME}                                       "
echo "║  Size:   ${BINARY_SIZE}                                            "
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "Usage:"
echo "  ./${DIST_DIR}/${APP_NAME} php-cli /app/bin/harphp <command> [args]"
echo ""
echo "Examples:"
echo "  ./${DIST_DIR}/${APP_NAME} php-cli /app/bin/harphp requests capture.har"
echo "  ./${DIST_DIR}/${APP_NAME} php-cli /app/bin/harphp view capture.har 0"
echo "  ./${DIST_DIR}/${APP_NAME} php-cli /app/bin/harphp cat capture.har -o filtered.har"
echo ""
