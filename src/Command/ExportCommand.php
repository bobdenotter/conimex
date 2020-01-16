<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\Command;

use BobdenOtter\Conimex\Export;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExportCommand extends Command
{
    protected static $defaultName = 'conimex:export';

    /** @var Export */
    private $export;

    public function __construct(Export $export)
    {
        $this->export = $export;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Export Content from Bolt to Yaml')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'filename of the file to export')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        $this->export->setIO($io);

        $filename = $input->getArgument('arg1');

        if ($filename) {
            $io->note(sprintf('You passed an argument: %s', $filename));
        }

        if (! realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        $this->export->export($filename);

        $io->success('Done.');
    }
}
