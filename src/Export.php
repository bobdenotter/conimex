<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use BobdenOtter\Conimex\OutputParser\OutputParserFactory;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Entity\Relation;
use Bolt\Entity\User;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\RelationRepository;
use Bolt\Repository\UserRepository;
use Bolt\Version;
use Doctrine\ORM\EntityManagerInterface;

class Export
{
    /** @var ContentRepository */
    private $contentRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var \Bolt\Doctrine\Version */
    private $dbVersion;

    /** @var RelationRepository */
    private $relationRepository;

    public function __construct(EntityManagerInterface $em, Config $config,
                                \Bolt\Doctrine\Version $dbVersion,
                                RelationRepository $relationRepository)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);
        $this->relationRepository = $relationRepository;

        $this->config = $config;
        $this->dbVersion = $dbVersion;
    }

    public function export(?string $contentType, string $format = 'yaml')
    {
        $output = [];

        $output['__bolt_export_meta'] = $this->buildMeta();
//        $output['__users'] = $this->buildUsers();
        $output['content'] = $this->buildContent($contentType);

        // Create a parser based on the requested file extension.
        $parser = OutputParserFactory::factory($format);

        return $parser->parse($output);
    }

    private function buildMeta()
    {
        return [
            'date' => date('c'),
            'version' => Version::fullName(),
            'platform' => $this->dbVersion->getPlatform(),
        ];
    }

    private function buildUsers()
    {
        $users = [];

        $userEntities = $this->userRepository->findAll();

        /** @var User $user */
        foreach ($userEntities as $user) {
            $users[] = $user->toArray();
        }

        return $users;
    }

    private function buildContent(?string $contentType)
    {
        $offset = 0;
        $limit = 100;
        $content = [];

        $criteria = [];
        if ($contentType) {
            $criteria['contentType'] = $contentType;
        }

        do {
            $contentEntities = $this->contentRepository->findBy($criteria, [], $limit, $limit * $offset);
            /** @var Content $record */
            foreach ($contentEntities as $record) {
                $currentITem = $record->toArray();
                // Get the select fields that have an entity referenced
                $selectFields = $this->getSelectFields($record);
                // Update the $currentItem with the right data that will be needed to make the reference when importing
                $currentITem = $this->updateSelectFields($currentITem, $selectFields);

                $currentITem['relations'] = [];
                $relationsDefinition = $record->getDefinition()->get('relations', []);


                foreach (array_keys((array) $relationsDefinition) as $fieldName) {
                    $relations = $this->relationRepository->findRelations($record, $fieldName);
                    $relationsSlug = [];

                    /** @var Relation $relation */
                    foreach ($relations as $relation) {
                        $relationsSlug[] = $relation->getToContent()->getContentType() . '/' . $relation->getToContent()->getSlug();
                    }
                    $currentITem['relations'][$fieldName] = $relationsSlug;
                }

                $content[] = $currentITem;
            }
            $offset++;
        } while ($contentEntities);

        return $content;
    }

    private function getSelectFields(Content $record)
    {
        $selectFields = $record->getDefinition()
            ->get('fields')
            ->filter(function($definition, $name) {
                if($definition['type'] == "select") {
                    $values = $definition->get('values');

                    if (is_string($values) && mb_strpos($values, '/') !== false) {
                        return true;
                    }
                }
            });

        return $selectFields;
    }

    private function updateSelectFields (array $currentITem, $selectFields)
    {
        foreach ($selectFields as $selectFieldDefinitionKey => $selectFieldDefinition)
        {
            $selectFieldData = $currentITem["fields"][$selectFieldDefinitionKey];
            $data = $this->populateSelectFieldReferencedData($selectFieldData, $selectFieldDefinitionKey, $selectFieldDefinition);

        }

        // Update the reference of the imported select field value.
        $currentITem['fields'][$selectFieldDefinitionKey] = $data;

        return $currentITem;
    }

    private function populateSelectFieldReferencedData ($selectFieldData, $selectFieldDefinitionKey, $selectFieldDefinition)
    {
        $data = [];

        if (is_iterable($selectFieldData)) {
            foreach ($selectFieldData as $selectFieldKey => $selectFieldValue){
                $data[] = $this->querySelectFieldReferencedData($selectFieldDefinition, $selectFieldValue);
            }
        } else {
            $data[] = $this->querySelectFieldReferencedData($selectFieldDefinition, $selectFieldData);
        }

        return $data;
    }

    private function querySelectFieldReferencedData ($selectFieldDefinition, $selectFieldValue)
    {
        $data = [];
        $selectFieldDefinitionValuesOption = $selectFieldDefinition->get('values');

        // Check if the Select field is populated with Records from different ContentTypes.
        // For example having a values definition like, (entries, news, articles)
        preg_match('/(?<=\()(.*?)(?=\))\//', $selectFieldDefinitionValuesOption,$matches);
        if(count($matches) > 0) {
            $contentTypes = explode(',', str_replace(' ', '', array_shift($matches)));

            // TODO: Build a more optimized query instead of looping over all ContenTypes querying per ContenType.
            foreach ($contentTypes as $key => $contentType) {
                $referencedRecordSlug = $this->fetchReferencedRecordSlug($contentType, $selectFieldValue);

                if(isset($referencedRecordSlug)) {
                    $data = [
                        'id' => $selectFieldValue,
                        'reference' => $contentType . '/' . $referencedRecordSlug
                    ];
                    break;
                }
            }
            
            return $data;
        }

        $referencedRecordSlug = $this->fetchReferencedRecordSlug(explode('/', $selectFieldDefinition['values'])[0], $selectFieldValue);

        // Set the data of the referenced entity to fetch it when running import
        $data = [
            'id' => $selectFieldValue,
            'reference' => explode('/', $selectFieldDefinition['values'])[0]
                . '/' . $referencedRecordSlug
        ];

        return $data;
    }

    private function fetchReferencedRecordSlug ($contentType, $selectFieldValue)
    {
        $criteria['contentType'] =  $contentType;
        $criteria['id'] = $selectFieldValue;

        $referencedRecord = $this->contentRepository->findBy($criteria, [], 1);

        $referencedRecordSlug = $referencedRecord[0]->getFieldValues()['slug'];

        return $referencedRecordSlug;
    }
}
