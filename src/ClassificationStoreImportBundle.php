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

namespace Studio1\ClassificationStoreImportBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class ClassificationStoreImportBundle extends AbstractPimcoreBundle
{
    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @return string
     */
    public function getNiceName(): string
    {
        return 'Studio1 Classification Store Import Bundle';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Bundle to import classification store';
    }

    /**
     * @return array|\Pimcore\Routing\RouteReferenceInterface[]|string[]
     */
    public function getJsPaths(): array
    {
        return [
        ];
    }
}
