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

use Pimcore\Model\DataObject\Classificationstore\CollectionGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\CollectionGroupRelation\Listing as CollectionGroupRelationListing;

class CollectionGroupRelationRepository
{
    /**
     * @param int $groupId
     * @param int $collectionId
     *
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
