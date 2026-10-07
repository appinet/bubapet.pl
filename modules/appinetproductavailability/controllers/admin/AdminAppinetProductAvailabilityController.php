<?php

class AdminAppinetProductAvailabilityController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'appinet_productavailability';
        $this->className = '';
        $this->identifier = 'id';
        $this->bootstrap = true;

        parent::__construct();
    }

    public function renderList()
    {
        $this->fields_list = [
            'id_product' => ['title' => $this->l('Product ID'), 'align' => 'center'],
            'available' => ['title' => $this->l('Available'), 'align' => 'center', 'type' => 'bool']
        ];

        return parent::renderList();
    }

    public function initContent()
    {
        parent::initContent();

        $page = max(1, (int)Tools::getValue('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
        $searchQuery = Tools::getValue('search_query', '');
        $viewMode = $this->getViewMode();

        $products = $this->getProductsWithCombinations($limit, $offset, $searchQuery, $viewMode);
        $totalProducts = $this->getTotalProducts($searchQuery, $viewMode);
        $totalPages = max(1, (int)ceil($totalProducts / $limit));
        $stats = $this->getDashboardStats();
        $integrityIssues = $this->getIntegrityIssues();
        $ajaxUrl = $this->context->link->getAdminLink('AdminAppinetProductAvailability');

        $this->context->smarty->assign([
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'viewMode' => $viewMode,
            'stats' => $stats,
            'integrityIssues' => $integrityIssues,
            'ajax_url' => $ajaxUrl,
            'token' => Tools::getAdminTokenLite('AdminAppinetProductAvailability'),
            'pagination' => $this->getPagination($page, $totalPages, $searchQuery, $viewMode),
            'filterLinks' => $this->getFilterLinks($searchQuery, $viewMode, $ajaxUrl),
            'hasActiveFilters' => ($searchQuery !== '' || $viewMode !== 'all'),
        ]);

        $templatePath = _PS_MODULE_DIR_ . 'appinetproductavailability/views/templates/admin/product_list.tpl';
        $this->content = $this->context->smarty->fetch($templatePath);
    }

    public function getProductsWithCombinations($limit, $offset, $searchQuery = '', $viewMode = 'all')
    {
        $whereClause = $this->buildProductSearchWhereClause($searchQuery);
        $havingClause = $this->buildViewModeHavingClause($viewMode);

        $products = Db::getInstance()->executeS('
            SELECT
                p.id_product,
                pl.name,
                COUNT(DISTINCT pa.id_product_attribute) AS combinations_count,
                SUM(CASE WHEN COALESCE(ap.available, 1) = 0 THEN 1 ELSE 0 END) AS unavailable_count,
                SUM(CASE WHEN ap.estimated_delivery_time IS NOT NULL THEN 1 ELSE 0 END) AS eta_count,
                SUM(CASE WHEN sa.id_stock_available IS NULL THEN 1 ELSE 0 END) AS missing_stock_rows,
                SUM(
                    CASE
                        WHEN sa.id_stock_available IS NOT NULL
                         AND sa.out_of_stock <> CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END
                        THEN 1
                        ELSE 0
                    END
                ) AS mismatch_count,
                MAX(CASE WHEN ap_product.allow_buying = 0 THEN 1 ELSE 0 END) AS product_blocked
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON p.id_product = pl.id_product
               AND pl.id_lang = ' . (int)$this->context->language->id . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                ON pa.id_product = p.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                ON pa.id_product = ap.id_product
               AND pa.id_product_attribute = ap.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.id_product = pa.id_product
               AND sa.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap_product
                ON ap_product.id_product = p.id_product
               AND ap_product.id_product_attribute = 0
            WHERE 1 ' . $whereClause . '
            GROUP BY p.id_product, pl.name
            HAVING COUNT(DISTINCT pa.id_product_attribute) > 0 ' . $havingClause . '
            ORDER BY mismatch_count DESC, missing_stock_rows DESC, unavailable_count DESC, pl.name ASC
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset);

        foreach ($products as &$product) {
            $productId = (int)$product['id_product'];
            $product['combinations'] = Db::getInstance()->executeS('
                SELECT
                    pa.id_product_attribute,
                    GROUP_CONCAT(DISTINCT CONCAT(agl.name, ":", al.name) SEPARATOR ", ") AS name,
                    COALESCE(ap.available, 1) AS available,
                    ap.estimated_delivery_time,
                    sa.out_of_stock,
                    sa.quantity,
                    CASE WHEN sa.id_stock_available IS NULL THEN 1 ELSE 0 END AS is_missing_stock_row,
                    CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END AS expected_out_of_stock,
                    CASE
                        WHEN sa.id_stock_available IS NOT NULL
                         AND sa.out_of_stock <> CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END
                        THEN 1
                        ELSE 0
                    END AS has_out_of_stock_mismatch
                FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                    ON pa.id_product_attribute = pac.id_product_attribute
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a
                    ON pac.id_attribute = a.id_attribute
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                    ON a.id_attribute = al.id_attribute
                   AND al.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                    ON a.id_attribute_group = agl.id_attribute_group
                   AND agl.id_lang = ' . (int)$this->context->language->id . '
                LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                    ON pa.id_product = ap.id_product
                   AND pa.id_product_attribute = ap.id_product_attribute
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                    ON sa.id_product = pa.id_product
                   AND sa.id_product_attribute = pa.id_product_attribute
                WHERE pa.id_product = ' . $productId . '
                GROUP BY pa.id_product_attribute, ap.available, ap.estimated_delivery_time, sa.id_stock_available, sa.out_of_stock, sa.quantity
                ORDER BY has_out_of_stock_mismatch DESC, is_missing_stock_row DESC, available ASC, name ASC'
            );

            $product['edit_link'] = $this->context->link->getAdminLink(
                'AdminProducts',
                true,
                [],
                ['id_product' => $productId, 'updateproduct' => 1]
            );
            $product['status_tone'] = $this->resolveProductStatusTone($product);
        }

        return $products;
    }

    public function getPagination($currentPage, $totalPages, $searchQuery = '', $viewMode = 'all')
    {
        $pagination = [];
        $baseUrl = $this->buildUrl([
            'search_query' => $searchQuery,
            'view_mode' => $viewMode,
        ]);

        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination[] = [
                'page' => $i,
                'is_current' => ($i == $currentPage),
                'url' => $baseUrl . '&page=' . (int)$i,
            ];
        }

        return $pagination;
    }

    public function getTotalProducts($searchQuery = '', $viewMode = 'all')
    {
        $whereClause = $this->buildProductSearchWhereClause($searchQuery);
        $havingClause = $this->buildViewModeHavingClause($viewMode);

        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*)
            FROM (
                SELECT p.id_product
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON p.id_product = pl.id_product
                   AND pl.id_lang = ' . (int)$this->context->language->id . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                    ON pa.id_product = p.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                    ON pa.id_product = ap.id_product
                   AND pa.id_product_attribute = ap.id_product_attribute
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                    ON sa.id_product = pa.id_product
                   AND sa.id_product_attribute = pa.id_product_attribute
                LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap_product
                    ON ap_product.id_product = p.id_product
                   AND ap_product.id_product_attribute = 0
                WHERE 1 ' . $whereClause . '
                GROUP BY p.id_product, pl.name
                HAVING COUNT(DISTINCT pa.id_product_attribute) > 0 ' . $havingClause . '
            ) filtered_products
        ');
    }

    public function getDashboardStats()
    {
        $stats = Db::getInstance()->getRow('
            SELECT
                COUNT(DISTINCT p.id_product) AS total_products,
                COUNT(DISTINCT pa.id_product_attribute) AS total_combinations,
                SUM(CASE WHEN COALESCE(ap.available, 1) = 0 THEN 1 ELSE 0 END) AS unavailable_combinations,
                COUNT(DISTINCT CASE WHEN COALESCE(ap.available, 1) = 0 THEN p.id_product END) AS products_with_unavailable,
                COUNT(DISTINCT CASE WHEN ap_product.allow_buying = 0 THEN p.id_product END) AS blocked_products,
                SUM(CASE WHEN ap.estimated_delivery_time IS NOT NULL THEN 1 ELSE 0 END) AS combinations_with_eta,
                SUM(CASE WHEN sa.id_stock_available IS NULL THEN 1 ELSE 0 END) AS missing_stock_rows,
                SUM(
                    CASE
                        WHEN sa.id_stock_available IS NOT NULL
                         AND sa.out_of_stock <> CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END
                        THEN 1
                        ELSE 0
                    END
                ) AS out_of_stock_mismatches
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                ON pa.id_product = p.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                ON pa.id_product = ap.id_product
               AND pa.id_product_attribute = ap.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.id_product = pa.id_product
               AND sa.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap_product
                ON ap_product.id_product = p.id_product
               AND ap_product.id_product_attribute = 0
        ');

        return array_map('intval', $stats ?: []);
    }

    public function getIntegrityIssues()
    {
        $issues = Db::getInstance()->executeS('
            SELECT
                p.id_product,
                pl.name AS product_name,
                pa.id_product_attribute,
                GROUP_CONCAT(DISTINCT CONCAT(agl.name, ":", al.name) SEPARATOR ", ") AS combination_name,
                COALESCE(ap.available, 1) AS available,
                sa.out_of_stock,
                CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END AS expected_out_of_stock,
                CASE WHEN sa.id_stock_available IS NULL THEN 1 ELSE 0 END AS is_missing_stock_row,
                ap.estimated_delivery_time
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            INNER JOIN `' . _DB_PREFIX_ . 'product` p
                ON p.id_product = pa.id_product
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON p.id_product = pl.id_product
               AND pl.id_lang = ' . (int)$this->context->language->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a
                ON pac.id_attribute = a.id_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                ON a.id_attribute = al.id_attribute
               AND al.id_lang = ' . (int)$this->context->language->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON a.id_attribute_group = agl.id_attribute_group
               AND agl.id_lang = ' . (int)$this->context->language->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'appinet_productavailability` ap
                ON pa.id_product = ap.id_product
               AND pa.id_product_attribute = ap.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.id_product = pa.id_product
               AND sa.id_product_attribute = pa.id_product_attribute
            WHERE sa.id_stock_available IS NULL
               OR sa.out_of_stock <> CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END
            GROUP BY p.id_product, pl.name, pa.id_product_attribute, ap.available, sa.id_stock_available, sa.out_of_stock, ap.estimated_delivery_time
            ORDER BY is_missing_stock_row DESC, p.id_product ASC, pa.id_product_attribute ASC
            LIMIT 10
        ');

        if (!$issues) {
            return [];
        }

        foreach ($issues as &$issue) {
            $issue['edit_link'] = $this->context->link->getAdminLink(
                'AdminProducts',
                true,
                [],
                ['id_product' => (int)$issue['id_product'], 'updateproduct' => 1]
            );
        }

        return $issues;
    }

    public function getFilterLinks($searchQuery, $viewMode, $ajaxUrl)
    {
        $links = [];
        $items = [
            'all' => 'Wszystkie',
            'unavailable' => 'Niedostępne kombinacje',
            'mismatch' => 'Błędy out_of_stock',
            'missing_stock' => 'Brak rekordu magazynowego',
            'blocked' => 'Zakup zablokowany',
            'eta' => 'Z ustawioną datą dostawy',
        ];

        foreach ($items as $key => $label) {
            $links[] = [
                'label' => $label,
                'is_active' => $viewMode === $key,
                'url' => $this->buildUrl([
                    'search_query' => $searchQuery,
                    'view_mode' => $key,
                ], $ajaxUrl),
            ];
        }

        return $links;
    }

    private function buildProductSearchWhereClause($searchQuery)
    {
        if ($searchQuery === '') {
            return '';
        }

        return ' AND (
            pl.name LIKE "%' . pSQL($searchQuery) . '%"
            OR p.id_product = ' . (int)$searchQuery . '
        )';
    }

    private function buildViewModeHavingClause($viewMode)
    {
        switch ($viewMode) {
            case 'unavailable':
                return ' AND SUM(CASE WHEN COALESCE(ap.available, 1) = 0 THEN 1 ELSE 0 END) > 0';
            case 'mismatch':
                return ' AND SUM(CASE WHEN sa.id_stock_available IS NOT NULL AND sa.out_of_stock <> CASE WHEN COALESCE(ap.available, 1) = 0 THEN 0 ELSE 2 END THEN 1 ELSE 0 END) > 0';
            case 'missing_stock':
                return ' AND SUM(CASE WHEN sa.id_stock_available IS NULL THEN 1 ELSE 0 END) > 0';
            case 'blocked':
                return ' AND MAX(CASE WHEN ap_product.allow_buying = 0 THEN 1 ELSE 0 END) = 1';
            case 'eta':
                return ' AND SUM(CASE WHEN ap.estimated_delivery_time IS NOT NULL THEN 1 ELSE 0 END) > 0';
            default:
                return '';
        }
    }

    public function getViewMode()
    {
        $viewMode = (string)Tools::getValue('view_mode', 'all');
        $allowedModes = ['all', 'unavailable', 'mismatch', 'missing_stock', 'blocked', 'eta'];

        if (!in_array($viewMode, $allowedModes, true)) {
            return 'all';
        }

        return $viewMode;
    }

    private function buildUrl(array $params = [], $baseUrl = null)
    {
        if ($baseUrl === null) {
            $baseUrl = $this->context->link->getAdminLink('AdminAppinetProductAvailability');
        }

        $query = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $query[] = urlencode($key) . '=' . urlencode((string)$value);
        }

        return $query ? $baseUrl . '&' . implode('&', $query) : $baseUrl;
    }

    private function resolveProductStatusTone(array $product)
    {
        if ((int)$product['mismatch_count'] > 0 || (int)$product['missing_stock_rows'] > 0) {
            return 'danger';
        }

        if ((int)$product['unavailable_count'] > 0 || (int)$product['product_blocked'] === 1) {
            return 'warning';
        }

        return 'success';
    }

    public function ajaxProcessUpdateAvailability()
    {
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
        }

        $id_product = (int)Tools::getValue('id_product');
        $id_product_attribute = (int)Tools::getValue('id_product_attribute');
        $available = (int)Tools::getValue('available');

        Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'appinet_productavailability` (id_product, id_product_attribute, available)
            VALUES (' . (int)$id_product . ', ' . (int)$id_product_attribute . ', ' . (int)$available . ')
            ON DUPLICATE KEY UPDATE available = ' . (int)$available
        );

        if ($this->module instanceof AppinetProductAvailability) {
            $this->module->syncStockAvailabilityFlag($id_product, $id_product_attribute, $available);
        }

        die(json_encode(['success' => true]));
    }
    public function ajaxProcessUpdateAvailabilityDate() {
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
        }

        $id_product = (int)Tools::getValue('id_product');
        $id_product_attribute = (int)Tools::getValue('id_product_attribute');
        $rawDate = Tools::getValue('date'); // np. 2025-04-30T16:10

        // Zamień T na spację, dodaj ":00" jeśli brakuje sekund
        $timestamp = strtotime(str_replace('T', ' ', (string)$rawDate));
        if (!$timestamp) {
            die(json_encode(['success' => false, 'message' => 'Invalid date']));
        }
        $formattedDate = date('Y-m-d H:i:s', $timestamp);

        Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'appinet_productavailability` 
            (id_product, id_product_attribute, estimated_delivery_time)
        VALUES (
            ' . (int)$id_product . ', 
            ' . (int)$id_product_attribute . ', 
            "' . pSQL($formattedDate) . '"
        )
        ON DUPLICATE KEY UPDATE estimated_delivery_time = "' . pSQL($formattedDate) . '"
    ');

        die(json_encode(['success' => true]));
    }
    public function ajaxProcessDeleteAvailabilityDate()
    {
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token',
            ]));
        }

        $id_product = (int)Tools::getValue('id_product');
        $id_product_attribute = (int)Tools::getValue('id_product_attribute');

        if (!$id_product) {
            die(json_encode([
                'success' => false,
                'message' => 'Brakuje ID produktu',
            ]));
        }

        $sql = '
            UPDATE `' . _DB_PREFIX_ . 'appinet_productavailability`
            SET `estimated_delivery_time` = NULL
            WHERE `id_product` = ' . (int)$id_product . '
            AND `id_product_attribute` = ' . (int)$id_product_attribute;

        Db::getInstance()->execute($sql);

        die(json_encode([
            'success' => true,
        ]));
    }
    public function ajaxProcessUpdateAllowBuying(){
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
        }

        $id_product = (int)Tools::getValue('id_product');
        $allow = (int)Tools::getValue('allow'); // np. 2025-04-30T16:10

        Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'appinet_productavailability` 
            (id_product, id_product_attribute, estimated_delivery_time, allow_buying)
        VALUES (
            ' . (int)$id_product . ', 
            0, 
            NULL,
            '.$allow.'
        )
        ON DUPLICATE KEY UPDATE allow_buying = ' . (int)$allow . '
    ');

        die(json_encode(['success' => true]));
    }
    public function ajaxProcessDeleteAvailabilityDateAll()
    {
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token',
            ]));
        }

        $id_product = (int)Tools::getValue('id_product');


        if (!$id_product) {
            die(json_encode([
                'success' => false,
                'message' => 'Brakuje ID produktu',
            ]));
        }

        $sql = '
            UPDATE `' . _DB_PREFIX_ . 'appinet_productavailability`
            SET `estimated_delivery_time_all` = NULL
            WHERE `id_product` = ' . (int)$id_product;


        Db::getInstance()->execute($sql);

        die(json_encode([
            'success' => true,
        ]));
    }
    public function ajaxProcessUpdateAvailabilityDateAll() {
        $token = Tools::getValue('token');
        $validToken = Tools::getAdminTokenLite('AdminAppinetProductAvailability');

        if ($token !== $validToken) {
            die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
        }

        $id_product = (int)Tools::getValue('id_product');
        $rawDate = Tools::getValue('date'); // np. 2025-04-30T16:10

        // Zamień T na spację, dodaj ":00" jeśli brakuje sekund
        $timestamp = strtotime(str_replace('T', ' ', (string)$rawDate));
        if (!$timestamp) {
            die(json_encode(['success' => false, 'message' => 'Invalid date']));
        }
        $formattedDate = date('Y-m-d H:i:s', $timestamp);

        Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'appinet_productavailability` 
            (id_product, estimated_delivery_time_all)
        VALUES (
            ' . (int)$id_product . ', 
            "' . pSQL($formattedDate) . '"
        )
        ON DUPLICATE KEY UPDATE estimated_delivery_time_all = "' . pSQL($formattedDate) . '"
    ');

        die(json_encode(['success' => true]));
    }

}
