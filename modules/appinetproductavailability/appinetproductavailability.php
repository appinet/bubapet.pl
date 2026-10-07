<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AppinetProductAvailability extends Module
{
    protected $_path = "";
    protected $templateAdminFile = '';
    public function __construct()
    {
        $this->name = 'appinetproductavailability';
        $this->tab = 'administration';
        $this->version = '1.1.4';
        $this->author = 'appiNET';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Oznaczanie dostępności produktu');
        $this->description = $this->l('Moduł pozwala oznaczać dostępność produktu oraz jego kombinacji.');

        $this->_path = __PS_BASE_URI__ . 'modules/' . $this->name . '/';
        $this->templateAdminFile = 'module:appinetproductavailability/views/templates/admin/product_list.tpl';
        $this->ensureDbSchema();
        $this->syncExistingStockAvailabilityFlags();
        $this->registerRuntimeHooks();

    }

    public function install()
    {
        $this->_clearCache('*');

        return parent::install() &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayShoppingCartFooter') &&
            $this->registerHook('displayCEShoppingCartFooter') &&
            $this->registerHook('actionGetProductPropertiesBefore') &&
            $this->installDb()
            && $this->installOverrides();
    }

    public function uninstall()
    {
        return $this->unregisterHook('displayAdminProductsExtra')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('displayProductAdditionalInfo')
            && $this->unregisterHook('displayHeader')
            && $this->unregisterHook('displayBackOfficeHeader')
            && $this->unregisterHook('displayShoppingCartFooter')
            && $this->unregisterHook('displayCEShoppingCartFooter')
            && $this->unregisterHook('actionGetProductPropertiesBefore')
            && $this->uninstallOverrides()
            //&& $this->uninstallDb()
            && parent::uninstall();

    }

    private function installDb()
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'appinet_productavailability` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT(10) UNSIGNED NOT NULL,
                `id_product_attribute` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `available` TINYINT(1) NOT NULL DEFAULT 1,
                `estimated_delivery_time` DATETIME NULL,
                `estimated_delivery_time_all` DATETIME NULL,
                `allow_buying` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `product_attribute` (`id_product`, `id_product_attribute`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    private function uninstallDb()
    {
        //return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'appinet_productavailability`;');
    }

    /**
     * Existing installations do not execute install() after an update, so make
     * sure the cart hook is available as soon as the updated module is loaded.
     */
    private function registerRuntimeHooks()
    {
        if (!(int) $this->id) {
            return;
        }

        foreach (['displayShoppingCartFooter', 'displayCEShoppingCartFooter', 'actionGetProductPropertiesBefore'] as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $this->registerHook($hook);
            }
        }
    }

    /**
     * Returns cart lines which were blocked after they had been added to a cart.
     * A missing availability row intentionally means that the product remains purchasable.
     */
    public function getUnavailableCartProducts(Cart $cart)
    {
        if (!(int) $cart->id || !$this->availabilityTableExists()) {
            return [];
        }

        $rows = Db::getInstance()->executeS('
            SELECT cp.id_product, cp.id_product_attribute,
                   ap_product.allow_buying,
                   ap_attribute.available
            FROM `' . _DB_PREFIX_ . 'cart_product` cp
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap_product
                ON ap_product.id_product = cp.id_product
                AND ap_product.id_product_attribute = 0
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap_attribute
                ON ap_attribute.id_product = cp.id_product
                AND ap_attribute.id_product_attribute = cp.id_product_attribute
            WHERE cp.id_cart = ' . (int) $cart->id . '
        ');

        $unavailable = [];
        foreach ((array) $rows as $row) {
            $productBlocked = $row['allow_buying'] !== null && (int) $row['allow_buying'] === 0;
            $attributeBlocked = (int) $row['id_product_attribute'] > 0
                && $row['available'] !== null
                && (int) $row['available'] === 0;

            if ($productBlocked || $attributeBlocked) {
                $key = (int) $row['id_product'] . ':' . (int) $row['id_product_attribute'];
                $unavailable[$key] = [
                    'id_product' => (int) $row['id_product'],
                    'id_product_attribute' => (int) $row['id_product_attribute'],
                ];
            }
        }

        if (!$unavailable) {
            return [];
        }

        foreach ($cart->getProducts() as $product) {
            $key = (int) $product['id_product'] . ':' . (int) $product['id_product_attribute'];
            if (isset($unavailable[$key])) {
                $unavailable[$key]['name'] = $product['name'];
            }
        }

        return array_values($unavailable);
    }

    public function getCartUnavailableMessage(Cart $cart)
    {
        $unavailable = $this->getUnavailableCartProducts($cart);
        if (!$unavailable) {
            return false;
        }

        $names = array_unique(array_filter(array_column($unavailable, 'name')));
        return 'Produkt lub wybrany wariant nie jest już dostępny: ' . implode(', ', $names)
            . '. Usuń tę pozycję z koszyka, aby przejść do płatności.';
    }

    private function availabilityTableExists()
    {
        static $exists;
        if ($exists === null) {
            $exists = (bool) Db::getInstance()->executeS(
                'SHOW TABLES LIKE "' . pSQL(_DB_PREFIX_ . 'appinet_productavailability') . '"'
            );
        }

        return $exists;
    }

    public function hookDisplayShoppingCartFooter()
    {
        return $this->renderCartAvailabilityWarning();
    }

    /**
     * Applies this module's availability rules to every product presentation.
     *
     * Product::getProductProperties() is executed before product data reaches
     * the presenter. The marker is applied to the ProductLazyArray by the
     * ProductController adapter, which disables only add_to_cart_url and keeps
     * the Creative Elements widget visible.
     */
    public function hookActionGetProductPropertiesBefore(array $params): void
    {
        if (empty($params['product']) || !is_array($params['product'])) {
            return;
        }

        $idProduct = (int) ($params['product']['id_product'] ?? 0);
        $idProductAttribute = (int) ($params['product']['id_product_attribute'] ?? 0);

        if ($idProduct <= 0 || $this->isPurchasable($idProduct, $idProductAttribute)) {
            return;
        }

        $params['product']['appinet_product_availability_blocked'] = 1;
    }

    /**
     * A missing module row deliberately means that a product remains for sale.
     */
    public function isPurchasable(int $idProduct, int $idProductAttribute = 0): bool
    {
        static $cache = [];

        $idProduct = (int) $idProduct;
        $idProductAttribute = (int) $idProductAttribute;
        $cacheKey = $idProduct . ':' . $idProductAttribute;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($idProduct <= 0 || !$this->availabilityTableExists()) {
            return $cache[$cacheKey] = true;
        }

        $productAllowBuying = Db::getInstance()->getValue('
            SELECT `allow_buying`
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
            WHERE `id_product` = ' . $idProduct . '
              AND `id_product_attribute` = 0
        ');

        if ($productAllowBuying !== false && (int) $productAllowBuying === 0) {
            return $cache[$cacheKey] = false;
        }

        if ($idProductAttribute <= 0) {
            return $cache[$cacheKey] = true;
        }

        $attributeAvailable = Db::getInstance()->getValue('
            SELECT `available`
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
            WHERE `id_product` = ' . $idProduct . '
              AND `id_product_attribute` = ' . $idProductAttribute . '
        ');

        return $cache[$cacheKey] = !($attributeAvailable !== false && (int) $attributeAvailable === 0);
    }

    /**
     * Creative Elements renders the cart opened from the header with this
     * dedicated hook, after the cart action buttons.
     */
    public function hookDisplayCEShoppingCartFooter()
    {
        return $this->renderCartAvailabilityWarning();
    }

    private function renderCartAvailabilityWarning()
    {
        $unavailable = $this->getUnavailableCartProducts($this->context->cart);
        if (!$unavailable) {
            return '';
        }

        $this->context->smarty->assign([
            'unavailable_cart_products' => $unavailable,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cart_availability_warning.tpl');
    }

    private function ensureDbSchema()
    {
        $table = _DB_PREFIX_ . 'appinet_productavailability';
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"')) {
            return;
        }

        $estimatedAllColumn = Db::getInstance()->getValue('
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "' . pSQL($table) . '"
              AND COLUMN_NAME = "estimated_delivery_time_all"
        ');

        if (!$estimatedAllColumn) {
            Db::getInstance()->execute('
                ALTER TABLE `' . bqSQL($table) . '`
                ADD COLUMN `estimated_delivery_time_all` DATETIME NULL AFTER `estimated_delivery_time`
            ');
        }
    }

    public function syncExistingStockAvailabilityFlags()
    {
        $table = _DB_PREFIX_ . 'appinet_productavailability';
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"')) {
            return;
        }

        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'stock_available` sa
            INNER JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                ON sa.id_product = ap.id_product
                AND sa.id_product_attribute = ap.id_product_attribute
            SET sa.out_of_stock = CASE
                WHEN ap.available = 0 THEN 0
                ELSE 2
            END
            WHERE ap.id_product_attribute > 0
              AND sa.out_of_stock <> CASE
                WHEN ap.available = 0 THEN 0
                ELSE 2
            END
        ');
    }

    public function syncStockAvailabilityFlag($id_product, $id_product_attribute, $available)
    {
        $id_product = (int)$id_product;
        $id_product_attribute = (int)$id_product_attribute;

        if ($id_product <= 0 || $id_product_attribute <= 0) {
            return;
        }

        $outOfStock = ((int)$available === 0) ? 0 : 2;

        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'stock_available`
            SET `out_of_stock` = ' . (int)$outOfStock . '
            WHERE `id_product` = ' . $id_product . '
              AND `id_product_attribute` = ' . $id_product_attribute
        );
    }

    public function getContent()
    {
        $page = max(1, (int)Tools::getValue('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
        $searchQuery = Tools::getValue('search_query', '');
        require_once __DIR__ . '/controllers/admin/AdminAppinetProductAvailabilityController.php';
        $adminController = new AdminAppinetProductAvailabilityController();
        $viewMode = $adminController->getViewMode();
        $products = $adminController->getProductsWithCombinations($limit, $offset, $searchQuery, $viewMode);
        $totalProducts = $adminController->getTotalProducts($searchQuery, $viewMode);
        $totalPages = max(1, (int)ceil($totalProducts / $limit));
        $ajaxUrl = $this->context->link->getAdminLink('AdminAppinetProductAvailability');

        $this->context->smarty->assign([
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'viewMode' => $viewMode,
            'stats' => $adminController->getDashboardStats(),
            'integrityIssues' => $adminController->getIntegrityIssues(),
            'ajax_url' => $ajaxUrl,
            'token' => Tools::getAdminTokenLite('AdminAppinetProductAvailability'),
            'pagination' => $adminController->getPagination($page, $totalPages, $searchQuery, $viewMode),
            'filterLinks' => $adminController->getFilterLinks($searchQuery, $viewMode, $ajaxUrl),
            'hasActiveFilters' => ($searchQuery !== '' || $viewMode !== 'all'),
        ]);
        $this->_clearCache('*');
        return $this->context->smarty->fetch($this->templateAdminFile);
    }

    private function getProductsWithCombinations($limit, $offset, $searchQuery = '')
    {
        $whereClause = $searchQuery ? 'AND pl.name LIKE "%' . pSQL($searchQuery) . '%"' : '';

        $products = Db::getInstance()->executeS('
        SELECT p.id_product, pl.name 
        FROM `' . _DB_PREFIX_ . 'product` p
        JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON p.id_product = pl.id_product
        WHERE pl.id_lang = ' . (int)$this->context->language->id . ' ' . $whereClause . '
        LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        );

        foreach ($products as &$product) {
            $product['combinations'] = Db::getInstance()->executeS('
            SELECT pa.id_product_attribute, 
                   GROUP_CONCAT(agl.name, ":", al.name SEPARATOR ", ") AS name, 
                   COALESCE(ap.available, 1) AS available
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON pac.id_attribute = a.id_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON a.id_attribute = al.id_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON a.id_attribute_group = agl.id_attribute_group
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap 
                ON pa.id_product = ap.id_product AND pa.id_product_attribute = ap.id_product_attribute
            WHERE pa.id_product = ' . (int)$product['id_product'] . ' 
                AND al.id_lang = ' . (int)$this->context->language->id . '
                AND agl.id_lang = ' . (int)$this->context->language->id . '
            GROUP BY pa.id_product_attribute'
            );
        }

        return $products;
    }
    private function getPagination($currentPage, $totalPages)
    {
        $pagination = [];

        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination[] = [
                'page' => $i,
                'is_current' => ($i == $currentPage)
            ];
        }

        return $pagination;
    }
    private function getTotalProducts($searchQuery = '')
    {
        $whereClause = 'WHERE pl.id_lang = ' . (int)$this->context->language->id;
        if ($searchQuery) {
            $whereClause .= ' AND pl.name LIKE "%' . pSQL($searchQuery) . '%"';
        }

        return (int)Db::getInstance()->getValue('
        SELECT COUNT(*) 
        FROM `' . _DB_PREFIX_ . 'product` p
        JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON p.id_product = pl.id_product
        ' . $whereClause
        );
    }

    public function hookDisplayAdminProductsExtra($params)
    {

            $id_product = (int)$params['id_product'];
            $availability = $this->getProductAvailability($id_product);
            $combinations = $this->getProductCombinations($id_product);
            $productsAllowBuing = $this->getProductAllowBuying($id_product);

            $this->context->smarty->assign([
                'id_product' => $id_product,
                'combinations' => $combinations,
                'availability' => $availability,
                'productsAllowBuing' => $productsAllowBuing,
                'ajax_url' => $this->context->link->getAdminLink(
                    'AdminAppinetProductAvailability'
                ),
                'token' => Tools::getAdminTokenLite('AdminAppinetProductAvailability')
            ]);

            return $this->display(__FILE__, 'views/templates/admin/product_availability.tpl');

    }

    public function hookActionProductUpdate($params)
    {
        if (Tools::isSubmit('availability')) {
            if (!Tools::isSubmit('token') || Tools::getValue('token') !== Tools::getAdminTokenLite('AdminAppinetProductAvailability')) {
                die('Invalid CSRF token');
            }

            $id_product = (int)$params['id_product'];
            $available = (array)Tools::getValue("availability");

            $this->updateProductAvailability($id_product, $available);
        }
    }
    public function getProductAllowBuying($id_product = 0)
    {
        if ((int)$id_product === 0) {
            return Db::getInstance()->executeS('
            SELECT DISTINCT(id_product), allow_buying, estimated_delivery_time_all
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
            WHERE id_product_attribute = 0
        ');
        }

        $row = Db::getInstance()->getRow('
            SELECT allow_buying, estimated_delivery_time_all
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
            WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = 0
        ');

        // Zwróć domyślne wartości, jeśli brak wpisu w bazie
        if ($row === false) {
            return [
                'allow_buying' => 1,
                'estimated_delivery_time_all' => ''
            ];
        }

        return [
            'allow_buying' => (int)$row['allow_buying'],
            'estimated_delivery_time_all' => $row['estimated_delivery_time_all']
        ];
    }


    private function getProductAvailability($id_product)
    {
        $results = Db::getInstance()->executeS('
        SELECT id_product_attribute, available , estimated_delivery_time
        FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
        WHERE id_product = ' . (int)$id_product
        );

        // Przekształcenie wyników na tablicę asocjacyjną klucz → wartość
        $availability = [];
        foreach ($results as $row) {
            $availability[$row['id_product_attribute']] = array(
                'display' => (int)$row['available'],
                'estimated_delivery_time' => $row['estimated_delivery_time']
            );
        }

        return $availability;
    }

    private function updateProductAvailability($id_product, $available)
    {
        if(is_array($available)){
            foreach ($available as $key => $av){

                Db::getInstance()->execute('
                    INSERT INTO `' . _DB_PREFIX_ . 'appinet_productavailability` (id_product, id_product_attribute,available)
                    VALUES (' . (int)$id_product . ', ' . (int)$key . ','.(int)$av.')
                    ON DUPLICATE KEY UPDATE available = ' . (int)$av
                );

                $this->syncStockAvailabilityFlag($id_product, $key, $av);
            }
        }

    }

    private function getProductCombinations($id_product)
    {
        $sql = '
        SELECT pa.id_product_attribute, 
               GROUP_CONCAT(agl.name, ":", al.name SEPARATOR ", ") AS attribute_name
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa
        LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac 
            ON pa.id_product_attribute = pac.id_product_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a 
            ON pac.id_attribute = a.id_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al 
            ON a.id_attribute = al.id_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl 
            ON a.id_attribute_group = agl.id_attribute_group
        WHERE pa.id_product = ' . (int)$id_product . ' 
            AND al.id_lang = ' . (int)$this->context->language->id . '
            AND agl.id_lang = ' . (int)$this->context->language->id . '
        GROUP BY pa.id_product_attribute';

        return Db::getInstance()->executeS($sql);
    }

    public function hookDisplayHeader()
    {
        $this->addFrontendAvailabilityDefinition();

        $this->context->controller->registerStylesheet(
            'module-appinetproductavailability',
            'modules/' . $this->name . '/views/css/frontend.css',
            ['media' => 'all', 'priority' => 150]
        );
        $this->context->controller->registerJavascript(
            'module-appinetproductavailability',
            'modules/' . $this->name . '/views/js/frontend-v114.js',
            ['position' => 'bottom', 'priority' => 150]
        );

    }

    /**
     * Exposes only the current product's public purchasing state to the
     * frontend adapter. It is deliberately scoped to ProductController, so
     * product cards displayed elsewhere on a page are never affected.
     */
    private function addFrontendAvailabilityDefinition(): void
    {
        if (!$this->availabilityTableExists() || !isset($this->context->controller)) {
            return;
        }

        $controller = $this->context->controller;
        if (!is_object($controller) || !method_exists($controller, 'getProduct')) {
            return;
        }

        try {
            $product = $controller->getProduct();
        } catch (Throwable $exception) {
            return;
        }

        if (!Validate::isLoadedObject($product)) {
            return;
        }

        $idProduct = (int) $product->id;
        $allowBuying = Db::getInstance()->getValue('
            SELECT `allow_buying`
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability`
            WHERE `id_product` = ' . $idProduct . '
              AND `id_product_attribute` = 0
        ');

        $combinations = [];
        $rows = Db::getInstance()->executeS('
            SELECT ap.`id_product_attribute`, ap.`available`,
                   GROUP_CONCAT(pac.`id_attribute` ORDER BY pac.`id_attribute` SEPARATOR ",") AS `attributes`
            FROM `' . _DB_PREFIX_ . 'appinet_productavailability` ap
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON pac.`id_product_attribute` = ap.`id_product_attribute`
            WHERE ap.`id_product` = ' . $idProduct . '
              AND ap.`id_product_attribute` > 0
            GROUP BY ap.`id_product_attribute`, ap.`available`
        ');

        foreach ((array) $rows as $row) {
            $idProductAttribute = (int) $row['id_product_attribute'];
            if ($idProductAttribute <= 0) {
                continue;
            }

            $attributes = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) $row['attributes'])
            )));
            sort($attributes, SORT_NUMERIC);
            $combinations[$idProductAttribute] = [
                'available' => (int) $row['available'] === 1,
                'attributes' => $attributes,
            ];
        }

        Media::addJsDef([
            'appinetProductAvailability' => [
                'idProduct' => $idProduct,
                // An absent product-level row keeps the original behaviour.
                'allowBuying' => $allowBuying === false || (int) $allowBuying === 1,
                'combinations' => $combinations,
            ],
        ]);
    }

    public function hookDisplayBackOfficeHeader()
    {

        $this->context->controller->addJS(_PS_JS_DIR_ . 'vendor/jquery.growl.js');
        $this->context->controller->addCSS(_PS_JS_DIR_ . 'vendor/jquery.growl.css');
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        if (!isset($params['product']['id_product'])) {
            return;
        }


        $id_product = (int)$params['product']['id_product'];

        $productHasCombinations = Db::getInstance()->getValue('
            SELECT COUNT(pa.id_product_attribute) 
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            WHERE pa.id_product = ' . (int) $id_product
        );
        $combinations = Db::getInstance()->executeS('
        SELECT pa.id_product_attribute, a.id_attribute,
               GROUP_CONCAT(agl.name, ":", al.name SEPARATOR ", ") AS attribute_name, 
               COALESCE(ap.available, 1) AS available, (ap.estimated_delivery_time) AS estimated_delivery_time 
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa
        LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pa.id_product_attribute = pac.id_product_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON pac.id_attribute = a.id_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON a.id_attribute = al.id_attribute
        LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON a.id_attribute_group = agl.id_attribute_group
        LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap 
            ON pa.id_product = ap.id_product AND pa.id_product_attribute = ap.id_product_attribute
        WHERE pa.id_product = ' . (int) $id_product . ' 
            AND al.id_lang = ' . (int) $this->context->language->id . '
            AND agl.id_lang = ' . (int) $this->context->language->id . '
            
        GROUP BY pa.id_product_attribute
    ');


        $showSellBtn = 0;

        if ($productHasCombinations > 0) {
            // Sprawdzamy, czy chociaż jedna kombinacja jest dostępna
            foreach ($combinations as $c) {
                if ((int) $c['available'] === 1) {
                    $showSellBtn = 1;
                    break; // wystarczy jedna dostępna kombinacja, nie musisz sprawdzać dalej
                }
            }
        } else {
            // Jeśli nie ma kombinacji, przycisk zawsze ma być widoczny
            $showSellBtn = 1;
        }

        $productAllowBuying = $this->getProductAllowBuying($id_product);

        // Jeżeli nie ma rekordu, domyślnie zezwalaj na zakup.
        $allowBuying = isset($productAllowBuying['allow_buying'])
            ? (int)$productAllowBuying['allow_buying']
            : 1;

        $this->context->smarty->assign([
            'product_combinations' => $combinations,
            'showSellBtn' => $showSellBtn,
            'allowBuying' => $allowBuying
        ]);

        return $this->display(__FILE__, 'views/templates/hook/product_availability.tpl');

    }
    /**
     * Kopiowanie pliku do override podczas instalacji modułu
     */
    private function installOverride()
    {
        /*$overrideDir = _PS_OVERRIDE_DIR_ . 'controllers/front/';
        $overrideFile = $overrideDir . 'ProductController.php';

        if (!is_dir($overrideDir)) {
            mkdir($overrideDir, 0755, true);
        }

        return copy(__DIR__ . 'ProductController.php', $overrideFile);*/

        return true;
    }

    /**
     * Usunięcie override podczas odinstalowania modułu
     */
    private function uninstallOverride()
    {
        /*$overrideFile = _PS_OVERRIDE_DIR_ . 'controllers/front/ProductController.php';

        if (file_exists($overrideFile)) {
            unlink($overrideFile);
        }
*/
        return true;
    }

}
