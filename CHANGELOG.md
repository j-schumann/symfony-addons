# Changelog
All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.0] - 2025-11-03
### Changed
* Allow DoctrineBundle ^3

## [3.1.0] - 2025-07-21
### Added
* `RefreshDatabaseTrait` can now automatically create missing test database(s)
* `RefreshDatabaseTrait` now supports `dropDatabase` as DB_CLEANUP_METHOD, test
  in your setup if it is faster than `dropSchema`

## [3.0.0] - 2025-06-16
### Changed
* `ApiPlatformTestCase` no longer uses the `AuthenticatedClientTrait` but
  its own `getJWT` and allows to customize the User class via the static
  `$userClass`.

### Removed
* Removed support for PHP <= 8.2, Symfony <= 7.1, ApiPlatform <= 4.0, Doctrine
  DataFixtures <= 1, Doctrine Persistence <= 3, Doctrine FixturesBundle <= 3
* Giving options as array to the `NoHtml`, `NoLineBreaks`, `NoSurroundingWhitespace`
  and `PasswordStrength` constraints was removed, as this is deprecated in
  Symfony 7.3, use named arguments instead.
* If you previously used the `NoHtml` or `PasswordStrength` constraint by giving
  options not as array (first argument) but as non-named arguments, e.g.
  `new NoHtml(null, ['validationGrp'])` this will no longer work as the
  argument order changed by allowing `message` (and `minStrength`) as arguments.
* If you previously used the `NoLineBreaks` or `NoSurroundingWhitespace` 
  constraint with the `match` option to invert the behavior this will no longer
  work, it is now fixed to always match the constraint name.
* The ApiPlatformTestCase no longer reboots the kernel on each call to
  `testOperation()`, instead the kernel is only booted when required.  
  The `skipRefresh` option was removed as this is now the default behavior. If
  you still want to force a DB refresh between calls to `testOperation()` in a
  single test you have to call `static::bootKernel()` yourself.
* Old ApiPlatformTestCase constants with (Hydra) error responses from APIP < 3.2
  were removed.
* Because the `files` argument of `testOperation` now defaults to an empty array,
  the "ContentType: multipart/form-data" header is no longer added automatically.
* Specifying both `iri` and `uri` as arguments for  `testOperation` now throws
  an exception.
* `AuthenticatedClientTrait` was removed

## [2.16.0] - 2025-06-12
### Changed
* The `NoHtml` constraint now has a custom error code and sets the "{{ value }}"
  parameter.
* The `PasswordStrength` constraint now has a custom error code.
* The `NoHtml`, `NoLineBreaks` and `NoSurroundingWhitespace` constraints now
  support options as single (named) arguments instead of only as array.

### Deprecated
* Giving options as array to the `NoHtml`, `NoLineBreaks`, `NoSurroundingWhitespace`
  and `PasswordStrength` constraints will be removed in v3, as this is deprecated in
  Symfony 7.3, use named arguments instead.
* Using the `skipRefresh` option for `ApiPlatformTestCase::testOperation`, this
  will be the default behavior in 3.0 and the option will be removed.
* Using the old error constants for APIP < 3.2 in `ApiPlatformTestCase`, they
  will be removed in 3.0.

### Backwards incompatibility
* If you previously used the `NoHtml` or `PasswordStrength` constraint by giving
  options not as array (first argument) but as non-named arguments, e.g.
  `new NoHtml(null, ['validationGrp'])` this will no longer work as the
  argument order changed by allowing `message` (and `minStrength`) as arguments.
* If you previously used the `NoLineBreaks` or `NoSurroundingWhitespace`
  constraint with the `match` option to invert the behavior this will no longer
  work, it is now fixed to always match the constraint name.

## [2.15.0] - 2025-02-12
### Changed
* ApiPlatformTestCase now throws an error when unsupported parameters
  are supplied, to allow spotting skipped tests e.g. because of typos.

## [2.14.0] - 2024-12-12
### Added
* SimpleSearchFilter can search in relations

## [2.13.1] - 2024-10-16
### Fixed
* APIP error constant

## [2.13.0] - 2024-10-15
### Changed
* config loading
* Tests for PHPUnit 11

### Fixed
* deprecations in tests
* CS

### Removed
* support for Symfony <= 6
* support for Doctrine ORM <= 2

## [2.12.0] - 2024-07-11
### Added
* ApiPlatformTestCase::testOperation now has a 'dispatchedEvents' option

## [2.11.0] - 2024-06-15
### Added
* ArrayUtils::hasDuplicates

### Fixed
* ArrayUtils::mergeValues should keep equal arrays on different keys

## [2.10.0] - 2024-05-17
### Added
* ContainsFilter allows multiple values

## [2.9.0] - 2024-05-16
### Added
* ContainsFilter for filtering by JSON elements

## [2.8.1] - 2024-03-29
### Fixed
* compatibility w/ ApiPlatform >= 3.2.19

## [2.8.0] - 2024-01-30
### Added
* ApiPlatformTestCase now has constants for RFC 7807 problem responses
* ApiPlatformTestCase::testOperation now has a 'postFormAuth' option
* `FormDecoder` to decode 'application/x-www-form-urlencoded' requests
* Support Symfony 7

### Updated
* Setting the `message` for the `AtLeastOneOf` constraint to the empty string
  now behaves the same as `NULL` previously, meaning if the message is empty
  the last failing constraints message is returned.

### Removed
* Support for Symfony 6.2

## [2.7.0] - 2023-09-11
### Added
* `ArrayUtil` class with `mergeValues` to merge 2 or more arrays and keep
  all values instead of skipping/overwriting and not produce duplicates

## [2.6.0] - 2023-08-11
### Added
* ApiPlatformTestCase now supports file uploads
* ApiPlatformTestCase documentation in README

## Updated
* Unittests for ApiPlatformTestCase

## [2.5.0] - 2023-07-20
### Added
* `PropertyMarkingStore`, `WorkflowHelper` for use with Symfony workflows 
* `FormatBytesExtension` for Twig
* `MultipartDecoder` for file uploads (with ApiPlatform)

## Updated
* Bundle structure to new best practices

## [2.4.0] - 2023-06-17
### Fixed
* AtLeastOneOf validator with All() constraint
* SimpleSearchFilter without CAST, tested w/ MariaDB/Mysql/Postgres

### Updated
* Tests, CS
* database specific tests

### Removed
* Support for Symfony < 6.2

## [2.3.0] - 2023-06-12
### Added
- ApiPlatformTestCase: dispatched messages can be inspected with a callback
- QueryBuilderHelper: simplify parameter & join handling, e.g. for 
  ApiPlatform QueryExtensions

### Fixed
- SimpleSearchFilter: usage of deprecated option w/ ApiPlatform 3

## [2.2.0] - 2023-03-30
### Added
- ApiPlatformTestCase: assert number of sent emails, dispatched messages,
- ApiPlatformTestCase: checking for specific log entries & dispatched messages

### Fixed
- ApiPlatformTestCase: selection of the correct schema to compare to

## [2.1.0] - 2023-03-15
### Added
- AtLeastOneOf constraint supports a custom message if set
- ApiPlatformTestCase supports PATCH w/ merge content type
- ApiPlatformTestCase now has a `getIriFromResource` method

## [1.10.0] - 2023-03-15
### Added
- AtLeastOneOf constraint supports a custom message if set

## [1.9.1 + 2.0.1] - 2023-02-16
### Fixed
- NoSurroundingWhitespace failed with linebreaks within the string

### Changed
- NoSurroundingWhitespace now also prohibits leading/trailing linebreaks

## [2.0.0] - 2023-01-24
### Fixed
- misc. deprecations

### Added
- support for ApiPlatform 3
- tests for ApiPlatform Filters
- improved docs for filters, traits etc.

### Changed
- `assertArrayHasNestedKeys` renamed to `assertDatasetHasKeys`

### Removed
- support for `basicAuth` from `ApiPlatformTestCase::testOperation` params, use
  `['requestOptions']['auth_basic']` instead, it's the default/existing way
- support for PHP7 + 8.0 + 8.1 (e.g. Monolog ^3.0 requires ^8.1)
- support for Symfony 5
- support for ApiPlatform 2
- support for validator annotations, use attributes instead
- `NoTlsTransport`: Most servers require TLS, just disable certificate validation
  in tests with `?verify_peer=0` in the MAILER_DSN

## [1.9.0] - 2022-12-22
### Added
- allow BasicAuth in ApiPlatformTestCase

## [1.8.0] - 2022-11-24
### Added
- ApiPlatformTestCase

## [1.7.0] - 2022-06-09
### Added
- AtLeastOneOfValidator

## [1.6.0] - 2022-06-08
### Added
- NoSurroundingWhitespaceValidator
- JsonExistsFilter
- SimpleSearchFilter
- MonologAssertsTrait

### Updated
- NoTlsTransport compatibility with EsmtpTransport
- GH workflow, added PHP 8.2 (currently failing because of PHPUnit dependency)

## [1.5.2] - 2022-02-08
### Fixed
- RefreshDatabaseTrait: purge even without fixtures

## [1.5.1] - 2022-02-02
### Updated
- dependencies

### Fixed
- typesafety / deprecations

## [1.5.0] - 2022-01-31
### Updated
- support PHP 8.1 + Symfony 6

## [1.4.3] - 2021-07-29
### Fixed
- RefreshDatabaseTrait for PHP8

## [1.4.2] - 2021-07-29
### Updated
- support PHP8

## [1.4.1] - 2021-05-06
### Fixed
- CronMonthlyCommand not available

## [1.4.0] - 2021-05-05
### Added
- CronMonthlyEvent + command

## [1.3.1] - 2021-05-03
### Updated
- Test cleanup function
- dependencies

## [1.3.0] - 2020-12-17
### Added
- PasswordStrengthValidator

## [1.2.0] - 2020-12-16
### Added
- NoHtmlValidator + constraint
- NoLineBreaks constraint

## [1.1.4] - 2020-12-07
### Fixed
- usage of deprectated SF method 
