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

use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use League\Csv\Reader;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Studio1\ClassificationStoreImportBundle\Classes\CollectionGroupRelationRepository;
use Studio1\ClassificationStoreImportBundle\Classes\CollectionRepository;
use Studio1\ClassificationStoreImportBundle\Classes\GroupKeyRelationRepository;
use Studio1\ClassificationStoreImportBundle\Classes\GroupRepository;
use Studio1\ClassificationStoreImportBundle\Classes\KeyRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportSAP extends AbstractCommand
{
    use \Elements\Bundle\ProcessManagerBundle\ExecutionTrait;

    protected MonitoringItem $monitoringItem;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function configure(): void
    {
        $this
            ->setName('studio1:csi:import:sap')
            ->setDescription('Import classification store data')
            ->addOption(
                'monitoring-item-id', null,
                InputOption::VALUE_REQUIRED,
                'Contains the monitoring item if executed via the Pimcore backend'
            )
            ->addOption(
                'merge-select-values', null,
                InputOption::VALUE_REQUIRED,
                'Sets the merge select values option',
                true
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $monitoringItem = $this->initProcessManager($input->getOption('monitoring-item-id'), ['autoCreate' => true]);

        $classFile = '/var/www/html/public/var/assets/import/sap_klassifikation.csv';
        $oReader = Reader::createFromPath($classFile);
        $oReader->setDelimiter(';');
        $oReader->setHeaderOffset(0);
        $GroupAndCollection = $oReader->getRecords([
            0 => 'ClassificationClass',
            1 => 'code',
            2 => 'sapId',
            3 => 'nameDe',
            4 => 'superclass',
        ]);

        $keysFile = '/var/www/html/public/var/assets/import/sap_keys.csv';
        $oReader = Reader::createFromPath($keysFile);
        $oReader->setDelimiter(';');
        $oReader->setHeaderOffset(0);
        $keys = $oReader->getRecords([
            0 => 'ClassAttribute',
            1 => 'classCode',
            2 => 'code',
            3 => 'sapId',
            4 => 'nameDe',
            5 => 'values',
        ]);

        $monitoringItem->setMessage('Starting process')->save();

        $storeConfig = StoreConfig::getByName('SapReadonly');

        foreach ($GroupAndCollection as $i => $item) {
            $monitoringItem->getLogger()->debug('Detailed log info for ' . $item['code']);
            $monitoringItem->setMessage('Processing ' . $item['code'])->setCurrentWorkload($i + 1)->save();

            $collectionConfig = CollectionRepository::getOrCreateByName($item['nameDe'], $storeConfig->getId());
            $groupConfig = GroupRepository::getOrCreateByName($item, $storeConfig->getId());

            CollectionGroupRelationRepository::addGroupToCollection($groupConfig->getId(), $collectionConfig->getId());
        }

        foreach ($keys as $i => $key) {
            $arrayValues = explode('],[', $key['values']);
            $arrayValues = array_filter($arrayValues);
            $newArrayValues = [];
            foreach ($arrayValues as $j => $value) {
                $value = str_replace(['[', '],'], '', $value);
                $monitoringItem->getLogger()->debug($value);
                $value = explode(':', $value);
                $newArrayValues[] = [
                    'value' => ($value[0]),
                    'key' => $value[1]
                ];
            }

            $key['values'] = $newArrayValues;

            $keyConfig = KeyRepository::getOrCreateByName($key, $storeConfig->getId(), $input->getOption('merge-select-values'), $monitoringItem->getLogger(), true);
            $groupConfig = GroupRepository::getByName($key['classCode'], $storeConfig->getId());

            GroupKeyRelationRepository::addKeyToGroup($keyConfig->getId(), $groupConfig->getId());
        }

        $monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }
}
