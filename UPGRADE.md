# Upgrade to 4.0

* Update API Platform to version 5, version 4 is no longer supported. If you use the split API
  Platform packages instead of `api-platform/core`, require `api-platform/test` for the
  `ApiPlatformTestCase`.
* Update doctrine/dbal to version 4.5 or newer.
* Replace `ApiPlatformTestCase::PROBLEM_CONTENT_TYPE` with
  `ApiPlatformTestCase::PROBLEM_MEDIA_TYPE`. `testOperation()` compares the `contentType` exactly,
  remove a `; charset=utf-8` suffix from expected JSON based content types.
* The `FormatBytesExtension` uses the `#[AsTwigFilter]` attribute, update `twig/twig` to >= 3.21.
  Its class is now `Vrok\SymfonyAddons\Twig\FormatBytesExtension`, update references to the old
  `Vrok\SymfonyAddons\Twig\Extension\FormatBytesExtension`. The bundle registers the extension,
  remove your own service definition for it if you have one: with the `twig.extension` tag it
  fails, because the class no longer implements Twig's `ExtensionInterface`.
* Replace the `#[ApiFilter]` attribute for the `SimpleSearchFilter`, `ContainsFilter` and
  `JsonExistsFilter` with `#[QueryParameter]`, see README.md. Requests to a parameter of such an
  `#[ApiFilter]` are no longer filtered: they return all records, without an error or a log entry.
  Check every entity that still uses the attribute with these filters.
* The filters lost their constructor arguments `managerRegistry`, `logger`, `nameConverter` and
  `properties`, only the `combination` of the `JsonExistsFilter` remains. API Platform sets the
  logger and converts the property names. Name the properties to search with the `properties` of
  the `#[QueryParameter]` instead of the `SimpleSearchFilter`'s argument:
  `new QueryParameter(filter: new SimpleSearchFilter(), properties: ['name', 'slug'])`. The
  `SimpleSearchFilter` also lost the `searchParameterName` argument, the key of the
  `#[QueryParameter]` names the search parameter.
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
* The `AutoSenderSubscriber` is removed. Remove its service definition and set the default sender
  via the mailer config instead. Like the subscriber, Symfony adds the header only to messages
  without a `From` address:
  ```yaml
  framework:
    mailer:
      headers:
        From: '%env(MAILER_SENDER)%'
  ```
* The `ResetLoggerSubscriber` is removed, remove its service definitions. Messenger's
  `ResetServicesListener` resets all services tagged with `kernel.reset` after each message,
  unless the worker runs with `--no-reset`: this flushes the `BufferHandler`s and renews the UID
  of the `UidProcessor`. The `UidProcessor` gets this tag only when it is autoconfigured, add the
  tag yourself if your service definition disables `autoconfigure`:
  ```yaml
  Monolog\Processor\UidProcessor:
    tags:
      - { name: monolog.processor, handler: logstash }
      - { name: kernel.reset, method: reset }
  ```

# Upgrade to 3.0

* Update all your dependencies to the latest versions
* Make sure all your `Vrok\Validator` attributes / calls use named arguments instead of an array of
  options.
* If you used multiple calls to `testOperation()` in a single test with `ApiPlatformTestCase` and
  want to refresh your database in between, you now have to call `static::bootKernel()` yourself.
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