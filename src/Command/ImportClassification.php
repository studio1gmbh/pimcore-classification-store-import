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
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Studio1\ClassificationStoreImportBundle\Classes\CollectionGroupRelationRepository;
use Studio1\ClassificationStoreImportBundle\Classes\CollectionRepository;
use Studio1\ClassificationStoreImportBundle\Classes\GroupKeyRelationRepository;
use Studio1\ClassificationStoreImportBundle\Classes\GroupRepository;
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
            )->addOption(
                'import-asset-id', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the asset id of the import file'
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

        $assetId = $input->getOption('import-asset-id');
        if (!$assetId) {
            $output->writeln('<error>Asset id is missing</error>');

            return 1;
        }

        $asset = \Pimcore\Model\Asset::getById($assetId);
        $path = sprintf('/var/www/html/public/var/assets%s', $asset->getFullPath());

        // open the file
        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($path);
        // read each cell of each row of each sheet

        $storeConfig = StoreConfig::getByName('HabaClassification');

        /** @var Sheet $sheet */
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() != 'PXM Klassen mit Attr.') {
                continue;
            }

            /** @var Row $row */
            $rowCount = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowCount++;
                if ($rowCount == 1) { // skip header
                    $rowTemplate = $row->toArray();
                    continue;
                }

                $data = $row->toArray();
                if (count($data) != 15) {
                    $data[] = '';
                }

                $rowData = array_combine($rowTemplate, $data);

                if ($rowData['Merkmal'] == '') { // Add group collection to tree
                    continue;
                }

                $collectionConfig = CollectionRepository::getOrCreateByName($rowData['Hängt an Klasse'], $storeConfig->getId());
                $groupConfig = GroupRepository::getOrCreateByName([
                    'code' => $rowData['Hängt an Klasse'],
                    'sapId' => null
                ], $storeConfig->getId());

                CollectionGroupRelationRepository::addGroupToCollection($groupConfig->getId(), $collectionConfig->getId());

                if ($rowData['Merkmalstyp'] == 'Push to PXM') {
                    continue;
                }

                if ($rowData['Merkmalstyp'] == 'Werteliste') {
                    continue;
                }

                if ($rowData['Merkmalstyp'] == 'Dezimalzahl') {
                    continue;
                }

                if ($rowData['Merkmalstyp'] == 'Ganzzahl') {
                    switch ($rowData['Einheit']) {
                        case 'Stk.':
                            $unit = 'Stk';
                            break;
                        case 'Zoll':
                            $unit = 'Zoll';
                            break;
                        case 'Zeichen':
                            $unit = 'Zeichen';
                            break;
                        default:
                            $unit = '';
                    }

                    if ($rowData['Wertigkeit'] == 'mehrwertig') {
                        $type = 'inputQuantityValue';
                        $name = sprintf('%s (mehrwertig)', $rowData['Merkmal']);
                        $definitionsArray = [
                            'fieldtype' => 'inputQuantityValue',
                            'name' => $name,
                            'title' => $name,
                            'datatype' => 'data',
                            'defaultUnit' => $unit,
                            'validUnits' => $unit
                        ];
                    } else {
                        $name = $rowData['Merkmal'];
                        $type = 'quantityValue';

                        $definitionsArray = [
                            'name' => $name,
                            'datatype' => 'data',
                            'fieldtype' => $type,
                            'title' => $name,
                            'tooltip' => '',
                            'mandatory' => $rowData['Pflicht'] == 'Ja' ? true : false,
                            'index' => false,
                            'unique' => false,
                            'noteditable' => false,
                            'invisible' => false,
                            'visibleGridView' => false,
                            'visibleSearch' => false,
                            'style' => '',
                            'width' => '',
                            'defaultValue' => null,
                            'defaultValueGenerator' => '',
                            'decimalSize' => null,
                            'decimalPrecision' => null,
                            'integer' => true,
                            'unsigned' => false,
                            'minValue' => null,
                            'maxValue' => null,
                            'defaultUnit' => $unit,
                            'validUnits' => $unit
                        ];
                    }
                }

                if ($rowData['Merkmalstyp'] == 'Boolean') {
                    $type = 'checkbox';
                    $name = $rowData['Merkmal'];

                    $definitionsArray = [
                        'name' => $name,
                        'datatype' => 'data',
                        'fieldtype' => $type,
                        'title' => $name,
                        'tooltip' => '',
                        'mandatory' => $rowData['Pflicht'] == 'Ja' ? true : false,
                        'index' => false,
                        'noteditable' => false,
                        'invisible' => false,
                        'visibleGridView' => false,
                        'visibleSearch' => false,
                        'style' => '',
                        'defaultValue' => 0,
                        'defaultValueGenerator' => '',
                    ];
                }

                if ($rowData['Merkmalstyp'] == 'Textfeld') {
                    if ($rowData['Wertigkeit'] == 'mehrwertig') {
                        $type = 'textarea';
                        $name = sprintf('%s (mehrwertig)', $rowData['Merkmal']);
                        $definitionsArray = [
                            'fieldtype' => 'input',
                            'name' => $name,
                            'title' => $name,
                            'datatype' => 'data',
                            'tooltip' => '',
                            'mandatory' => $rowData['Pflicht'] == 'Ja' ? true : false,
                            'index' => false,
                            'unique' => false,
                            'noteditable' => false,
                            'invisible' => false,
                            'visibleGridView' => false,
                            'visibleSearch' => false,
                            'style' => '',
                            'defaultValue' => '',
                            'defaultValueGenerator' => '',
                            'width' => '',
                            'showCharCount' => true
                        ];
                    } else {
                        $name = $rowData['Merkmal'];
                        $type = 'textarea';

                        $definitionsArray = [
                            'name' => $name,
                            'datatype' => 'data',
                            'fieldtype' => 'input',
                            'title' => $name,
                            'tooltip' => '',
                            'mandatory' => $rowData['Pflicht'] == 'Ja' ? true : false,
                            'index' => false,
                            'unique' => false,
                            'noteditable' => false,
                            'invisible' => false,
                            'visibleGridView' => false,
                            'visibleSearch' => false,
                            'style' => '',
                            'defaultValue' => '',
                            'defaultValueGenerator' => '',
                            'width' => '',
                            'showCharCount' => true
                        ];
                    }
                }

                $keyConfig = KeyConfig::getByName($name, $storeConfig->getId(), true);
                if (!$keyConfig) {
                    $keyConfig = new KeyConfig();
                    $keyConfig->setName($name);
                    $keyConfig->setDescription($name);
                    $keyConfig->setType($type);
                    $keyConfig->setStoreId($storeConfig->getId());
                    $keyConfig->setEnabled(1);
                    $keyConfig->setDefinition(json_encode($definitionsArray));
                    $keyConfig->save();
                }
                GroupKeyRelationRepository::addKeyToGroup($keyConfig->getId(), $groupConfig->getId());
            }
        }
        $reader->close();
        $monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }
}
