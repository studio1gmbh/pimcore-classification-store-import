<?php
/**
 * Created by PhpStorm.
 * User: jneugebauer
 * Date: 21.03.23
 * Time: 13:07
 */

namespace Studio1\ClassificationStoreImportBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
