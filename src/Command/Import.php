<?php

/**
 * Studio1 Kommunikation GmbH
 *
 * This source file is available under following license:
 * - GNU General Public License v3.0 (GNU GPLv3)
 *
 *  @copyright  Copyright (c) Studio1 Kommunikation GmbH (http://www.studio1.de)
 *  @license    https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Studio1\ClassificationStoreImportBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class Import extends Command
{
    protected string $file;

    public function __construct(MessageBusInterface $messageBus, string $name = null)
    {
        $this->messageBus = $messageBus;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function configure(): void
    {
        $this->setName('studio1:csi:import')
            ->setDescription('Import classification store data');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        var_dump('start');

        return 0;
    }
}
