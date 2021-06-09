<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Controller\Backend\ContentEditController;
use Bolt\Entity\Content;
use Bolt\Entity\Relation;
use Bolt\Entity\Taxonomy;
use Bolt\Entity\User;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\RelationRepository;
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

    /** @var RelationRepository */
    private $relationRepository;

    /** @var Config */
    private $config;

    public function __construct(EntityManagerInterface $em, Config $config, TaxonomyRepository $taxonomyRepository, RelationRepository $relationRepository, ContentEditController $contentEditController)
    {
        $this->contentRepository = $em->getRepository(Content::class);
        $this->userRepository = $em->getRepository(User::class);
        $this->taxonomyRepository = $em->getRepository(Taxonomy::class);

        $this->em = $em;
        $this->config = $config;
        $this->taxonomyRepository = $taxonomyRepository;
        $this->relationRepository = $relationRepository;
        $this->contentEditController = $contentEditController;

        // Data for updating collections
        $this->data = [
            'collections' => [
            ],
        ];
    }

    public function setIO(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function import(array $yaml): void
    {
        foreach ($yaml as $contenttypeslug => $data) {
            if ($contenttypeslug === '__bolt_export_meta') {
                continue;
            }

            if ($contenttypeslug === '__users') {
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
     */
    private function importContentType(string $contenttypeslug, array $data): void
    {
        $this->io->comment('Importing ContentType ' . $contenttypeslug);

        $progressBar = new ProgressBar($this->io, count($data));
        $progressBar->setBarWidth(50);
        $progressBar->start();

        $count = 0;

        foreach ($data as $record) {
            $record = new Collection($record);

            /** @var ContentType $contentType */
            $contentType = $this->config->getContentType($record->get('contentType', $contenttypeslug));

            if (! $contentType) {
                $this->io->error('Requested ContentType ' . $record->get('contentType', $contenttypeslug) . ' is not defined in contenttypes.yaml.');

                return;
            }

            $this->importRecord($contentType, $record);

            if ($count++ % 3 === 0) {
                $this->em->clear();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();
    }

    private function importRecord(ContentType $contentType, Collection $record): void
    {
        $user = $this->guesstimateUser($record);

        $slug = $record->get('slug', $record->get('fields')['slug']);

        // Slug can be either a string (older exports) or an array with a single element (newer exports)
        if (is_array($slug)) {
            $slug = current($slug);
        }

        /** @var Content $content */
        $content = $this->contentRepository->findOneBySlug($slug, $contentType);

        if (! $content) {
            $content = new Content($contentType);
            $content->setStatus('published');
            $content->setAuthor($user);
        }

        // Import Bolt 3 Fields and Taxonomies
        foreach ($record as $key => $item) {
            if ($content->hasFieldDefined($key)) {
                $fieldDefinition = $content->getDefinition()->get('fields')->get($key);

                // Convert 'file' in incoming image or file to 'filename'
                if (in_array($fieldDefinition['type'], ['image', 'file'], true)) {
                    $item = (array) $item;
                    $item['filename'] = ! empty($item['file']) ? $item['file'] : current($item);

                    // If no filename is set, don't import a broken/missing image
                    if (! $item['filename']) {
                        continue;
                    }
                }

                if (in_array($fieldDefinition['type'], ['collection'], true)) {
                    // Here, we're importing a Bolt 3 block and repeater into a Bolt 4 collection of sets.

                    $i = 1;

                    if (empty($item)) {
                        // Do not import an empty field.
                        continue;
                    }

                    foreach ($item as $fieldData) {
                        if (is_array(current(array_values($fieldData)))) {
                            // We are importing a block
                            foreach ($fieldData as $setName => $setValue) {
                                $this->data['collections'][$key][$setName][$i] = $setValue;
                                $this->data['collections'][$key]['order'][] = $i;
                                $i++;
                            }
                        } else {
                            // We are importing a repeater. It does not have a name.
                            // The set name will be the old repeater's name minus the last character.

                            $setName = mb_substr($key, 0, -1);
                            $i++;

                            foreach ($fieldData as $name => $value) {
                                $this->data['collections'][$key][$setName][$i][$name] = $value;
                                $this->data['collections'][$key]['order'][] = $i;
                            }
                        }
                    }

                    continue;
                }

                // Handle select fields with referenced entities
                if ($fieldDefinition['type'] === 'select') {
                    $values = $content->getDefinition()->get('fields')[$key]->get('values');
                    $result = [];
                    // Check if this select field Definition has referenced entities
                    if (is_string($values) && mb_strpos($values, '/') !== false) {
                        if (is_iterable($item)) {
                            foreach ($item as $itemValueKey => $itemValue) {
                                // No references are exported as null, make sure to avoid importing those
                                if (isset($item[$itemValueKey]['value'])) {
                                    $contentType = $this->config->getContentType(explode('/', $itemValue['_id'])[0]);
                                    $slug = explode('/', $itemValue['_id'])[1];
                                    $referencedEntity = $this->contentRepository->findOneBySlug($slug, $contentType);
                                    if ($referencedEntity instanceof Content) {
                                        $result[] = $referencedEntity->getId();
                                    }
                                }
                            }
                            $item = $result;
                        }
                    }
                    
                    $field = $this->contentEditController->getFieldToUpdate($content, $key);
                    $this->contentEditController->updateField($field, $item, null);
                }

                $content->setFieldValue($key, $item);

                // Import localized field if needed, from BoltTranslate
                if (count($contentType['locales']) > 0 && $fieldDefinition['localize']) {
                    foreach ($contentType['locales'] as $locale) {
                        // More recent BoltTranslate versions, in a field like `endata`.
                        if (isset($record[$locale . 'data']) && $record[$locale . 'data'] !== null) {
                            $localizeFields = json_decode($record[$locale . 'data'], true);
                            if (isset($localizeFields[$key])) {
                                $content->setFieldValue($key, $localizeFields[$key], $locale);
                            }
                        }

                        // Older BoltTranslate versions, in a field like `body_en`
                        if (isset($record[$key . '_' . $locale])) {
                            $content->setFieldValue($key, $record[$key . '_' . $locale], $locale);
                        }
                    }
                }
            }

            if ($content->hasTaxonomyDefined($key)) {
                foreach ($item as $taxo) {
                    $configForTaxonomy = $this->config->getTaxonomy($key);
                    if ($taxo['slug'] &&
                        $configForTaxonomy !== null &&
                        $configForTaxonomy['options']->get($taxo['slug']) !== null) {
                        $content->addTaxonomy($this->taxonomyRepository->factory($key,
                            $taxo['slug'],
                            $configForTaxonomy['options']->get($taxo['slug'])));
                    }
                }
            }
        }

        // If there were any repeaters/blocks in to be saved as collections/sets, do so here.
        // Save it the way the contentEditController saves it.
        $this->contentEditController->updateCollections($content, $this->data, null);
        $this->data = []; // unset it for the next time it's needed.
        // Import Bolt 4 Fields
        foreach ($record->get('fields', []) as $key => $item) {
            if ($content->hasFieldDefined($key)) {
                // Handle collections
                if ($content->getDefinition()->get('fields')[$key]['type'] === 'collection') {
                    $data = [
                        'collections' => [
                            $key => [],
                        ],
                    ];

                    $i = 1;
                    foreach ($item as $fieldData) {
                        $data['collections'][$key][$fieldData['name']][$i] = $fieldData['value'];
                        $data['collections'][$key]['order'][] = $i;
                        $i++;
                    }

                    $this->contentEditController->updateCollections($content, $data, null);
                } else {
                    // Handle all other fields
                    if ($this->isLocalisedField($content, $key, $item)) {
                        foreach ($item as $locale => $value) {
                            $content->setFieldValue($key, $value, $locale);
                        }
                    } else {
                        $field = $this->contentEditController->getFieldToUpdate($content, $key);

                        // Handle select fields with referenced entities
                        if ($content->getDefinition()->get('fields')[$key]['type'] === 'select') {
                            $values = $content->getDefinition()->get('fields')[$key]->get('values');
                            $result = [];

                            // Check if this select field Definition has referenced entities
                            if (is_string($values) && mb_strpos($values, '/') !== false) {
                                if (is_iterable($item)) {
                                    foreach ($item as $key => $itemValue) {
                                        $contentType = $this->config->getContentType(explode('/', $itemValue['reference'])[0]);
                                        $slug = explode('/', $itemValue['reference'])[1];
                                        $referencedEntity = $this->contentRepository->findOneBySlug($slug, $contentType);

                                        if ($referencedEntity instanceof Content) {
                                            $result[] = $referencedEntity->getId();
                                        }
                                    }
                                }
                            }

                            $item = $result;
                        }
                        $this->contentEditController->updateField($field, $item, null);
                    }
                }
            }
        }

        // Import Bolt 4 Taxonomies
        foreach ($record->get('taxonomies', []) as $key => $item) {
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

        //import relations
        foreach ($content->getDefinition()->get('relations') as $key => $relation) {
            if (isset($record[$key])) {
                // Remove old ones
                $currentRelations = $this->relationRepository->findRelations($content, null, true, null, false);
                foreach ($currentRelations as $currentRelation) {
                    $this->em->remove($currentRelation);
                }

                //create new relation
                foreach ($record[$key] as $relationSource) {
                    $item = explode('/', $relationSource);
                    $contentType = ContentType::factory($item[0], $this->config->get('contenttypes'));
                    $contentTo = $this->contentRepository->findOneBySlug($item[1], $contentType);
                    if ($contentTo === null) {
                        continue;
                    }
                    $relation = new Relation($content, $contentTo);
                    $this->em->persist($relation);
                }
            }
        }

        $this->em->persist($content);
        $this->em->flush();
    }

    private function isLocalisedField(Content $content, string $key, $item): bool
    {
        $fieldDefinition = $content->getDefinition()->get('fields')->get($key);

        if (! $fieldDefinition['localize']) {
            return false;
        }

        if (! is_array($item)) {
            return false;
        }

        foreach (keys($item) as $key) {
            if (! preg_match('/^[a-z]{2}([_-][a-z]{2,3})?$/i', $key)) {
                return false;
            }
        }

        return true;
    }

    private function guesstimateUser(Collection $record)
    {
        $user = null;

        // Bolt 3 exports have an 'ownerid', but we shouldn't use it
        if ($record->has('ownerid')) {
            $user = $this->userRepository->findOneBy(['id' => $record->get('ownerid')]);
        }

        // Fall back to the first user we can find. 🤷‍
        if (! $user) {
            $user = $this->userRepository->findOneBy([]);
        }

        return $user;
    }

    private function importUsers(array $data): void
    {
        foreach ($data as $importUser) {
            $importUser = new Collection($importUser);
            $user = $this->userRepository->findOneBy(['username' => $importUser->get('username')]);

            if ($user) {
                // If a user is present, we don't want to mess with it.
                continue;
            }

            $this->io->comment("Add user '" . $importUser->get('username') . "'.");

            $user = new User();

            $roles = $importUser->get('roles');

            // Bolt 3 fallback
            if (! in_array('ROLE_USER', $roles, true) && ! in_array('ROLE_EDITOR', $roles, true)) {
                $roles[] = 'ROLE_EDITOR';
            }

            $user->setDisplayName($importUser->get('displayName', $importUser->get('displayname')));
            $user->setUsername($importUser->get('username'));
            $user->setEmail($importUser->get('email'));
            $user->setPassword($importUser->get('password'));
            $user->setRoles($roles);
            $user->setLocale($importUser->get('locale', 'en'));
            $user->setBackendTheme($importUser->get('backendTheme', 'default'));
            $user->setStatus($importUser->get('status', ($importUser->get('enabled') ? 'enabled' : 'disabled')));

            $this->em->persist($user);

            $this->em->flush();
        }
    }
}
