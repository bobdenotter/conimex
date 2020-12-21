<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use BobdenOtter\Conimex\OutputParser\CsvParser;
use BobdenOtter\Conimex\OutputParser\OutputParserFactory;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Entity\User;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\RelationRepository;
use Bolt\Entity\Relation;
use Bolt\Repository\TaxonomyRepository;
use Bolt\Repository\UserRepository;
use Bolt\Version;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class Export
{
    /** @var SymfonyStyle */
    private $io;

    /** @var ContentRepository */
    private $contentRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var \Bolt\Doctrine\Version */
    private $dbVersion;

    /** @var RelationRepository */
    private $relationRepository;

    public function __construct(EntityManagerInterface $em, Config $config,
                                TaxonomyRepository $taxonomyRepository,
                                \Bolt\Doctrine\Version $dbVersion,
                                RelationRepository $relationRepository)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);
        $this->relationRepository = $relationRepository;

        $this->config = $config;
        $this->dbVersion = $dbVersion;
    }

    public function setIO(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function export(string $filename, ?string $contentType): void
    {
        $output = [];

        $output['__bolt_export_meta'] = $this->buildMeta();
        $output['__users']  = $this->buildUsers();
        $output['content'] = $this->buildContent($contentType);

        $parser = OutputParserFactory::factory(pathinfo($filename, PATHINFO_EXTENSION));

        if ($parser instanceof CsvParser) {
            $message = 'The CSV export support is experimental. You should only use it to export one ContentType at a time.';
            $message .= ' Otherwise, the exported results may be inaccurate.';
            $this->io->warning($message);
        }

        $parser->parse($output, $filename);
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
        $contentEntities = [];
        $progressBar = new ProgressBar($this->io, count($contentEntities));
        $progressBar->setBarWidth(50);
        $progressBar->start();

        $criteria = [];
        if ($contentType) {
            $criteria['contentType'] = $contentType;
        }

        do {
            $contentEntities = $this->contentRepository->findBy($criteria, [], $limit, $limit * $offset);
            /** @var Content $record */
            foreach ($contentEntities as $record) {
                $currentITem = $record->toArray();
                $currentITem['relations'] = [];
                $relationsDefinition = $record->getDefinition()->get('relations');

                foreach ($relationsDefinition as $fieldName => $relationDefinition) {
                    $relations = $this->relationRepository->findRelations($record, $fieldName);
                    $relationsSlug = [];

                    /** @var Relation $relation */
                    foreach ($relations as $relation) {
                        $relationsSlug[] = $relation->getToContent()->getContentType() . '/' . $relation->getToContent()->getSlug();
                    }
                    $currentITem['relations'][$fieldName] = $relationsSlug;
                }

                $content[] = $currentITem;
                $progressBar->advance();
            }
            $offset++;
        } while ($contentEntities);

        $progressBar->finish();
        $this->io->newLine();

        return $content;
    }
}
