# Changelog
All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.8.0] - TBD
### Added
* ApiPlatformTestCase now has constants for RFC 7807 problem responses
* ApiPlatformTestCase::testOperation now has a 'postFormAuth' option
* `FormDecoder` to decode 'application/x-www-form-urlencoded' requests

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
