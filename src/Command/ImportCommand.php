<?php

namespace App\Command;

use App\Conimex\Import;
use Bolt\Common\Json;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Entity\Taxonomy;
use Bolt\Entity\User;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

class ImportCommand extends Command
{
    protected static $defaultName = 'conimex:import';

    /** @var ObjectManager */
    private $objectManager;

    /** @var Config */
    private $boltConfig;

    private $counter = 1;

    /** @var \Bolt\Repository\TaxonomyRepository */
    private $taxonomyRepository;

    /** @var Import */
    private $import;

    public function __construct(EntityManagerInterface $objectManager, Config $boltConfig, Import $import)
    {
        $this->objectManager = $objectManager;
        $this->boltConfig = $boltConfig;
        $this->import = $import;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Import Content from YAML into Bolt')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'filename of the file to import')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->import->setIO($io);

        $filename = $input->getArgument('arg1');

        if ($filename) {
            $io->note(sprintf('You passed an argument: %s', $filename));
        }

        if (!realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        $yaml = Yaml::parseFile(realpath($filename));

        dump($yaml['__bolt_export_meta']);

        $this->import->import($yaml);

        $io->success('Done.');
    }


    public function upsert($yaml)
    {
        $contentRepository = $this->objectManager->getRepository(Content::class);
        $userRepository = $this->objectManager->getRepository(User::class);
        $this->taxonomyRepository = $this->objectManager->getRepository(Taxonomy::class);

        $user = $userRepository->findOneBy(['username' => 'admin']);
        $contentTypeDefinition = $this->boltConfig->getContentType('feeditems');


        foreach($yaml['feeditems'] as $item) {

            $content = $contentRepository->findOneByFieldValue('itemid', $item['itemid']);

            if (!$content) {
                echo " - " . $this->counter++ . " [new] " . $item['title'] . "\n";
                $content = new Content($contentTypeDefinition);
                $content->setStatus('published');
                $content->setAuthor($user);
            } else {
                echo " - [upd] ". $item['title'] . "\n";

                // Note: For now we don't actually update. Just skip 'm.
                continue;

            }


            $content->setFieldValue('title', $item['title']);
            $content->setFieldValue('slug', $item['slug']);
            $content->setFieldValue('itemid', $item['itemid']);
            $content->setFieldValue('content', $item['content']);
            $content->setFieldValue('source', $item['source']);
            $content->setFieldValue('author', $item['author']);
            $content->setFieldValue('image', $item['image']);
            $content->setFieldValue('sitetitle', $item['sitetitle']);
            $content->setFieldValue('sitesource', $item['sitesource']);

            $content->setCreatedAt(new Carbon($item['datecreated']));
            $content->setPublishedAt(new Carbon($item['datepublish']));
            $content->setModifiedAt(new Carbon($item['datechanged']));

            if (!empty($item['tags'])) {
                $this->updateTaxonomy($content, 'tags', $item['tags']);
            }
            if (!empty($item['authors'])) {
                $this->updateTaxonomy($content, 'authors', $item['authors']);
            }

            $this->objectManager->persist($content);


            $this->objectManager->flush();

        }

    }


    private function updateTaxonomy(Content $content, string $key, $taxonomies): void
    {
        $taxonomies = (new Collection(Json::findArray($taxonomies)))->filter();

        // Remove old ones
        foreach ($content->getTaxonomies($key) as $current) {
            $content->removeTaxonomy($current);
        }

        // Then (re-) add selected ones
        foreach ($taxonomies as $taxo) {
            $taxonomy = $this->taxonomyRepository->findOneBy([
                'type' => $key,
                'slug' => $taxo['slug'],
            ]);

            if ($taxonomy === null) {
                $taxonomy = Taxonomy::factory($key, $taxo['slug'], $taxo['name']);
            }

            $content->addTaxonomy($taxonomy);
        }
    }
}
