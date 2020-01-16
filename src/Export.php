<?php

namespace App\Conimex;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Taxonomy;
use Bolt\Entity\User;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\TaxonomyRepository;
use Bolt\Repository\UserRepository;
use Bolt\Version;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

class Export
{
    /** @var SymfonyStyle */
    private $io;

    /** @var EntityManagerInterface */
    private $em;

    /** @var ContentRepository */
    private $contentRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var TaxonomyRepository */
    private $taxonomyRepository;

    /** @var Config */
    private $config;

    /** @var \Bolt\Doctrine\Version */
    private $dbVersion;

    /** @var OutputInterface */
    private $output;

    public function __construct(EntityManagerInterface $em, Config $config, TaxonomyRepository $taxonomyRepository, \Bolt\Doctrine\Version $dbVersion)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);
        $this->taxonomyRepository = $em->getRepository(Taxonomy::class);

        $this->em = $em;
        $this->config = $config;
        $this->taxonomyRepository = $taxonomyRepository;
        $this->dbVersion = $dbVersion;
    }

    public function setIO(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    public function export(string $filename)
    {
        $output = [];

        $output['__bolt_export_meta'] = $this->buildMeta();
        $output['__users'] = $this->buildUsers();
        $output['content'] = $this->buildContent();

        $yaml = Yaml::dump($output, 4);

        file_put_contents($filename, $yaml);
    }

    private function buildMeta()
    {
        $meta = [
            'date' => date('c'),
            'version' => Version::fullName(),
            'platform' => $this->dbVersion->getPlatform()
            ];

        return $meta;
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

    private function buildContent()
    {
        $content = [];

        $contentEntities = $this->contentRepository->findAll();

        $progressBar = new ProgressBar($this->io, count($contentEntities));
        $progressBar->setBarWidth(50);
        $progressBar->start();

        /** @var Content $record */
        foreach ($contentEntities as $record) {
            $content[] = $record->toArray();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();

        return $content;
    }


}