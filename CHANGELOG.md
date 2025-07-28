# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial project setup with PHP 8.3+ support
- PHPUnit 11 testing framework configuration
- PHP CS Fixer for code style enforcement
- PHPStan for static analysis (level 8)
- GitHub workflows for testing and linting
- Docker Compose setup for Weaviate integration testing
- Basic project structure following EdgeBinder adapter patterns
- Integration with zestic/weaviate-php-client
- Comprehensive documentation and examples
- Complete CRUD operations implementation for WeaviateAdapter
- UUID mapping for Weaviate 1.31 compatibility
- Comprehensive unit and integration test coverage
- Exception hierarchy with WeaviateException and SchemaException
- Entity extraction and metadata validation utilities

### Changed
- Updated from StorageException to PersistenceException following core library changes
- Metadata stored as JSON strings for Weaviate 1.31 compatibility
- Schema creation uses proper Weaviate 1.31 format with vectorizer disabled for Phase 1

### Changed
- Nothing yet

### Deprecated
- Nothing yet

### Removed
- Nothing yet

### Fixed
- Nothing yet

### Security
- Nothing yet

## [0.1.0] - TBD

### Added
- Initial release with basic project setup
- Phase 1 implementation foundation
- Testing infrastructure
- Development tooling
