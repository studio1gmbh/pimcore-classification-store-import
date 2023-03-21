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

namespace Studio1\TemplatePimcoreBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class TemplatePimcoreBundle extends AbstractPimcoreBundle
{
    public function getVersion()
    {
        return '1.0.0';
    }

    public function getNiceName()
    {
        return 'Template Bundle';
    }

    public function getDescription()
    {
        return 'Template Bundle';
    }

    public function getJsPaths()
    {
        return [
        ];
    }
}
