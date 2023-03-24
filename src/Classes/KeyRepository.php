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

use Pimcore\Model\DataObject\Classificationstore\KeyConfig;

class KeyRepository
{
    private static bool $mergeInputValue;

    /**
     * @param array $item
     * @param int $storeId
     * @param bool $mergeInputValue
     *
     * @return KeyConfig
     *
     * @throws \Exception
     */
    public static function getOrCreateByName(array $item, int $storeId, bool $mergeInputValue, $logger): KeyConfig
    {
        self::$mergeInputValue = $mergeInputValue;

        $keyConfig = KeyConfig::getByName($item['code'], $storeId, true);
        if (!$keyConfig) {
            $definition = self::getDefinition($item);
            $keyConfig = new KeyConfig();
            $keyConfig->setName($item['code']);
            $keyConfig->setDescription($item['sapId']);
            $keyConfig->setType(self::getType($item));
            $keyConfig->setStoreId($storeId);
            $keyConfig->setEnabled(1);
            $keyConfig->setDefinition($definition);
            $keyConfig->save();
        }

        if (self::getType($item) == 'select') {
            $definition = json_decode($keyConfig->getDefinition(), true);

            if (!key_exists('options', $definition)) {
                $definition['options'] = [];
                $definition['type'] = 'select';
            }

            $options = self::getOptions($item['values'], $definition['options']);
            $definition['options'] = $options;
            $keyConfig->setDefinition(json_encode($definition));

            $keyConfig->save();
        }

        return $keyConfig;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    private static function getDefinition(array $item): string
    {
        $definitionsArray = [
            'fieldtype' => self::getType($item),
            'name' => $item['code'],
            'title' => $item['nameDe'],
            'datatype' => 'data'
        ];

        if (self::getType($item) == 'select') {
            $definitionsArray['options'] = self::getOptions($item['values']);
        }

        return json_encode($definitionsArray);
    }

    /**
     * @param array $item
     *
     * @return string
     */
    private static function getType(array $item): string
    {
        $type = 'input';

        if (count($item['values']) > 0) {
            $type = 'select';
        }

        return $type;
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @return array
     */
    private static function getOptions(array $values, array $options = []): array
    {
        $optionsMerged = [];

        foreach ($options as $option) {
            $optionsMerged[$option['key']] = [
                'key' => $option['key'],
                'value' => $option['value']
            ];
        }

        foreach ($values as $value) {
            $optionsMerged[$value['key']] = [
                'key' => $value['key'],
                'value' => $value['value']
            ];
        }

        return array_values($optionsMerged);
    }
}
