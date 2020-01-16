<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\Command;

use BobdenOtter\Conimex\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ImportCommand extends Command
{
    protected static $defaultName = 'conimex:import';

    /** @var Import */
    private $import;

    public function __construct(Import $import)
    {
        $this->import = $import;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import Content from YAML into Bolt')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'filename of the file to import')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        $this->import->setIO($io);

        $filename = $input->getArgument('arg1');

        if ($filename) {
            $io->note(sprintf('You passed an argument: %s', $filename));
        }

        if (! realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        $yaml = Yaml::parseFile(realpath($filename));

        dump($yaml['__bolt_export_meta']);

        $this->import->import($yaml);

        $io->success('Done.');
    }
}
