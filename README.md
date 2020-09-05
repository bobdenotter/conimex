# Conimex: Content Importer and Exporter

Author: Bob den Otter <bobdenotter@gmail.com>

Extension to export your bolt content as YAML, and import it again.

## Installation:

```bash
composer require bobdenOtter/conimex
```

## Usage: 

Exporting: 

```bash
bin/console conimex:export websitename.yaml
```

Importing: 

```bash
bin/console conimex:import websitename.yaml
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
