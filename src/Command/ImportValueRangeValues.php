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
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Reader\XLSX\Sheet;
use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassHabaProduct;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\Version as Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportValueRangeValues extends AbstractCommand
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
            ->setName('studio1:csi:import:valuerangevalues')
            ->setDescription('Import classification valuerangevalues from excel file')
            ->addOption(
                'monitoring-item-id', null,
                InputOption::VALUE_REQUIRED,
                'Contains the monitoring item if executed via the Pimcore backend'
            )->addOption(
                'import-asset-id', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the asset id of the import file'
            )->addOption(
                'import-folder-id', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the object id where the value ranges are imported to'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws IOException
     * @throws ReaderNotOpenedException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        Version::disable();

        $monitoringItem = $this->initProcessManager($input->getOption('monitoring-item-id'), ['autoCreate' => true]);

        $assetId = $input->getOption('import-asset-id');
        if (!$assetId) {
            $output->writeln('<error>Asset id is missing</error>');

            return 1;
        }

        $parentId = $input->getOption('import-folder-id');
        if (!$parentId) {
            $output->writeln('<error>Folder id is missing</error>');

            return 1;
        }

        $asset = \Pimcore\Model\Asset::getById($assetId);
        $path = sprintf('/var/www/html/public/var/assets%s', $asset->getFullPath());

        // open the file
        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($path);
        // read each cell of each row of each sheet

        /** @var Sheet $sheet */
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() != 'Werteliste') {
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
                $rowData = array_combine($rowTemplate, $data);

                // Struktur der Wertelisten aufbauen
                // - Ermitteln der Ordner
                // - Anlegen der möglichen Ausprägungen

                // Zuweisung der Werte in die Struktur
                // - Ermitteln der Strukturordner
                // - Zuweisung der Ids zu den Strukturordnern

                $folder = DataObject::getByPath(sprintf('/Wertelisten/%s', $this->getValueName($rowData['MerkmalsName'])));
                if (!$folder) {
                    $folder = new Folder();
                    $folder->setParentId($parentId);
                    $folder->setKey($this->getValueName($rowData['MerkmalsName']));
                    $folder->save();
                }

                $value = DataObject::getByPath(sprintf('/Wertelisten/%s/%s', $this->getValueName($rowData['MerkmalsName']), $this->getValueName($rowData['Vorgabewert'])));
                if (!$value) {
                    $value = new DataObject\ClassSelection();
                    $value->setParentId($folder->getId());
                    $value->setKey($this->getValueName($rowData['Vorgabewert']));
                    $value->setSelectionName($rowData['Vorgabewert']);
                    $value->setSelectionValue($rowData['Vorgabewert']);
                    $value->setPublished(true);
                    $value->save();
                }

                $productListing = new ClassHabaProduct\Listing();
                $productListing->filterByKey($rowData['Hängt an Klasse']);
                $productListing->load();

                foreach ($productListing as $product) {
                    $property = $product->getProperty($this->getValueName($rowData['MerkmalsName']));

                    $monitoringItem->getLogger()->debug($property);

                    $properties = [];

                    if (strlen($property) !== 0) {
                        $properties = explode(',', $property);
                        $properties[] = $value->getId();

                        $properties = array_unique($properties, SORT_NUMERIC);
                    } else {
                        $properties[] = $value->getId();
                    }

                    $product->setProperty($this->getValueName($rowData['MerkmalsName']), 'text', implode(',', $properties), false, true);
                    $product->setProperty($this->getValueName($rowData['MerkmalsName'], 'Multi'), 'text', implode(',', $properties), false, true);
                    $product->save();
                }
            }
        }

        $reader->close();
        $monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }

    /**
     * @param string $merkmal
     * @param $multi
     *
     * @return string
     */
    private function getValueName(string $merkmal, $multi = ''): string
    {
        $merkmal = str_replace([' ', '_', '.', '(', ')', '-', '/', '/n'], [''], $merkmal);
        $merkmal = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'], $merkmal);

        return sprintf('value%s%s', $merkmal, $multi);
    }
}
