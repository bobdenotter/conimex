<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\OutputParser;

use Bolt\Common\Arr;
use League\Csv\Writer;

class CsvParser implements OutputParserInterface
{
    public function parse(array $output): string
    {
        $csv = Writer::createFromString('');

        $headings = [];
        foreach ($output['content'][0] as $key => $value) {
            if ($key === 'fields') {
                foreach (array_keys($value) as $name) {
                    $headings[] = $name;
                }
            } elseif (! in_array($key, ['twig', 'relationsToThisContent', 'relationsFromThisContent'], true)) {
                $headings[] = $key;
            }
        }

        $rows = collect($output['content'])->map(function ($content) use ($headings, $locale) {
            $result = [];

            foreach ($headings as $heading) {
                if (isset($content[$heading])) {
                    $column = $content[$heading];
                } elseif (isset($content['fields'][$heading])) {
                    $column = $content['fields'][$heading];
                }

                // Only get values for the requested locale
                if (is_array($column) && isset($column[$locale])) {
                    $column = $column[$locale];
                }

                if (is_array($column)) {
                    // If the value is an array, flatten it. Otherwise, a mismatch between columns in different rows appears.
                    $column = implode(',', Arr::flatten($column));
                } elseif ($column instanceof \DateTime) {
                    $column = $column->format('Y-m-d H:i:s');
                }

                $result[] = $column;
            }

            return $result;
        });

        $csv->insertOne($headings);

        $csv->insertAll($rows);

        return $csv->getContent();
    }
}
