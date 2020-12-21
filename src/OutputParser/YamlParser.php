<?php

namespace BobdenOtter\Conimex\OutputParser;

use Symfony\Component\Yaml\Yaml;

class YamlParser extends AbstractParser implements OutputParserInterface
{
    public function parse(array $output, ?string $filename = ''): void
    {
        $yaml = Yaml::dump($output, 4);

        $this->save($filename, $yaml);
    }
}
