# Acme Conimex

Author: YourNameHere

This Bolt extension can be used as a starting point to base your own extensions on.

Installation:

```bash
composer require BobdenOtter/conimex
```

<!---
Add to `services.yaml`

```yaml

    BobdenOtter\Conimex\:
        resource: '../vendor/bobdenotter/conimex/src/*'
        exclude: '../vendor/bobdenotter/conimex/src/{Entity,Exception,Kernel.php}'

```
-->


## Running PHPStan and Easy Codings Standard

First, make sure dependencies are installed:

```
COMPOSER_MEMORY_LIMIT=-1 composer update
```

And then run ECS:

```
vendor/bin/ecs check src
```
