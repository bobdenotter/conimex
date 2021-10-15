<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\Command;

use BobdenOtter\Conimex\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument('filename', InputArgument::REQUIRED, 'filename of the file to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->import->setIO($io);

        $filename = $input->getArgument('filename');

        if ($filename) {
            $io->note(sprintf('Importing Bolt database contents from: %s', $filename));
        }

        if (! realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        if (! realpath($filename)) {
            echo "Couldn't read file '" . $filename . "'. \nMake sure it's readable, and you've given the correct filename.\n\n";

            return Command::FAILURE;
        }

        $yaml = Yaml::parseFile(realpath($filename));

        dump($yaml['__bolt_export_meta']);

        $this->import->import($yaml);

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
