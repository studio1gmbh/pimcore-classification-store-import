<?php

namespace Studio1\ClassificationStoreImportBundle\Classes;

use Pimcore\Model\DataObject\Classificationstore\GroupConfig;

class GroupRepository
{
    /**
     * @param array $item
     * @param int $storeConfigId
     * @return GroupConfig
     * @throws \Exception
     */
    public static function getOrCreateByName(array $item, int $storeConfigId): GroupConfig
    {
        $group = GroupConfig::getByName($item['code'], $storeConfigId, true);
        if (!$group) {
            $group = new GroupConfig();
            $group->setName($item['code']);
            $group->setDescription($item['sapId']);
            $group->setStoreId($storeConfigId);
            $group->save();
        }
        return $group;
    }

    /**
     * @param $name
     * @param int $storeConfigId
     * @return GroupConfig
     * @throws \Exception
     */
    public static function getByName($name, int $storeConfigId): GroupConfig
    {
        return GroupConfig::getByName($name, $storeConfigId, true);
    }
}
