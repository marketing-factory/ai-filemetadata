<?php

namespace Mfd\Ai\FileMetadata\Command;

use Mfd\Ai\FileMetadata\Services\FalAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Search\FileSearchDemand;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class GenerateAltTextsCommand extends Command
{
    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly FalAdapter $falAdapter,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addOption(
                'path',
                mode: InputOption::VALUE_REQUIRED,
                description: 'FAL path to start alt text generation from',
            )
            ->addOption(
                'limit',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Limit operation to a maximum number of files',
            )
            ->addOption(
                'overwrite',
                mode: InputOption::VALUE_NONE,
                description: 'Overwrite existing metadata?',
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        ProgressBar::setFormatDefinition('with_message', ' %current%/%max% [%bar%] %message%');
        Bootstrap::initializeBackendAuthentication();

        $doOverwriteMetadata = $input->getOption('overwrite');
        $limit = $input->getOption('limit');

        $io = new SymfonyStyle($input, $output);

        if (($path = $input->getOption('path')) !== null) {
            $storage = $this->storageRepository->findByCombinedIdentifier($path);
            $folder = $storage->getFolder(substr($path, strpos($path, ':') + 1));
        } else {
            $storage = $this->storageRepository->getDefaultStorage();
            $folder = $storage->getRootLevelFolder();
        }

        $io->section('Generating new alternative texts');
        $this->falAdapter->iterate($folder, $doOverwriteMetadata, $limit, $output);

        return 0;
    }
}
