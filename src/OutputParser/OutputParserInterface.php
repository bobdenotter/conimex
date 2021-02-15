<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\OutputParser;

/**
 * Common interface for all output parsers (yaml, csv...)
 */
interface OutputParserInterface
{
    public function parse(array $output, ?string $filename = '');
}
