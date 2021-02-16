<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\OutputParser;

use Symfony\Component\Yaml\Yaml;

class YamlParser implements OutputParserInterface
{
    public function parse(array $output): string
    {
        return Yaml::dump($output, 4);
    }
}
