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

namespace Studio1\ClassificationStoreImportBundle\Classes;

use Pimcore\Model\DataObject\Classificationstore\CollectionConfig;

class CollectionRepository
{
    /**
     * @param string $name
     * @param int $storeConfigId
     *
     * @return CollectionConfig
     *
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
