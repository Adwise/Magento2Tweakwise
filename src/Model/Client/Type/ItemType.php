<?php
/**
 * Tweakwise & Emico (https://www.tweakwise.com/ & https://www.emico.nl/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2017 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   Proprietary and confidential, Unauthorized copying of this file, via any medium is strictly prohibited
 */

namespace Emico\Tweakwise\Model\Client\Type;

/**
 * @method LabelType[] getLabels();
 */
class ItemType extends Type
{
    /**
     * @param LabelType[]|array[] $labels
     * @return $this
     */
    public function setLabels(array $labels)
    {
        $labels = $this->normalizeArray($labels, 'label');

        $values = [];
        foreach ($labels as $value) {
            if (!$value instanceof LabelType) {
                $value = new LabelType($value);
            }

            $values[] = $value;
        }

        $this->data['labels'] = $values;
        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return (int) $this->getDataValue('itemno');
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return (string) $this->getDataValue('order');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return (string) $this->getDataValue('order');
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return (int) $this->getDataValue('price');
    }

    /**
     * @return string
     */
    public function getBrand()
    {
        return (string) $this->getDataValue('brand');
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return (string) $this->getDataValue('image');
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return (string) $this->getDataValue('url');
    }
}