# harphp Makefile
# Convenience targets for building and development

.PHONY: help build build-gnu build-arm64 clean install dev

# Default target
help:
	@echo "harphp Build System"
	@echo ""
	@echo "Usage:"
	@echo "  make build        Build standalone binary (Linux musl, current arch)"
	@echo "  make build-gnu    Build with glibc (supports dynamic extensions)"
	@echo "  make build-arm64  Build for ARM64 architecture"
	@echo "  make build-all    Build for all platforms"
	@echo "  make compress     Compress binary with UPX"
	@echo "  make clean        Remove build artifacts"
	@echo "  make install      Install to /usr/local/bin"
	@echo "  make dev          Install dev dependencies"
	@echo ""

# Build standalone binary (fully static, musl)
build:
	@./build.sh

# Build with glibc (for dynamic extension loading)
build-gnu:
	@LIBC=gnu ./build.sh

# Build for ARM64
build-arm64:
	@TARGET_ARCH=aarch64 ./build.sh

# Build all variants
build-all:
	@echo "Building Linux x86_64 (musl)..."
	@TARGET_ARCH=x86_64 ./build.sh
	@mv dist/harphp dist/harphp-linux-x86_64-musl
	@echo "Building Linux aarch64 (musl)..."
	@TARGET_ARCH=aarch64 ./build.sh
	@mv dist/harphp dist/harphp-linux-aarch64-musl
	@echo "Building Linux x86_64 (gnu)..."
	@LIBC=gnu TARGET_ARCH=x86_64 ./build.sh
	@mv dist/harphp dist/harphp-linux-x86_64-gnu
	@echo ""
	@echo "All builds complete:"
	@ls -lh dist/

# Compress binary with UPX
compress:
	@if [ ! -f dist/harphp ]; then \
		echo "Error: dist/harphp not found. Run 'make build' first."; \
		exit 1; \
	fi
	@echo "Compressing with UPX..."
	@upx --best dist/harphp
	@echo "Compressed size:"
	@ls -lh dist/harphp

# Clean build artifacts
clean:
	@rm -rf dist/
	@docker rmi harphp-builder 2>/dev/null || true
	@echo "Cleaned build artifacts"

# Install to system
install: build
	@sudo cp dist/harphp /usr/local/bin/harphp
	@echo "Installed to /usr/local/bin/harphp"

# Development setup
dev:
	@composer install
	@echo "Development environment ready"
	@echo "Run: bin/harphp --help"
