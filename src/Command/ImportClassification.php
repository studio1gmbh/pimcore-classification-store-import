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

class ImportClassification extends AbstractCommand
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
            ->setName('studio1:csi:import:classification')
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

        $path = '/var/www/html/public/var/assets/import/HFG_PXM_Klassifikation.xlsx';
        # open the file
        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($path);
        # read each cell of each row of each sheet
        /** @var Sheet $sheet */
        foreach ($reader->getSheetIterator() as $sheet) {
            if($sheet->getName() != 'PXM Klassen mit Attr.')  {
                continue;
            }
            /** @var Row $row */
            foreach ($sheet->getRowIterator() as $row) {
                $monitoringItem->getLogger()->debug(var_export($row->toArray(), true));
                return 0;
            #    foreach ($row->getCells() as $cell) {
            #        var_dump($cell->getValue());
            #    }
            }
        }
        $reader->close();

        return 0;

        $monitoringItem->setMessage('Starting process')->save();

        $storeConfig = StoreConfig::getByName('HabaClassification');

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

            $keyConfig = KeyRepository::getOrCreateByName($key, $storeConfig->getId(), $input->getOption('merge-select-values'), $monitoringItem->getLogger());
            $groupConfig = GroupRepository::getByName($key['classCode'], $storeConfig->getId());

            GroupKeyRelationRepository::addKeyToGroup($keyConfig->getId(), $groupConfig->getId());
        }

        $monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }
}
