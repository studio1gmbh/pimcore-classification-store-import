<?php

namespace Studio1\ClassificationStoreImportBundle\Classes;

use Pimcore\Model\DataObject\Classificationstore\CollectionGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\CollectionGroupRelation\Listing as CollectionGroupRelationListing;

class CollectionGroupRelationRepository
{
    /**
     * @param int $groupId
     * @param int $collectionId
     * @return void
     */
    public static function addGroupToCollection(int $groupId, int $collectionId): void
    {
        $list = new CollectionGroupRelationListing();
        $list->setCondition('colId = ? AND groupId = ?', [$collectionId, $groupId]);
        $list->load();
        if (count($list->getList()) > 0) {
            return;
        }

        $relation = new CollectionGroupRelation();
        $relation->setColId($collectionId);
        $relation->setGroupId($groupId);

        $relation->save();
    }
}
