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

To export a single Content Type, for example `pages`, use:

```bash
bin/console conimex:export websitename.yaml [pages]
```

To export a single Content Type as a CSV, use:

```bash
bin/console conimex:export websitename.csv [pages]
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
