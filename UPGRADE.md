# Upgrade to 3.0

* Update all your dependencies to the latest versions
* Make sure all your `Vrok\Validator` attributes / calls use named arguments
  instead of an array of options.
* If you used multiple calls to `testOperation()` in a single test with 
  `ApiPlatformTestCase` and want to refresh your database inbetween, you now
  have to call `static::bootKernel()` yourself.
* Remove `skipRefresh` from your calls to `testOperation()`, this is now the
  default behavior and the option is no longer valid.
* `ApiPlatformTestCase` no longer uses the `AuthenticatedClientTrait`, if you
  need the functionality, include it in your test class directly.
* Replace usages of the removed error constants from `ApiPlatformTestCase` with
  the new versions or your own values:
    * ERROR_RESPONSE
    * UNAUTHORIZED_RESPONSE
    * NOT_FOUND_RESPONSE
    * ACCESS_BLOCKED_RESPONSE
    * CONSTRAINT_VIOLATION_RESPONSE
* The `AuthenticatedClientTrait` was removed without replacement, use
  `ApiPlatformTestCase::testOperation` or your own implementation instead.