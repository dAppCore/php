# Tests in this repository that cannot pass in this repository

110 of the Module suite's failures are not defects in `dappcore/php`. They are
tests that reference classes owned by other packages — classes this package
cannot depend on, because **every one of those packages already depends on this
one**.

```
dappcore/php-tenant  requires  dappcore/php: *
dappcore/agent       requires  dappcore/php: *
dappcore/service     requires  dappcore/php: *
```

Adding any of them to `composer.json` closes a loop. So these tests cannot be
made to pass where they are, by any amount of work inside this repository. They
need to move to the repository that owns what they test.

This document names them so their owners inherit findings rather than
archaeology. It is deliberately not a fix list for this repo.

## By owner

### `dappcore/php-tenant` — 82 failures, 13 files

Referencing `Core\Tenant\Models\User` (78) and `Core\Tenant\Rules\ResourceStatusRule` (4).
Both exist, in `php-tenant/Models/User.php` and `php-tenant/Rules/ResourceStatusRule.php`.

```
src/Core/Config/Tests/Feature/ConfigServiceTest.php
src/Core/Tests/Feature/DatabaseMigrationTest.php
src/Core/Tests/Feature/ValidationRulesTest.php
src/Core/Tests/Feature/PerformanceBaselineTest.php
src/Core/Tests/Feature/SecurityHeadersTest.php
src/Core/Tests/Feature/ErrorPagesTest.php
src/Core/Tests/Feature/SecurityFixesTest.php
src/Core/Tests/Feature/ImageOptimizerTest.php
src/Core/Tests/Feature/AdminRouteSmokeTest.php
src/Mod/Trees/Tests/Feature/SignupReferralTest.php
src/Mod/Trees/Tests/Feature/SubscriberMonthlyCommandTest.php
src/Mod/Trees/Tests/Feature/DailyLimitAndBonusTest.php
src/Mod/Trees/Tests/Feature/TreePlantingTest.php
```

Most of these want a `User` only to authenticate a request. Where that is all
they need, the cheaper fix than moving the file is a test user this package
owns — a fixture model, or Testbench's own — rather than the tenant package's.
Where the test genuinely exercises tenancy, it belongs in `php-tenant`.

### `dappcore/agent` — 25 failures, 1 file

```
src/Mod/Trees/Tests/Unit/AgentDetectionTest.php
```

It references `Core\Agentic\Services\AgentDetection` and
`Core\Agentic\Support\AgentIdentity`. **Both names are also stale**: the classes
exist in `dappcore/agent` under `Core\Mod\Agentic\Services` and
`Core\Mod\Agentic\Support`. So this file is wrong twice — a namespace the
ecosystem has moved away from, in a package that cannot be depended on from here.
Correcting the namespace alone would not make it pass.

### host.uk.com — 3 failures, 1 file

```
src/Core/Tests/Feature/MailConfigurationTest.php
```

References `Website\Host\Mail\ContactFormSubmission`, which lives in the
**application**, not in any package: `app/Website/Host/Mail/ContactFormSubmission.php`
in host.uk.com. A framework package testing its consumer's mailable. There is no
dependency that could make this work; the test belongs in the application.

## Why this is worth writing down

The Module suite reports one number, and one number invites one explanation.
These 110 have nothing wrong with them as tests — several look well written. They
are in the wrong repository, which is a different problem with a different owner
and a different fix, and it does not get solved by anyone driving a failure count
down inside this package.

The remaining Module-suite failures are ordinary: assertion mismatches, status
codes, a missing `offload:migrate` command registration. Those are this
repository's own and are being worked separately.
