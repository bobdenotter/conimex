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
        $output['__users'] = $this->buildUsers();
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
                $currentItem = $record->toArray();

                // Get the select fields that have an entity referenced
                $selectFields = new SelectFields($record, $this->contentRepository);

                // Update the $currentItem with the right data that will be needed to make the reference when importing
                $currentItem = $selectFields->updateSelectFields($currentItem);

                $currentItem['relations'] = [];
                $relationsDefinition = $record->getDefinition()->get('relations', []);

                // $relationsDefinition sometimes is a Collection, and sometimes (older code?) it is an array
                if ($relationsDefinition instanceof \IteratorAggregate) {
                    // This conversion to get the keys by iterating, and later iterating over the keys is a bit wasteful,
                    // I've put it like this to minimize changes to the rest of the code.
                    $fieldNames = [];
                    foreach ($relationsDefinition->getIterator() as $key => $val) {
                        $fieldNames[] = $key;
                    };
                } else {
                    $fieldNames = array_keys((array) $relationsDefinition);
                }
                foreach ($fieldNames as $fieldName) {
                    $relations = $this->relationRepository->findRelations($record, $fieldName);
                    $relationsSlug = [];

                    /** @var Relation $relation */
                    foreach ($relations as $relation) {
                        $relationsSlug[] = $relation->getToContent()->getContentType() . '/' . $relation->getToContent()->getSlug();
                    }
                    $currentItem['relations'][$fieldName] = $relationsSlug;
                }

                $content[] = $currentItem;

            }
            $offset++;


        } while ($contentEntities);

        return $content;
    }
}
