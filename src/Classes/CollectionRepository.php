<?php

namespace Studio1\ClassificationStoreImportBundle\Classes;

use Pimcore\Model\DataObject\Classificationstore\CollectionConfig;

class CollectionRepository
{
    /**
     * @param string $name
     * @param int $storeConfigId
     * @return CollectionConfig
     * @throws \Exception
     */
    public static function getOrCreateByName(string $name, int $storeConfigId): CollectionConfig
    {
        $collection = CollectionConfig::getByName($name, $storeConfigId, true);
        if (!$collection) {
            $collection = new CollectionConfig();
            $collection->setName($name);
            $collection->setStoreId($storeConfigId);
            $collection->save();
        }
        return $collection;
    }
}
