# Upgrade to 4.0

* Update API Platform to version 5, version 4 is no longer supported. If you use the split API
  Platform packages instead of `api-platform/core`, require `api-platform/test` for the
  `ApiPlatformTestCase`.
* Replace `ApiPlatformTestCase::PROBLEM_CONTENT_TYPE` with
  `ApiPlatformTestCase::PROBLEM_MEDIA_TYPE`. `testOperation()` compares the `contentType` exactly,
  remove a `; charset=utf-8` suffix from expected JSON based content types.
* The `FormatBytesExtension` uses the `#[AsTwigFilter]` attribute, update `twig/twig` to >= 3.21.
  If you registered the extension yourself with the `twig.extension` tag, use `autoconfigure: true`
  instead.
* If you extended the internal `BaseApiTestCase`, extend `ApiPlatform\Test\ApiTestCase` instead.
* If you use the `JsonExistsFilter`: it now filters by multiple values (e.g.
  `?roles[]=ROLE_A&roles[]=ROLE_B`). Before, the filter ignored such requests and returned all
  records, now they return only the matching records. The filter no longer uses `JSON_CONTAINS_TEXT`
  but `JSON_CONTAINS_ALL_TEXT` (default, AND) or `JSON_CONTAINS_ANY_TEXT` (OR) of
  `vrok/doctrine-addons` >= 3.1, for single values too. Register the functions for the combinations
  you use, else all requests using the filter fail with an unknown DQL function:
  ```yaml
  doctrine:
    orm:
      dql:
        string_functions:
          JSON_CONTAINS_ALL_TEXT: Vrok\DoctrineAddons\ORM\Query\AST\JsonContainsAllTextFunction
          JSON_CONTAINS_ANY_TEXT: Vrok\DoctrineAddons\ORM\Query\AST\JsonContainsAnyTextFunction
  ```
* Multiple values for the `JsonExistsFilter` are combined with AND by default: a record must
  contain all values. To match records that contain at least one of them, set the `combination`
  argument to `JsonExistsFilter::OR`, see README.md.

# Upgrade to 3.0

* Update all your dependencies to the latest versions
* Make sure all your `Vrok\Validator` attributes / calls use named arguments instead of an array of
  options.
* If you used multiple calls to `testOperation()` in a single test with `ApiPlatformTestCase` and
  want to refresh your database inbetween, you now have to call `static::bootKernel()` yourself.
* Remove `skipRefresh` from your calls to `testOperation()`, this is now the default behavior and
  the option is no longer valid.
* `ApiPlatformTestCase` no longer uses the `AuthenticatedClientTrait`, if you need the
  functionality, include it in your test class directly.
* If you previously used an empty `files` array to get the "ContentType: multipart/form-data"
  header with `testOperation()` you now have to specify this header manually.
* Make sure you don't specify the `iri` and `uri` arguments together when calling
  `testOperation()`, or by setting the other in the `prepare` callback.
* Replace usages of the removed error constants from `ApiPlatformTestCase` with the new versions or
  your own values:
    * ERROR_RESPONSE
    * UNAUTHORIZED_RESPONSE
    * NOT_FOUND_RESPONSE
    * ACCESS_BLOCKED_RESPONSE
    * CONSTRAINT_VIOLATION_RESPONSE
* The `AuthenticatedClientTrait` was removed without replacement, use
  `ApiPlatformTestCase::testOperation` or your own implementation instead.