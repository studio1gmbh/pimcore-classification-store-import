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

use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;

class GroupKeyRelationRepository
{
    /**
     * @param int $keyId
     * @param int $groupId
     *
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
