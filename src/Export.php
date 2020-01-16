<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Entity\User;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\TaxonomyRepository;
use Bolt\Repository\UserRepository;
use Bolt\Version;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

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

    public function __construct(EntityManagerInterface $em, Config $config, TaxonomyRepository $taxonomyRepository, \Bolt\Doctrine\Version $dbVersion)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);

        $this->config = $config;
        $this->dbVersion = $dbVersion;
    }

    public function setIO(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function export(string $filename): void
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
