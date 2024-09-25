# Custom Rector Rules

This package contains a custom rector rule.

#### Installation
```bash
composer require jcergolj/custom-rector-rules
```

Add the following to the rector.php file
```php
    // find or add withRules method
    ->withRules([
        // other rules
        \Jcergolj\CustomRectorRules\Rector\Rules\FixMissingCoverClassAttributeRector::class,
    ])
```

#### Add #CoversClass and #CoversMethod Attribute for each Feature or Unit test class, if missing

If you follow the convection to store test in the same namespace as the class that is being tested e.g. `App/Http/Controllers/UserController ->
Tests/Features/Http/Controllers/UserControllerTest` this rule would add the #See attribute if missing referencing the App/Http/Controllers/UserController class.

Same rule applies for Unit tests.

My convection is to if there is a lot to test in CRUD controllers I split test according to methods e.g.
`App/Http/Controllers/UserController/CreateTest`, App/Http/Controllers/UserController/UpdateTest and so forth. In this case the #See attribute references
`App/Http/Controllers/UserController` class automatically.

```php
// before
<?php

namespace Tests\Feature\Http\Controllers\TeamController;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteTest extends TestCase
{
}

// after
<?php

namespace Tests\Feature\Http\Controllers\TeamController;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\App\Http\Controllers\TeamController::class)]
#[\PHPUnit\Framework\Attributes\CoversMethod(\App\Http\Controllers\TeamController::class, "Delete")]
class DeleteTest extends TestCase
{
}

```
