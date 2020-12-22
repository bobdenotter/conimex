<?php

namespace BobdenOtter\Conimex\OutputParser;

use League\Csv\Writer;

class CsvParser extends AbstractParser implements OutputParserInterface
{
    public function parse(array $output, ?string $filename = ''): void
    {
        $csv = Writer::createFromString('');

        $rows = collect($output['content'])->map(function($content) {
            if ($content['createdAt'] instanceof \DateTime) {
                $content['createdAt'] = $content['createdAt']->format('Y-m-d H:i:s');
            }

            if ($content['modifiedAt'] instanceof \DateTime) {
                $content['modifiedAt'] = $content['modifiedAt']->format('Y-m-d H:i:s');
            }

            if ($content['publishedAt'] instanceof \DateTime) {
                $content['publishedAt'] = $content['publishedAt']->format('Y-m-d H:i:s');
            }

            if ($content['depublishedAt'] instanceof \DateTime) {
                $content['depublishedAt'] = $content['depublishedAt']->format('Y-m-d H:i:s');
            }

            $content['author'] = $content['author']['username'];

            return collect($content)->flatten()->toArray();
        });

        $headings = [];
        foreach($output['content'][0] as $key => $value) {
            if ($key === 'fields') {
                foreach($value as $name => $v) {
                    $headings[] = $name;
                }
            } else if (! in_array($key, ['twig'])){
                $headings[] = $key;
            }
        }

        $csv->insertOne($headings);

        $csv->insertAll($rows);

        $this->save($filename, $csv->getContent());
    }

}
