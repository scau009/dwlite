#!/bin/bash
set -e

# ========================================
# DWLite Deployment Script
# ========================================
# This script deploys the DWLite application to a server
# with zero-downtime and automatic rollback on failure.
#
# Usage:
#   ENVIRONMENT=staging TAG=abc123 bash deploy.sh
#
# Required Environment Variables:
#   - ENVIRONMENT: staging or production
#   - TAG: Docker image tag to deploy
#   - CCR_USERNAME: Tencent Cloud CCR username
#   - CCR_PASSWORD: Tencent Cloud CCR password
#
# Optional Environment Variables:
#   - REGISTRY: Docker registry URL (default: ccr.ccs.tencentyun.com/dwlite/)
#   - PROJECT_DIR: Project directory (default: /opt/dwlite)
#   - ENV_FILE_CONTENT: Content for .env file
# ========================================

# Configuration
ENVIRONMENT=${ENVIRONMENT:-staging}
TAG=${TAG:-latest}
REGISTRY=${REGISTRY:-ccr.ccs.tencentyun.com/dwlite/}
PROJECT_DIR=${PROJECT_DIR:-/opt/dwlite}
HEALTH_CHECK_URL=${HEALTH_CHECK_URL:-http://localhost:8000/health}
MAX_HEALTH_RETRIES=30
HEALTH_RETRY_INTERVAL=2

# Detect Docker Compose command (v1 vs v2)
if docker compose version > /dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_section() {
    echo ""
    echo "========================================="
    echo "$1"
    echo "========================================="
}

# Error handler
error_exit() {
    log_error "$1"
    exit 1
}

# Validate required environment variables
if [ -z "$CCR_USERNAME" ] || [ -z "$CCR_PASSWORD" ]; then
    error_exit "CCR_USERNAME and CCR_PASSWORD must be set"
fi

log_section "DWLite Deployment - $ENVIRONMENT"
log_info "Tag: $TAG"
log_info "Registry: $REGISTRY"
log_info "Project directory: $PROJECT_DIR"

# Create project directory if not exists
log_info "Ensuring project directory exists..."
mkdir -p "$PROJECT_DIR"
cd "$PROJECT_DIR"

# Login to Tencent CCR
log_section "Logging in to Tencent CCR"
echo "$CCR_PASSWORD" | docker login ccr.ccs.tencentyun.com -u "$CCR_USERNAME" --password-stdin || \
    error_exit "Failed to login to Tencent CCR"
log_info "Successfully logged in to CCR"

# Pull latest images
log_section "Pulling Docker Images"
log_info "Pulling backend image: ${REGISTRY}dwlite-backend:${TAG}"
docker pull "${REGISTRY}dwlite-backend:${TAG}" || error_exit "Failed to pull backend image"

log_info "Pulling frontend image: ${REGISTRY}dwlite-frontend:${TAG}"
docker pull "${REGISTRY}dwlite-frontend:${TAG}" || error_exit "Failed to pull frontend image"

log_info "Successfully pulled all images"

# Create/update .env file if content provided
if [ -n "$ENV_FILE_CONTENT" ]; then
    log_section "Updating Environment Configuration"
    # Decode from base64 if ENV_FILE_BASE64 is set, otherwise use content directly
    if [ -n "$ENV_FILE_BASE64" ]; then
        echo "$ENV_FILE_CONTENT" | base64 -d > .env
        log_info ".env file updated (from base64)"
    else
        echo "$ENV_FILE_CONTENT" > .env
        log_info ".env file updated"
    fi
else
    log_warn ".env file not provided, using existing configuration"
fi

# Ensure docker-compose.prod.yml exists
if [ ! -f "docker-compose.prod.yml" ]; then
    error_exit "docker-compose.prod.yml not found in $PROJECT_DIR"
fi

# Verify observability configs exist
if [ ! -f "observability/loki/loki-config.yaml" ]; then
    log_error "Observability configs not found!"
    log_error "Required files:"
    log_error "  - observability/loki/loki-config.yaml"
    log_error "  - observability/promtail/promtail-config.yaml"
    log_error "  - observability/tempo/tempo-config.yaml"
    log_error "  - observability/prometheus/prometheus.yml"
    log_error "Please ensure these files are copied to the server before deployment."
    error_exit "Missing observability configuration files"
fi

# Export environment variables for docker-compose
export REGISTRY
export TAG

# Get current running container IDs for rollback
log_section "Preparing Deployment"
log_info "Using Docker Compose command: $DOCKER_COMPOSE"
BACKEND_CURRENT=$($DOCKER_COMPOSE -f docker-compose.prod.yml ps -q backend 2>/dev/null || echo "")
FRONTEND_CURRENT=$($DOCKER_COMPOSE -f docker-compose.prod.yml ps -q frontend 2>/dev/null || echo "")

if [ -n "$BACKEND_CURRENT" ]; then
    log_info "Current backend container: $BACKEND_CURRENT"
    # Tag current images for potential rollback
    BACKEND_IMAGE=$(docker inspect --format='{{.Image}}' "$BACKEND_CURRENT" 2>/dev/null || echo "")
    if [ -n "$BACKEND_IMAGE" ]; then
        docker tag "$BACKEND_IMAGE" "${REGISTRY}dwlite-backend:rollback-${ENVIRONMENT}" || true
        log_info "Tagged current backend image for rollback"
    fi
fi

# Perform zero-downtime deployment
log_section "Starting Deployment"
log_info "Pulling images with docker compose..."
$DOCKER_COMPOSE -f docker-compose.prod.yml pull

log_info "Starting new containers..."
$DOCKER_COMPOSE -f docker-compose.prod.yml up -d --no-deps --remove-orphans

# Wait for services to start
log_info "Waiting for services to initialize..."
sleep 10

# Health check
log_section "Running Health Checks"
RETRY_COUNT=0
while [ $RETRY_COUNT -lt $MAX_HEALTH_RETRIES ]; do
    if curl -f "$HEALTH_CHECK_URL" > /dev/null 2>&1; then
        log_info "Backend is healthy!"
        break
    fi

    log_info "Waiting for backend to be ready... ($RETRY_COUNT/$MAX_HEALTH_RETRIES)"
    sleep $HEALTH_RETRY_INTERVAL
    RETRY_COUNT=$((RETRY_COUNT + 1))
done

if [ $RETRY_COUNT -eq $MAX_HEALTH_RETRIES ]; then
    log_section "Deployment Failed - Rolling Back"
    log_error "Backend health check failed after ${MAX_HEALTH_RETRIES} attempts!"

    # Show logs for debugging
    log_info "Backend logs:"
    $DOCKER_COMPOSE -f docker-compose.prod.yml logs --tail=50 backend

    # Rollback if we have a previous version
    if [ -n "$BACKEND_CURRENT" ]; then
        log_warn "Attempting to rollback to previous version..."
        if docker image inspect "${REGISTRY}dwlite-backend:rollback-${ENVIRONMENT}" > /dev/null 2>&1; then
            export TAG="rollback-${ENVIRONMENT}"
            $DOCKER_COMPOSE -f docker-compose.prod.yml up -d --no-deps backend
            log_info "Rolled back to previous version"
        fi
    fi

    error_exit "Deployment failed and rollback completed"
fi

# Clean up old images
log_section "Cleaning Up"
log_info "Removing dangling images..."
docker image prune -f || log_warn "Failed to prune images (non-fatal)"

# Remove rollback tags if deployment succeeded
docker rmi "${REGISTRY}dwlite-backend:rollback-${ENVIRONMENT}" 2>/dev/null || true
docker rmi "${REGISTRY}dwlite-frontend:rollback-${ENVIRONMENT}" 2>/dev/null || true

# Show deployment status
log_section "Deployment Summary"
$DOCKER_COMPOSE -f docker-compose.prod.yml ps

log_section "Deployment Completed Successfully"
log_info "Environment: $ENVIRONMENT"
log_info "Tag: $TAG"
log_info "All services are running and healthy!"

exit 0
