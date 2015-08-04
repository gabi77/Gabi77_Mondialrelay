<?php

/**
 * @category   Gabi77
 * @package    Gabi77_Mondialrelay
 * @copyright  Copyright (c) 2015 gabi77 (http://www.gabi77.com)
 * @author     Gabriel Janez <contact@gabi77.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

class Gabi77_Mondialrelay_Model_Source_Attributesprice {

    public function toOptionArray() {
        $model = Mage::getResourceModel('catalog/product');
        $typeId = $model->getTypeId();

        $attributesCollection = Mage::getResourceModel('eav/entity_attribute_collection')
                ->setEntityTypeFilter($typeId)
                ->load();
        $attributes = array();
        $attributes[] = array('value' => '', 'label' => '');
        foreach ($attributesCollection as $attribute) {
            if($attribute->getFrontendInput() == 'price') {
                $code = $attribute->getAttributeCode();
                $attributes[] = array('value' => $code, 'label' => $code);
            }
        }

        return $attributes;
    }

}