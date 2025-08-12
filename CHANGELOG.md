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

## [0.4.1] - 2025-01-12

### Fixed
- Anonymous class entity handling in GraphQL queries - null bytes and special characters in anonymous class names were causing "Invalid character escape sequence" errors
- Added sanitization for anonymous class names to make them GraphQL-safe while preserving uniqueness and test compatibility

### Added
- Comprehensive unit tests for entity type extraction methods
- `sanitizeAnonymousClassName()` method for handling problematic characters in anonymous class names

### Changed
- Updated EdgeBinder dependency to >=0.7.1 to support new anonymous class test suite

## [0.4.0] - 2025-01-12

### Added
- **MAJOR**: EdgeBinder v0.7.0 compatibility with type-safe AdapterConfiguration
- Support for configurable WeaviateClient service names via `weaviate_client` configuration key
- Framework-agnostic dependency injection with proper container integration
- Enhanced separation of concerns in adapter factory pattern

### Changed
- **BREAKING**: Updated WeaviateAdapterFactory to use `AdapterConfiguration` class instead of arrays
- **BREAKING**: Factory now requires EdgeBinder v0.7.0+ for new configuration system
- **BREAKING**: WeaviateClient must now be registered in container - factory no longer creates clients directly
- Improved method signature: `createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface`
- Enhanced framework integration allowing custom service names (e.g., 'weaviate.client.rag', 'weaviate.client.custom')

### Fixed
- **CRITICAL**: Fixed separation of concerns violation where factory was creating WeaviateClient instances
- Fixed PHPStan static analysis errors in test suite
- Removed obsolete tests that violated type safety principles
- Updated all 12 factory tests to use new AdapterConfiguration format

### Enhanced
- **Type Safety**: Full type safety with AdapterConfiguration class replacing magic array keys
- **Framework Support**: Better integration with Laravel, Symfony, Laminas, and other PSR-11 frameworks
- **Code Quality**: PSR-12 compliant without underscore prefixes for private methods
- **Test Coverage**: All tests passing (12/12) with proper error handling scenarios

### Technical Details
- **Dependencies**: Updated to require EdgeBinder v0.7.0+
- **Static Analysis**: 0 PHPStan errors, full type compliance
- **Code Style**: 100% PHP-CS-Fixer compliant
- **Architecture**: Proper dependency injection following modern PHP patterns

## [0.3.0] - 2025-01-11

### Added
- **MAJOR**: Comprehensive unit test coverage for all previously untested components
- **QueryResultTest.php** (20 tests, 47 assertions) - Complete coverage of QueryResult readonly class
- **WeaviateExceptionTest.php** (23 tests, 65 assertions) - Complete coverage of WeaviateException hierarchy
- **SchemaExceptionTest.php** (22 tests, 69 assertions) - Complete coverage of SchemaException class
- **MetadataMapperTest.php** (20 tests, 53 assertions) - Comprehensive serialization/deserialization testing
- **WeaviateTransformer** class following EdgeBinder v0.6.0 transformer pattern
- Proper namespace organization for QueryResult class in Weaviate adapter
- Enhanced error handling with proper type safety and PHPStan compliance

### Changed
- **BREAKING**: Refactored WeaviateAdapter to follow InMemory adapter pattern for consistency
- **BREAKING**: Separated schema initialization from constructor - now requires explicit `initializeSchema()` call
- **BREAKING**: Updated QueryResult namespace from `EdgeBinder\Query` to `EdgeBinder\Adapter\Weaviate\Query`
- Improved adapter architecture following EdgeBinder v0.6.0 patterns
- Enhanced test isolation and reliability across all test suites
- Updated dependencies to require EdgeBinder v0.6.2+ and Weaviate client v0.5.0+

### Fixed
- **CRITICAL**: Fixed QueryResult namespace mismatch causing Codecov to report class as untested
- **CRITICAL**: Fixed unit test failures by properly separating concerns in adapter initialization
- Fixed code style violations (import ordering, trailing whitespace)
- Fixed PHPStan type safety issues in MetadataMapper tests
- Fixed test coverage attribution for all adapter components
- Resolved all CS-check failures in GitHub Actions

### Enhanced
- **Test Coverage**: Added 85 new tests with 234 new assertions
- **Code Quality**: Achieved 100% PHPStan compliance with 0 errors
- **Architecture**: Improved separation of concerns following EdgeBinder patterns
- **Documentation**: Enhanced inline documentation and error messages
- **Type Safety**: Comprehensive type checking and null-safety improvements

### Technical Details
- **Total Tests**: 199/199 passing (112 unit + 87 integration)
- **Total Assertions**: 501+ across all test suites
- **Code Coverage**: Significantly improved for critical components
- **Static Analysis**: 0 PHPStan errors, full type safety compliance
- **Code Style**: 100% compliant with project CS standards

## [0.2.1] - 2025-08-07

### Fixed
- **CRITICAL**: Fixed DateTimeImmutable null value error in BindingMapper that completely blocked relationship queries
- Added null-safety checks for timestamp parsing in `BindingMapper::fromWeaviateObject()` method
- Implemented robust `parseDateTime()` helper method with proper error handling for null, empty, malformed, and missing timestamps
- Fixed PHPStan static analysis issues: resource type safety and unused method cleanup
- All EdgeBinder relationship queries (`findProfileWorkspaces()`, `findOrganizationMembers()`, `isOrganizationMember()`, `isWorkspaceOwner()`) now work correctly

### Added
- Comprehensive test coverage for timestamp edge cases with 6 new test methods
- Graceful fallback to current timestamp when invalid data is encountered
- Better error handling for malformed timestamp strings from Weaviate

### Technical Details
- **Root Cause**: Weaviate was returning null values for `createdAt`/`updatedAt` fields, causing `TypeError` in `DateTimeImmutable` constructor
- **Solution**: Added null-safety with `parseDateTime()` helper that handles null, empty strings, and invalid date formats
- **Backward Compatibility**: Fully backward compatible with no API changes
- **Test Coverage**: Added tests for null timestamps, empty strings, malformed dates, and missing properties

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
