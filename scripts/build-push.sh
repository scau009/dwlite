#!/bin/bash
set -e

# Configuration
REGISTRY="${REGISTRY:-ccr.ccs.tencentyun.com/dwlite/}"  # e.g., "docker.io/username/" or "registry.cn-hangzhou.aliyuncs.com/namespace/"
TAG="${TAG:-latest}"
PLATFORM="${PLATFORM:-linux/amd64}"  # Target platform for server deployment
BASE_IMAGE="${BASE_IMAGE:-dwlite-php-base:latest}"
BUILD_BASE="${BUILD_BASE:-false}"  # Set to "true" to rebuild base image

echo "=== Building DWLite Images ==="
echo "Registry: ${REGISTRY:-local}"
echo "Tag: $TAG"
echo "Platform: $PLATFORM"
echo "Base image: $BASE_IMAGE"

# Build base image if requested or doesn't exist
if [ "$BUILD_BASE" = "true" ]; then
    echo ""
    echo ">>> Building base image (this may take a while)..."
    docker buildx build --platform "$PLATFORM" -t "$BASE_IMAGE" -f ./backend/Dockerfile.base --load ./backend
fi

# Check if base image exists
if ! docker image inspect "$BASE_IMAGE" >/dev/null 2>&1; then
    echo ""
    echo ">>> Base image not found, building..."
    docker buildx build --platform "$PLATFORM" -t "$BASE_IMAGE" -f ./backend/Dockerfile.base --load ./backend
fi

# Build backend
echo ""
echo ">>> Building backend..."
docker buildx build --platform "$PLATFORM" --build-arg BASE_IMAGE="$BASE_IMAGE" -t "${REGISTRY}dwlite-backend:${TAG}" --load ./backend

# Build frontend
echo ""
echo ">>> Building frontend..."
docker buildx build --platform "$PLATFORM" -t "${REGISTRY}dwlite-frontend:${TAG}" --load ./frontend

# Push if registry is set
if [ -n "$REGISTRY" ]; then
    echo ""
    echo ">>> Pushing images to registry..."
    docker push "${REGISTRY}dwlite-backend:${TAG}"
    docker push "${REGISTRY}dwlite-frontend:${TAG}"
    echo ""
    echo "=== Images pushed successfully ==="
else
    echo ""
    echo "=== Images built locally (no registry set) ==="
    echo "To push, set REGISTRY env var:"
    echo "  REGISTRY=your-registry/ TAG=v1.0.0 ./scripts/build-push.sh"
fi

echo ""
echo "Images:"
echo "  - ${REGISTRY}dwlite-backend:${TAG}"
echo "  - ${REGISTRY}dwlite-frontend:${TAG}"
