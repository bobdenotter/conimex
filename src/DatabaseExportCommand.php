<?php

namespace App\Command;

use App\Conimex\Export;
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

class DatabaseExportCommand extends Command
{
    protected static $defaultName = 'conimex:export';

    /** @var ObjectManager */
    private $objectManager;

    /** @var Config */
    private $boltConfig;

    /** @var Export */
    private $export;

    public function __construct(EntityManagerInterface $objectManager, Config $boltConfig, Export $export)
    {
        $this->objectManager = $objectManager;
        $this->boltConfig = $boltConfig;
        $this->export = $export;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Export Content from Bolt to Yaml')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'filename of the file to export')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->export->setIO($io);

        $filename = $input->getArgument('arg1');

        if ($filename) {
            $io->note(sprintf('You passed an argument: %s', $filename));
        }

        if (!realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        $this->export->export($filename);

        $io->success('Done.');
    }


}
