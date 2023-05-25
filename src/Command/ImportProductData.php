<?php

/**
 * Studio1 Kommunikation GmbH
 *
 * This source file is available under following license:
 * - GNU General Public License v3.0 (GNU GPLv3)
 *
 * @copyright  Copyright (c) Studio1 Kommunikation GmbH (http://www.studio1.de)
 * @license    https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Studio1\ClassificationStoreImportBundle\Command;

use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportProductData extends AbstractCommand
{
    use \Elements\Bundle\ProcessManagerBundle\ExecutionTrait;

    protected MonitoringItem $monitoringItem;
    protected string $importMode;
    protected array $ignoreAttributes = [
        'Artikelnummer',
        'Basisprodukt',
        'Bezeichnung',
        'Pfad',
        'PXM Klasse',
        'Einsatzbereich'
    ];

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
            ->setName('studio1:csi:import:productdata')
            ->setDescription('Import Product data')
            ->addOption(
                'monitoring-item-id', null,
                InputOption::VALUE_REQUIRED,
                'Contains the monitoring item if executed via the Pimcore backend'
            )->addOption(
                'classification-store-name', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the classification store name to export'
            )->addOption(
                'import-file', null,
                InputOption::VALUE_REQUIRED,
                'Specifies the import file id'
            )->addOption(
                'import-mode', null,
                InputOption::VALUE_OPTIONAL,
                'Specifies the type of data to import (product or article)',
                'product'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnavailableStream
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitoringItem = $this->initProcessManager($input->getOption('monitoring-item-id'), ['autoCreate' => true]);
        $this->monitoringItem->setTotalSteps(3);
        $this->monitoringItem->setMessage('Job started')->setStatus('started');

        $classificationStoreName = $input->getOption('classification-store-name');
        if (!$classificationStoreName) {
            $this->monitoringItem->setMessage('Job aborted. Classification store name is missing.')->setCompleted();
            return 1;
        }

        $storeConfig = StoreConfig::getByName($classificationStoreName);
        if (!$storeConfig instanceof StoreConfig) {
            $this->monitoringItem->setMessage('Job aborted. Classification store not found.')->setCompleted();
            return 1;
        }

        $importFileId = $input->getOption('import-file');
        $importFile = Asset::getById($importFileId);

        if (!$importFile instanceof Asset) {
            $this->monitoringItem->setMessage('Job aborted. Import File not found.')->setCompleted();
            return 1;
        }

        $this->importMode = $input->getOption('import-mode');

        $oReader = Reader::createFromPath(sprintf('/var/www/html/public/var/assets%s', $importFile->getFullPath()));
        $oReader->setDelimiter(';');
        $oReader->setHeaderOffset(0);

        $this->monitoringItem->setTotalSteps($oReader->count());
        $i = 1;

        $productData = $oReader->getRecords();
        foreach ($productData as $productDatum) {
            $this->monitoringItem->setCurrentStep($i++);
            $this->importData($productDatum);
        }

        $this->monitoringItem->setMessage('Job finished')->setCompleted();

        return 0;
    }

    /**
     * @param array $datas
     * @return void
     */
    private function importData(array $datas): void
    {
        if (strlen($datas['Artikelnummer']) !== 0) {
            if ($this->importMode !== 'article') {
                // an article needs an articleNumber, this is a product
                #return;
            }
            $identifier = $datas['Artikelnummer'];
        } else {
            if ($this->importMode !== 'product') {
                // a product must not have an articleNumber, this is an article
                #return;
            }
            $identifier = $datas['Basisprodukt'];
        }


        $dataObject = DataObject\ClassHabaProduct::getByProductNumber($identifier, 1);
        if (!$dataObject instanceof DataObject\ClassHabaProduct) {
            // object not found
            #$this->monitoringItem->getLogger()->debug(sprintf('Dataobject not found: %s', $identifier));
            return;
        }

        $this->monitoringItem->getLogger()->debug(sprintf('Dataobject found: %s (%s)', $identifier, $dataObject->getId()));

        $importData = [];
        foreach ($datas as $key => $data) {
            if (in_array($key, $this->ignoreAttributes)) {
                continue;
            }

            if (strlen($data) !== 0) {
                $importData[$this->getValueName($key)] = $data;
            }
        }

        $objectKeys = [];
        $allKeys = $this->getGroups($dataObject);

        foreach ($allKeys as $groups) {
            /** @var DataObject\Classificationstore\Key $collectionKey */
            foreach ($groups->getKeys() as $collectionKey) {
                if (array_key_exists($collectionKey->getConfiguration()->getName(), $importData)) {
                    if ($collectionKey->getConfiguration()->getType() == 'quantityValue') {
                        $valueData = explode(' ', $importData[$collectionKey->getConfiguration()->getName()]);
                        if(count($valueData) == 2) {
                            $valueData[0] = str_replace(',', '.', $valueData[0]);
                            $valueData[1] = str_replace(['Stück'], ['Stk.'], $valueData[1]);
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\QuantityValue($valueData[0], $valueData[1]);
                        } else {
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\QuantityValue($valueData[0]);
                        }
                    }

                    if ($collectionKey->getConfiguration()->getType() == 'inputQuantityValue') {
                        $valueData = explode(' ', $importData[$collectionKey->getConfiguration()->getName()]);
                        if(count($valueData) == 2) {
                            $valueData[0] = str_replace(',', '.', $valueData[0]);
                            $valueData[1] = str_replace(['Stück'], ['Stk.'], $valueData[1]);
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\InputQuantityValue($valueData[0], $valueData[1]);
                        } else {
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\InputQuantityValue($valueData[0]);
                        }
                    }

                    if($collectionKey->getConfiguration()->getType() == 'multiselect') {
                        $importData[$collectionKey->getConfiguration()->getName()] = $this->getValueName($importData[$collectionKey->getConfiguration()->getName()]);
                    }

                    $objectKeys[$groups->getConfiguration()->getId()][$collectionKey->getConfiguration()->getId()]['default'] = array_key_exists($collectionKey->getConfiguration()->getName(), $importData) ? $importData[$collectionKey->getConfiguration()->getName()] : null;
                } else if(array_key_exists(str_replace('Multi', '', $collectionKey->getConfiguration()->getName()), $importData)) {
                    if ($collectionKey->getConfiguration()->getType() == 'quantityValue') {
                        $valueData = explode(' ', $importData[str_replace('Multi', '', $collectionKey->getConfiguration()->getName())]);
                        $valueData[0] = str_replace(',', '.', $valueData[0]);
                        $valueData[1] = str_replace(['Stück'], ['Stk.'], $valueData[1]);
                        $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\QuantityValue($valueData[0], $valueData[1]);
                    }

                    if ($collectionKey->getConfiguration()->getType() == 'inputQuantityValue') {
                        $valueData = explode(' ', $importData[str_replace('Multi', '', $collectionKey->getConfiguration()->getName())]);
                        if(count($valueData) == 2) {
                            $valueData[0] = str_replace(',', '.', $valueData[0]);
                            $valueData[1] = str_replace(['Stück'], ['Stk.'], $valueData[1]);
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\InputQuantityValue($valueData[0], $valueData[1]);
                        } else {
                            $importData[$collectionKey->getConfiguration()->getName()] = new \Pimcore\Model\DataObject\Data\InputQuantityValue($valueData[0]);
                        }
                    }

                    if($collectionKey->getConfiguration()->getType() == 'multiselect') {
                        $objectKeys[$groups->getConfiguration()->getId()][$collectionKey->getConfiguration()->getId()]['default'][] = array_key_exists(str_replace('Multi', '', $collectionKey->getConfiguration()->getName()), $importData) ? $importData[str_replace('Multi', '', $collectionKey->getConfiguration()->getName())] : null;
                        continue;
                    }

                    $objectKeys[$groups->getConfiguration()->getId()][$collectionKey->getConfiguration()->getId()]['default'][] = array_key_exists(str_replace('Multi', '', $collectionKey->getConfiguration()->getName()), $importData) ? $importData[str_replace('Multi', '', $collectionKey->getConfiguration()->getName())] : null;
                    $objectKeys[$groups->getConfiguration()->getId()][$collectionKey->getConfiguration()->getId()]['default'][] = array_key_exists($collectionKey->getConfiguration()->getName(), $importData) ? $importData[$collectionKey->getConfiguration()->getName()] : null;
                }
            }
        }

        if (count($objectKeys) !== 0) {
            $items = $dataObject->getHabaClassification()->getItems();
            if($items != $objectKeys) {
                $objectGroups = $this->extractGroups($objectKeys);
                $this->monitoringItem->getLogger()->debug(sprintf('Setting active groups: %s', var_export($objectGroups, true)));

                $this->monitoringItem->getLogger()->debug(sprintf('Preupdate: %s', var_export($items, true)));
                $this->monitoringItem->getLogger()->debug(sprintf('Updating: %s (%s) with %s', $identifier, $dataObject->getId(), var_export($objectKeys, true)));
                $dataObject->getHabaClassification()->setActiveGroups($objectGroups);
                $dataObject->getHabaClassification()->setItems($objectKeys);
                $dataObject->save();

                $dataObject2 = DataObject\ClassHabaProduct::getById($dataObject->getId(), true);
                $objectKeys2 = $dataObject2->getHabaClassification()->getItems();

                if ($objectKeys != $objectKeys2) {
                    $this->monitoringItem->getLogger()->error(sprintf('Update failed: %s (%s)', $identifier, $dataObject->getId()));
                    $this->monitoringItem->getLogger()->error(sprintf('To Update: (%s)', var_export($objectKeys, true)));
                    $this->monitoringItem->getLogger()->error(sprintf('After Update: (%s)', var_export($objectKeys2, true)));
                } else {
                    $this->monitoringItem->getLogger()->notice(sprintf('Update succeded: %s (%s)', $identifier, $dataObject->getId()));
                }
            }
        }
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

    /**
     * @param $object
     * @return array
     */
    private function getGroups($object): array
    {
        if (!$object instanceof DataObject\ClassHabaProduct) {
            return [];
        } else {
            return array_merge_recursive($object->getHabaClassification()->getGroups(), $this->getGroups($object->getParent()));
        }
    }

    /**
     * @param array $objectKeys
     * @return array
     */
    private function extractGroups(array $objectKeys): array
    {
        $groups = [];

        foreach ($objectKeys as $groupId => $objectKey) {
            $groups[$groupId] = true ;
        }

        return $groups;
    }
}
