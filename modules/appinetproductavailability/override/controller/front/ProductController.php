<?php

class ProductController extends ProductControllerCore
{

    /**
     * Keep the purchase control visible while preventing a product or
     * combination marked unavailable by appinetproductavailability from being
     * added to the cart. Creative Elements uses add_to_cart_url to set the
     * native disabled state of its product controls.
     */
    public function getTemplateVarProduct()
    {
        $presentedProduct = parent::getTemplateVarProduct();

        if (!$presentedProduct instanceof ArrayAccess) {
            return $presentedProduct;
        }

        $availabilityModule = Module::isInstalled('appinetproductavailability')
            ? Module::getInstanceByName('appinetproductavailability')
            : false;

        if (!$availabilityModule instanceof Module || !method_exists($availabilityModule, 'isPurchasable')) {
            return $presentedProduct;
        }

        $idProduct = (int) $presentedProduct['id_product'];
        $idProductAttribute = (int) $presentedProduct['id_product_attribute'];

        if ($availabilityModule->isPurchasable($idProduct, $idProductAttribute)) {
            return $presentedProduct;
        }

        $presentedProduct->offsetSet('add_to_cart_url', null, true);
        $presentedProduct->offsetSet('appinet_product_availability_blocked', true, true);

        return $presentedProduct;
    }

    /**
     * Deleted attributes if show is "NO"
     * Module:appinetproductavailability
     */
    protected function assignAttributesGroups($product_for_template = null)
    {
        $colors = [];
        $groups = [];
        $this->combinations = [];

        // Pobranie wszystkich kombinacji produktu
        $attributes_groups = $this->product->getAttributesGroups($this->context->language->id);

        if (is_array($attributes_groups) && $attributes_groups) {
            $combination_images = $this->product->getCombinationImages($this->context->language->id);
            $combination_prices_set = [];

            foreach ($attributes_groups as $k => $row) {
                $id_product_attribute = (int) $row['id_product_attribute'];

                // Pobranie dostępności kombinacji z bazy danych
                $availability = Db::getInstance()->getValue('
                SELECT available 
                FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
                WHERE id_product_attribute = ' . $id_product_attribute
                );

                // Pomijamy kombinacje oznaczone jako "niedostępne" (available = 0)
                if ($availability === "0") {
                    continue;
                }

                // Color management
                if (isset($row['is_color_group']) && $row['is_color_group'] && (isset($row['attribute_color']) && $row['attribute_color']) || (file_exists(_PS_COL_IMG_DIR_ . $row['id_attribute'] . '.jpg'))) {
                    $colors[$row['id_attribute']]['value'] = $row['attribute_color'];
                    $colors[$row['id_attribute']]['name'] = $row['attribute_name'];
                    if (!isset($colors[$row['id_attribute']]['attributes_quantity'])) {
                        $colors[$row['id_attribute']]['attributes_quantity'] = 0;
                    }
                    $colors[$row['id_attribute']]['attributes_quantity'] += (int) $row['quantity'];
                }

                if (!isset($groups[$row['id_attribute_group']])) {
                    $groups[$row['id_attribute_group']] = [
                        'group_name' => $row['group_name'],
                        'name' => $row['public_group_name'],
                        'group_type' => $row['group_type'],
                        'default' => -1,
                    ];
                }

                $groups[$row['id_attribute_group']]['attributes'][$row['id_attribute']] = [
                    'name' => $row['attribute_name'],
                    'html_color_code' => $row['attribute_color'],
                    'texture' => (@filemtime(_PS_COL_IMG_DIR_ . $row['id_attribute'] . '.jpg')) ? _THEME_COL_DIR_ . $row['id_attribute'] . '.jpg' : '',
                    'selected' => (isset($product_for_template['attributes'][$row['id_attribute_group']]['id_attribute']) && $product_for_template['attributes'][$row['id_attribute_group']]['id_attribute'] == $row['id_attribute']) ? true : false,
                ];

                if ($row['default_on'] && $groups[$row['id_attribute_group']]['default'] == -1) {
                    $groups[$row['id_attribute_group']]['default'] = (int) $row['id_attribute'];
                }

                if (!isset($groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']])) {
                    $groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']] = 0;
                }
                $groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']] += (int) $row['quantity'];

                $this->combinations[$row['id_product_attribute']]['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
                $this->combinations[$row['id_product_attribute']]['attributes'][] = (int) $row['id_attribute'];
                $this->combinations[$row['id_product_attribute']]['price'] = (float) $row['price'];

                if (!isset($combination_prices_set[(int) $row['id_product_attribute']])) {
                    $combination_specific_price = null;
                    Product::getPriceStatic((int) $this->product->id, false, $row['id_product_attribute'], 6, null, false, true, 1, false, null, null, null, $combination_specific_price);
                    $combination_prices_set[(int) $row['id_product_attribute']] = true;
                    $this->combinations[$row['id_product_attribute']]['specific_price'] = $combination_specific_price;
                }

                $this->combinations[$row['id_product_attribute']]['quantity'] = (int) $row['quantity'];
                $this->combinations[$row['id_product_attribute']]['reference'] = $row['reference'];
            }

            $this->context->smarty->assign([
                'groups' => $groups,
                'colors' => (count($colors)) ? $colors : false,
                'combinations' => $this->combinations,
                'combinationImages' => $combination_images,
            ]);
        } else {
            $this->context->smarty->assign([
                'groups' => [],
                'colors' => false,
                'combinations' => [],
                'combinationImages' => [],
            ]);
        }
    }

    /**
     * Allow or Not to buy product
     * Module:appinetproductavailability
     */
    public function initContent()
    {

        parent::initContent();

        $id_product = (int)Tools::getValue('id_product');
        if (!$id_product) {
            $id_product = (int)$this->product->id;
        }

        $allow = Db::getInstance()->getValue('
            SELECT allow_buying
            FROM ' . _DB_PREFIX_ . 'appinet_productavailability
            WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = 0
        ');

        $this->context->smarty->assign('productAllowBuying', $allow === false ? 1 : (int)$allow);
    }

}
