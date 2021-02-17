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
            ->addArgument('filename', InputArgument::REQUIRED, 'filename of the file to export')
            ->addOption('contenttype', 'c', InputOption::VALUE_OPTIONAL, 'ContentType to export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filename = $input->getArgument('filename');

        if ($filename) {
            $io->note(sprintf('Exporting Bolt database contents as: %s', $filename));
        }

        if (! realpath($filename)) {
            $filename = getcwd() . '/' . $filename;
        }

        $type = pathinfo($filename, PATHINFO_EXTENSION);

        $contentType = $input->getArgument('contenttype') ?? null;

        $output = $this->export->export($contentType, $type);

        $this->save($filename, $output);

        $io->success('Done.');

        return 1;
    }

    private function save(?string $filename, $output): void
    {
        if ($filename) {
            file_put_contents($filename, $output);
        }
    }
}
