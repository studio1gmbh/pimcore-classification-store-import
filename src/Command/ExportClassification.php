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

use Box\Spout\Common\Entity\Row;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\XLSX\Sheet;
use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassHabaProduct;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\Version as Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportClassification extends AbstractCommand
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
            ->setName('studio1:csi:export:classification')
            ->setDescription('Export classification')
            ->addOption(
                'monitoring-item-id', null,
                InputOption::VALUE_REQUIRED,
                'Contains the monitoring item if executed via the Pimcore backend'
            )->addOption(
                'export-path', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the path of the export folder'
            )->addOption(
                'classification-store-name', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the classification store name to export'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $monitoringItem = $this->initProcessManager($input->getOption('monitoring-item-id'), ['autoCreate' => true]);
        $monitoringItem->setTotalSteps(3);
        $monitoringItem->setMessage('Job started')->setStatus('started');

        $classificationStoreName = $input->getOption('classification-store-name');
        if (!$classificationStoreName) {
            $monitoringItem->setMessage('Job aborted. Classification store name is missing.')->setCompleted();
            return 1;
        }

        $storeConfig = StoreConfig::getByName($classificationStoreName);
        if (!$storeConfig instanceof StoreConfig) {
            $monitoringItem->setMessage('Job aborted. Classification store not found.')->setCompleted();
            return 1;
        }

        $exportPath = $input->getOption('export-path');
        if(!file_exists($exportPath)) {
            $monitoringItem->setMessage('Exportpath not found. Creating.')->setStatus('running');
            mkdir($exportPath, 0777, true);
        }

        $monitoringItem->setMessage('Exporting Collections')->setStatus('running')->setCurrentStep(1);
        $collectionList = new DataObject\Classificationstore\CollectionConfig\Listing();
        $collectionList->setCondition('storeId = ?', $storeConfig->getId());
        $collectionList->load();
        $collectionFile = $exportPath . '/' . $classificationStoreName . '_collection.csv';
        $collectionFileStream = fopen($collectionFile, 'w');
        foreach ($collectionList as $collection) {
            fputcsv($collectionFileStream, [
                $collection->getId(),
                $collection->getName()
            ]);
        }
        fclose($collectionFileStream);

        $monitoringItem->setMessage('Exporting Groups')->setStatus('running')->setCurrentStep(2);
        $groupList = new DataObject\Classificationstore\GroupConfig\Listing();
        $groupList->setCondition('storeId = ?', $storeConfig->getId());
        $groupList->load();
        $groupFile = $exportPath . '/' . $classificationStoreName . '_group.csv';
        $groupFileFileStream = fopen($groupFile, 'w');
        fputcsv($groupFileFileStream, [
            'groupId',
            'groupName',
            'collectionIds'
        ]);
        foreach ($groupList as $group) {
            $relationList = new DataObject\Classificationstore\CollectionGroupRelation\Listing();
            $relationList->setCondition('groupId = ?', $group->getId());
            $relationList->load();
            $relations = [];
            foreach ($relationList as $relation) {
                $relations[] = $relation->getColId();
            }
            fputcsv($groupFileFileStream, [
                $group->getId(),
                $group->getName(),
                implode('|', $relations)
            ]);
        }

        $monitoringItem->setMessage('Exporting Keys')->setStatus('running')->setCurrentStep(3);
        $keyList = new DataObject\Classificationstore\KeyConfig\Listing();
        $keyList->setCondition('storeId = ?', $storeConfig->getId());
        $keyList->load();
        $keyFile = $exportPath . '/' . $classificationStoreName . '_key.csv';
        $keyFileFileStream = fopen($keyFile, 'w');
        fputcsv($keyFileFileStream, [
            'keyId',
            'keyName',
            'keyTitle',
            'keyType',
            'groupIds'
        ]);
        foreach ($keyList as $key) {
            $relationList = new DataObject\Classificationstore\KeyGroupRelation\Listing();
            $relationList->setCondition('keyId = ?', $key->getId());
            $relationList->load();
            $relations = [];
            foreach ($relationList as $relation) {
                $relations[] = $relation->getGroupId();
            }

            fputcsv($keyFileFileStream, [
                $key->getId(),
                $key->getName(),
                $key->getTitle(),
                $key->getType(),
                implode('|', $relations)
            ]);
        }

        $monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }
}
