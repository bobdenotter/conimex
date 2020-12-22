<?php

namespace BobdenOtter\Conimex\OutputParser;

abstract class AbstractParser implements OutputParserInterface
{
    protected function save(?string $filename, $output): void
    {
        if ($filename) {
            file_put_contents($filename, $output);
        }
    }
}
