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
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tightenco\Collect\Support\Collection;

class Import
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

    public function __construct(EntityManagerInterface $em, Config $config, TaxonomyRepository $taxonomyRepository)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);
        $this->taxonomyRepository = $em->getRepository(Taxonomy::class);

        $this->em = $em;
        $this->config = $config;
        $this->taxonomyRepository = $taxonomyRepository;
    }

    public function setIO(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    public function import(array $yaml)
    {
        foreach($yaml as $contenttypeslug => $data) {
            if ($contenttypeslug == '__bolt_export_meta') {
                continue;
            }

            if ($contenttypeslug == '__users') {
                // @todo Add flag to skip importing users

                $this->importUsers($data);

                continue;
            }

            $this->importContentType($contenttypeslug, $data);
        }
    }

    /**
     * Bolt 3 exports have one block for each contenttype. Bolt 4 exports have only one 'content' block.
     *
     * We either use the name of the block, or an explicitly set 'contentType'
     *
     * @param string $contenttypeslug
     * @param array $data
     */
    private function importContentType(string $contenttypeslug, array $data)
    {
        $this->io->comment("Importing ContentType " . $contenttypeslug);

        $progressBar = new ProgressBar($this->io, count($data));
        $progressBar->setBarWidth(50);
        $progressBar->start();

        $count=0;

        foreach ($data as $record) {
            $record = new Collection($record);

            /** @var ContentType $contentType */
            $contentType = $this->config->getContentType($record->get('contentType', $contenttypeslug));

            if (! $contentType) {
                $this->io->error("Requested ContentType " . $record->get('contentType', $contenttypeslug) . " is not defined in contenttypes.yaml.");
                return;
            }

            $this->importRecord($contentType, $record);

            if ($count++ % 3 == 0) {
                $this->em->clear();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();

    }

    private function importRecord(ContentType $contentType, Collection $record)
    {
        $user = $this->guesstimateUser($record);

        $slug = $record->get('slug', $record->get('fields')['slug']);
        /** @var Content $content */
        $content = $this->contentRepository->findOneByFieldValue('slug', $slug);

        if (!$content) {
            $content = new Content($contentType);
            $content->setStatus('published');
            $content->setAuthor($user);
        }

        // Import Bolt 3 Fields and Taxonomies
        foreach ($record as $key => $item) {
            if ($content->hasFieldDefined($key)) {
                $content->setFieldValue($key, $item);
            }
            if ($content->hasTaxonomyDefined($key)) {
                foreach ($item as $taxo) {
                    if ($taxo['slug']) {
                        $content->addTaxonomy($this->taxonomyRepository->factory($key, $taxo['slug'], $taxo['name']));
                    }
                }
            }
        }

        // Import Bolt 4 Fields
        foreach ($record->get('fields') as $key => $item) {
            if ($content->hasFieldDefined($key)) {
                $content->setFieldValue($key, $item);
            }
        }

        // Import Bolt 4 Taxonomies
        foreach ($record->get('taxonomies') as $key => $item) {
            if ($content->hasTaxonomyDefined($key)) {
                foreach ($item as $slug => $name) {
                    if ($slug) {
                        $content->addTaxonomy($this->taxonomyRepository->factory($key, $slug, $name));
                    }
                }
            }
        }

        $content->setCreatedAt(new Carbon($record->get('createdAt', $record->get('datecreated'))));
        $content->setPublishedAt(new Carbon($record->get('publishedAt', $record->get('datepublish'))));
        $content->setModifiedAt(new Carbon($record->get('modifiedAt', $record->get('datechanged'))));

        // Make sure depublishAt is `null`, and doesn't get defaulted to "now".
        if ($record->get('depublishedAt') || $record->get('datedepublish')) {
            $content->setDepublishedAt(new Carbon($record->get('depublishedAt', $record->get('datedepublish'))));
        } else {
            $content->setDepublishedAt(null);
        }

        $this->em->persist($content);
        $this->em->flush();
    }

    private function guesstimateUser(Collection $record)
    {
        $user = null;

        // Bolt 3 exports have an 'ownerid', but we shouldn't use it
        if ($record->has('ownerid')) {
            $user = $this->userRepository->findOneBy(['id' => $record->get('ownerid')]);
        }

        // Fall back to the first user we can find. 🤷‍
        if (!$user) {
            $user = $this->userRepository->findOneBy([]);
        }

        return $user;
    }

    /**
     * @param array $data
     */
    private function importUsers(array $data)
    {
        foreach ($data as $importUser) {
            $importUser = new Collection($importUser);
            $user = $this->userRepository->findOneBy(['username' => $importUser->get('username')]);

            if ($user) {
                // If a user is present, we don't want to mess with it.
                continue;
            }

            $this->io->comment("Add user '" . $importUser->get('username'). "'.");

            $user = new User();

            $roles = $importUser->get('roles');

            // Bolt 3 fallback
            if (!in_array('ROLE_USER', $roles) && !in_array('ROLE_EDITOR', $roles)) {
                $roles[] = 'ROLE_EDITOR';
            }

            $user->setDisplayName($importUser->get('displayName', $importUser->get('displayname')));
            $user->setUsername($importUser->get('username'));
            $user->setEmail($importUser->get('email'));
            $user->setPassword($importUser->get('password'));
            $user->setRoles($roles);
            $user->setLocale($importUser->get('locale', 'en'));
            $user->setBackendTheme($importUser->get('backendTheme', 'default'));
            $user->isDisabled($importUser->get('disabled', !$importUser->get('enabled')));

            $this->em->persist($user);

            $this->em->flush();

        }
    }

}