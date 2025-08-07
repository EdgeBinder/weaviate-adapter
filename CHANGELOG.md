# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Nothing yet

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

## [0.2.0] - 2025-01-08

### Added
- **MAJOR**: Full v0.5.0 Weaviate client support with complete query functionality
- **MAJOR**: Implemented `executeQuery()` method with v0.5.0 Filter API support
- **MAJOR**: Implemented `findByEntity()` for finding bindings involving specific entities
- **MAJOR**: Implemented `findBetweenEntities()` for finding bindings between two entities
- **MAJOR**: Implemented `count()` method for counting matching bindings
- Query conversion helpers for EdgeBinder to Weaviate format translation
- Callback mechanism for `BasicWeaviateQueryBuilder::get()` method execution
- Integration test for `count()` method functionality
- Support for complex filtering with `Filter::byProperty()`, `Filter::allOf()`, `Filter::anyOf()`
- Proper result handling with `returnProperties()` calls

### Changed
- **BREAKING**: Upgraded `zestic/weaviate-php-client` from v0.4.0 to `>=0.5.0 <1.0`
- **BREAKING**: Upgraded `edgebinder/edgebinder` to `>=0.4.0 <1.0` for better version constraints
- Replaced all "Phase 2 client enhancements" placeholder exceptions with working implementations
- Updated PHPStan configuration to remove deprecated options (`checkMissingIterableValueType`, `checkGenericClassInNonGenericObjectType`)
- Enhanced test coverage with comprehensive unit and integration tests for all query methods

### Removed
- Inappropriate `testDuplicateRegistrationThrowsException` test (registry behavior should be tested in core)
- All placeholder exceptions that blocked core EdgeBinder functionality

### Fixed
- **CRITICAL**: EdgeBinder relationship queries now fully functional (was completely broken)
- PHPStan warnings about deprecated configuration options
- Test isolation issues in `WeaviateAdapterQueryTest`
- Query result format compatibility with v0.5.0 client API

### Security
- Updated dependencies to latest secure versions

## [0.1.2] - 2024-12-XX

### Changed
- Use wildcard version constraints for flexible dependency management

## [0.1.1] - 2024-12-XX

### Changed
- Update zestic/weaviate-php-client to v0.3.0

## [0.1.0] - 2024-12-XX

### Added
- Initial release with basic project setup
- Complete CRUD operations implementation for WeaviateAdapter
- UUID mapping for Weaviate 1.31 compatibility
- Comprehensive unit and integration test coverage
- Exception hierarchy with WeaviateException and SchemaException
- Entity extraction and metadata validation utilities
- EdgeBinder registry system integration
- PHP 8.3+ support with PHPUnit 11 testing framework
- PHPStan static analysis (level 8) and PHP CS Fixer
- Docker Compose setup for Weaviate integration testing
- Comprehensive documentation and examples

### Changed
- Updated from StorageException to PersistenceException following core library changes
- Metadata stored as JSON strings for Weaviate 1.31 compatibility
- Schema creation uses proper Weaviate 1.31 format with vectorizer disabled for Phase 1

### Note
- Phase 1 implementation with placeholder methods for query functionality
- Query methods (`executeQuery`, `findByEntity`, `findBetweenEntities`, `count`) threw "Phase 2 client enhancements" exceptions
