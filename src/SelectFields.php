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

            if (in_array($field->get('type'), ['set', 'collection'])) {
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
            if (in_array($key, ['collection'])) {
                $fields[$key] = $this->updateSelectFieldsInner($field);
            }

            // Iterare over Sets
            if (is_array($field) && array_key_exists(0, $field)) {
                foreach($field as $setKey => $setField) {
                    if (is_array($setField) && is_array($setField['value'])) {
                        $fields[$key][$setKey]['value'] = $this->updateSelectFieldsInner($setField['value']);
                    }
                }
            }

            // This is a Select field, update it!
            if ($this->selectFields->firstWhere('slug', $key)) {
                $fields[$key] = $this->populateSelectFieldReferencedData($field, $key, $this->selectFields->firstWhere('slug', $key));
            }
        }

        return $fields;
    }

    private function populateSelectFieldReferencedData($selectFieldData, $selectFieldDefinitionKey, $selectFieldDefinition)
    {
        $data = [];

        if (is_iterable($selectFieldData)) {
            foreach ($selectFieldData as $selectFieldValue) {
                $data[] = $this->querySelectFieldReferencedData($selectFieldDefinition, $selectFieldValue);
            }
        } else {
            $data[] = $this->querySelectFieldReferencedData($selectFieldDefinition, $selectFieldData);
        }

        return $data;
    }

    private function querySelectFieldReferencedData($selectFieldDefinition, $selectFieldValue)
    {
        $data = [];
        $selectFieldDefinitionValuesOption = $selectFieldDefinition->get('values');

        // Check if the Select field is populated with Records from different ContentTypes.
        // For example having a values definition like, (entries, news, articles)
        preg_match('/(?<=\()(.*?)(?=\))\//', $selectFieldDefinitionValuesOption, $matches);
        if (count($matches) > 0) {
            $contentTypes = explode(',', str_replace(' ', '', array_shift($matches)));

            // TODO: Build a more optimized query instead of looping over all ContenTypes querying per ContenType.
            foreach ($contentTypes as $contentType) {
                $referencedRecordSlug = $this->fetchReferencedRecordSlug($contentType, $selectFieldValue);

                if (isset($referencedRecordSlug)) {
                    $data = [
                        'id' => $selectFieldValue,
                        'reference' => $contentType . '/' . $referencedRecordSlug,
                    ];
                    break;
                }
            }

            return $data;
        }

        $referencedRecordSlug = $this->fetchReferencedRecordSlug(explode('/', $selectFieldDefinition['values'])[0], $selectFieldValue);

        // Set the data of the referenced entity to fetch it when running import
        return [
            'id' => $selectFieldValue,
            'reference' => explode('/', $selectFieldDefinition['values'])[0]
                . '/' . $referencedRecordSlug,
        ];
    }

    private function fetchReferencedRecordSlug($contentType, $selectFieldValue)
    {
        $criteria['contentType'] = $contentType;
        $criteria['id'] = $selectFieldValue;

        $referencedRecord = $this->contentRepository->findBy($criteria, [], 1);

        return $referencedRecord[0]->getFieldValues()['slug'];
    }
}

