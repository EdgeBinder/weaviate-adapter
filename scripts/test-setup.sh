#!/bin/bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
WEAVIATE_URL="http://localhost:8080"
WEAVIATE_TIMEOUT=60
COMPOSE_FILE="docker-compose.yml"

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

wait_for_weaviate() {
    log_info "Waiting for Weaviate to be ready..."
    local timeout=$WEAVIATE_TIMEOUT
    local count=0
    
    while [ $count -lt $timeout ]; do
        if curl -f -s "$WEAVIATE_URL/v1/meta" > /dev/null 2>&1; then
            log_info "Weaviate is ready!"
            return 0
        fi
        
        echo -n "."
        sleep 2
        count=$((count + 2))
    done
    
    log_error "Weaviate failed to start within $timeout seconds"
    return 1
}

start_weaviate() {
    log_info "Starting Weaviate..."
    docker-compose -f $COMPOSE_FILE up -d weaviate
    wait_for_weaviate
}

stop_weaviate() {
    log_info "Stopping Weaviate..."
    docker-compose -f $COMPOSE_FILE down
}

reset_weaviate() {
    log_info "Resetting Weaviate data..."
    docker-compose -f $COMPOSE_FILE down -v
    docker-compose -f $COMPOSE_FILE up -d weaviate
    wait_for_weaviate
}

run_tests() {
    local test_type=${1:-"all"}
    
    case $test_type in
        "unit")
            log_info "Running unit tests..."
            ./vendor/bin/phpunit tests/Unit
            ;;
        "integration")
            log_info "Running integration tests..."
            ./vendor/bin/phpunit tests/Integration
            ;;
        "all"|*)
            log_info "Running all tests..."
            ./vendor/bin/phpunit
            ;;
    esac
}

# Main script logic
case "${1:-help}" in
    "start")
        start_weaviate
        ;;
    "stop")
        stop_weaviate
        ;;
    "reset")
        reset_weaviate
        ;;
    "test")
        if ! curl -f -s "$WEAVIATE_URL/v1/meta" > /dev/null 2>&1; then
            start_weaviate
        fi
        run_tests "${2:-all}"
        ;;
    "help"|*)
        echo "Usage: $0 {start|stop|reset|test [unit|integration|all]}"
        echo ""
        echo "Commands:"
        echo "  start       Start Weaviate container"
        echo "  stop        Stop Weaviate container"
        echo "  reset       Reset Weaviate data and restart"
        echo "  test        Run tests (optionally specify unit, integration, or all)"
        echo ""
        echo "Examples:"
        echo "  $0 start"
        echo "  $0 test unit"
        echo "  $0 test integration"
        echo "  $0 reset"
        exit 1
        ;;
esac
