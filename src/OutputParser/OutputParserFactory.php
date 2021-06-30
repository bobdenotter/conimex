<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\OutputParser;

use Symfony\Component\Filesystem\Exception\IOException;

class OutputParserFactory
{
    public static function factory(string $type): OutputParserInterface
    {
        switch ($type) {
            case 'yaml':
                return new YamlParser();
            case 'csv':
                return new CsvParser();
            default:
                throw new IOException('Unrecognized file type. Please use `yaml` or `csv`');
        }
    }
}
