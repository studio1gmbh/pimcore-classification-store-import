<?php

namespace Studio1\ClassificationStoreImportBundle\Classes;

use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;

class GroupKeyRelationRepository
{
    /**
     * @param int $keyId
     * @param int $groupId
     * @return void
     */
    public static function addKeyToGroup(int $keyId, int $groupId): void
    {
        $relation = new KeyGroupRelation();
        $relation->setGroupId($groupId);
        $relation->setKeyId($keyId);

        $relation->save();
    }
}
