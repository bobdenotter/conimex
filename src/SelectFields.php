<?php

namespace BobdenOtter\Conimex;

use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Tightenco\Collect\Support\Collection;

class SelectFields
{
    /** @var Content */
    private $record;

    /** @var Collection */
    private $selectFields;

    /** @var ContentRepository */
    private $contentRepository;

    public function __construct(Content $record, ContentRepository $contentRepository)
    {
        $this->record = $record;
        $this->selectFields = $this->getSelectFields();
        $this->contentRepository = $contentRepository;
    }

    private function getSelectFields()
    {
        $this->selectFields = collect();

        $this->getSelectFieldsInner($this->record->getDefinition()->get('fields'));

        return $this->selectFields;
    }

    private function getSelectFieldsInner($fields)
    {
        foreach($fields as $slug => $field) {
            if ($field->get('type') == 'select') {

                // Stupid 'slug' isn't always set.
                if (!array_key_exists('slug', $field)) {
                    $field['slug'] = $slug;
                }
                $this->selectFields->add($field);
            }

            if (in_array($field->get('type'), ['set', 'collection'], true)) {
                $this->getSelectFieldsInner($field->get('fields'));
            }
        }
    }

    public function updateSelectFields(array $record): array
    {
        $record['fields'] = $this->updateSelectFieldsInner($record['fields']);

        return $record;
    }

    private function updateSelectFieldsInner(array $fields): array
    {
        foreach($fields as $key => $field) {

            // Iterate over Collections
            if (in_array($key, ['collection'], true)) {
                $fields[$key] = $this->updateSelectFieldsInner($field);
            }

            // Iterate over Sets
            if (is_array($field) && array_key_exists(0, $field)) {
                foreach($field as $setKey => $setField) {

                    if (is_array($setField) && array_key_exists('value', $setField)) {
                        if ($this->selectFields->firstWhere('slug', $setField['name'])) {
                            // This is a Select field, update it!
                            $fields[$key][$setKey]['value'] = $this->populateSelectFieldReferencedData($setField['value']);
                        } else {
                            // We have to go deeper
                            $fields[$key][$setKey]['value'] = $this->updateSelectFieldsInner($setField['value']);
                        }
                    }
                }
            }

            // This is a Select field, update it!
            if ($this->selectFields->firstWhere('slug', $key)) {
                $fields[$key] = $this->populateSelectFieldReferencedData($field);
            }
        }

        return $fields;
    }

    private function populateSelectFieldReferencedData($selectFieldData)
    {
        $data = [];

        if (is_iterable($selectFieldData)) {
            foreach ($selectFieldData as $selectFieldValue) {
                $data[] = $this->querySelectFieldReferencedData($selectFieldValue);
            }

            return $data;
        }

        if (!empty($selectFieldData)) {
            $data[] = $this->querySelectFieldReferencedData($selectFieldData);
        }

        return $data;
    }

    private function querySelectFieldReferencedData($selectFieldValue)
    {
        if (!$selectFieldValue) {
            return null;
        }

        $data = [];

        $referencedRecord = $this->contentRepository->findBy(['id' => $selectFieldValue], [], 1);

        // Set the data of the referenced entity to fetch it when running import
        return [
            'id' => $selectFieldValue,
            'reference' => sprintf('%s/%s' , $referencedRecord[0]->getContentType(), $referencedRecord[0]->getFieldValues()['slug']),
        ];
    }

}

