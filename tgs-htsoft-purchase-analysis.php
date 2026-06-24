<?php
/**
 * Plugin Name: TGS HTSoft Purchase Analysis
 * Plugin URI: https://bizgpt.vn/
 * Description: Import 4 file Excel HTSoft de cap nhat du lieu Phan tich mua hang.
 * Version: 1.0.0
 * Author: BIZGPT_AI
 * Author URI: https://bizgpt.vn/
 * License: GPL v2 or later
 * Text Domain: tgs-htsoft-purchase-analysis
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TGS_HTSOFT_PA_VERSION', '1.0.0');
define('TGS_HTSOFT_PA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TGS_HTSOFT_PA_PLUGIN_URL', plugin_dir_url(__FILE__));

class TGS_HTSoft_Purchase_Analysis
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('tgs_shop_dashboard_routes', [$this, 'add_dashboard_routes']);
        add_filter('tgs_shop_workflow_nav', [$this, 'add_mega_nav_item'], 20, 2);
        add_action('tgs_shop_sidebar_menu', [$this, 'add_sidebar_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_tgs_htsoft_pa_save_rows', [$this, 'ajax_save_rows']);
    }

    public function add_dashboard_routes($routes)
    {
        $routes['purchase-htsoft-analysis-import'] = [
            'Phan tich cac file Excel tu HTSoft',
            TGS_HTSOFT_PA_PLUGIN_DIR . 'admin-views/import.php',
        ];

        return $routes;
    }

    public function add_mega_nav_item($workflow_nav, $current_view)
    {
        unset($current_view);

        if (empty($workflow_nav['purchase']['sections']) || !is_array($workflow_nav['purchase']['sections'])) {
            return $workflow_nav;
        }

        foreach ($workflow_nav['purchase']['sections'] as &$section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $item) {
                if (($item['view'] ?? '') === 'purchase-htsoft-analysis-import') {
                    return $workflow_nav;
                }
            }
        }
        unset($section);

        $item = [
            'view' => 'purchase-htsoft-analysis-import',
            'label' => 'Nhập Excel HTSoft',
            'icon' => 'bx bx-spreadsheet',
            'active_views' => ['purchase-htsoft-analysis-import'],
        ];

        foreach ($workflow_nav['purchase']['sections'] as &$section) {
            if (($section['heading'] ?? '') !== 'Phiếu mua' || empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            $items = [];
            foreach ($section['items'] as $existing_item) {
                $items[] = $existing_item;
                if (($existing_item['view'] ?? '') === 'purchase-analysis') {
                    $items[] = $item;
                }
            }
            $section['items'] = $items;
            unset($section);
            return $workflow_nav;
        }
        unset($section);

        $workflow_nav['purchase']['sections'][0]['items'][] = $item;
        return $workflow_nav;
    }

    public function add_sidebar_menu($current_view)
    {
        $is_active = $current_view === 'purchase-htsoft-analysis-import';
        ?>
        <li class="menu-item <?php echo $is_active ? 'active' : ''; ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=tgs-shop-management&view=purchase-htsoft-analysis-import')); ?>" class="menu-link">
                <i class="bx bx-spreadsheet me-1"></i>
                <div>Excel HTSoft mua hàng</div>
            </a>
        </li>
        <?php
    }

    public function enqueue_assets($hook)
    {
        if (strpos((string) $hook, 'tgs-shop-management') === false) {
            return;
        }

        $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';
        if ($current_view !== 'purchase-htsoft-analysis-import') {
            return;
        }

        wp_enqueue_style(
            'tgs-htsoft-pa-admin',
            TGS_HTSOFT_PA_PLUGIN_URL . 'assets/css/import.css',
            [],
            TGS_HTSOFT_PA_VERSION . '.' . filemtime(TGS_HTSOFT_PA_PLUGIN_DIR . 'assets/css/import.css')
        );

        wp_enqueue_script(
            'tgs-htsoft-pa-admin',
            TGS_HTSOFT_PA_PLUGIN_URL . 'assets/js/import.js',
            ['jquery'],
            TGS_HTSOFT_PA_VERSION . '.' . filemtime(TGS_HTSOFT_PA_PLUGIN_DIR . 'assets/js/import.js'),
            true
        );

        wp_localize_script('tgs-htsoft-pa-admin', 'tgsHtsoftPurchaseAnalysis', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tgs_htsoft_pa_nonce'),
        ]);
    }

    public function ajax_save_rows()
    {
        $this->verify_request();

        $type = sanitize_key($_POST['import_type'] ?? '');
        $allowed = ['sales_week', 'sales_month', 'shop_analysis', 'warehouse_analysis'];
        if (!in_array($type, $allowed, true)) {
            wp_send_json_error(['message' => 'Loai file khong hop le.'], 400);
        }

        $raw_rows = json_decode(wp_unslash((string) ($_POST['rows'] ?? '')), true);
        if (!is_array($raw_rows) || empty($raw_rows)) {
            wp_send_json_error(['message' => 'Khong co dong nao de luu.'], 400);
        }

        global $wpdb;
        $table = $this->table_name();
        if (!$this->table_exists($table)) {
            wp_send_json_error(['message' => 'Bang du lieu chua ton tai. Hay kich hoat/cap nhat plugin tgs_shop_management truoc.'], 500);
        }

        $now = current_time('mysql');
        $user_id = get_current_user_id();
        $saved = 0;
        $skipped = 0;
        $reset_missing = 0;
        $valid_rows = [];

        foreach ($raw_rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $sku = $this->normalize_sku($row['sku'] ?? '');
            if ($sku === '') {
                $skipped++;
                continue;
            }

            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            $valid_row = [
                'product_sku' => $sku,
                'sku_cache' => $sku,
                'product_name_cache' => $name,
                'month_sales_qty' => '0.000',
                'week_sales_qty' => '0.000',
                'warehouse_max_qty' => '0.000',
                'warehouse_stock_qty' => '0.000',
                'warehouse_in_transit_qty' => '0.000',
                'shop_in_transit_qty' => '0.000',
                'shop_max_qty' => '0.000',
                'shop_stock_qty' => '0.000',
            ];

            switch ($type) {
                case 'sales_week':
                    $valid_row['week_sales_qty'] = $this->db_decimal($row['qty'] ?? 0);
                    break;
                case 'sales_month':
                    $valid_row['month_sales_qty'] = $this->db_decimal($row['qty'] ?? 0);
                    break;
                case 'shop_analysis':
                    $valid_row['shop_stock_qty'] = $this->db_decimal($row['stock_qty'] ?? 0);
                    $valid_row['shop_in_transit_qty'] = $this->db_decimal($row['in_transit_qty'] ?? 0);
                    $valid_row['shop_max_qty'] = $this->db_decimal($row['max_qty'] ?? 0);
                    break;
                case 'warehouse_analysis':
                    $valid_row['warehouse_stock_qty'] = $this->db_decimal($row['stock_qty'] ?? 0);
                    $valid_row['warehouse_in_transit_qty'] = $this->db_decimal($row['in_transit_qty'] ?? 0);
                    $valid_row['warehouse_max_qty'] = $this->db_decimal($row['max_qty'] ?? 0);
                    break;
            }

            $valid_rows[] = $valid_row;
        }

        if (!empty($valid_rows) && in_array($type, ['sales_week', 'sales_month'], true)) {
            $reset_missing = $this->reset_sales_qty_for_missing_skus($table, $type, array_column($valid_rows, 'product_sku'), $user_id, $now);
        }

        foreach (array_chunk($valid_rows, 500) as $chunk) {
            $this->bulk_upsert($table, $type, $chunk, $user_id, $now);
            $saved += count($chunk);
        }

        $message = 'Da luu ' . number_format($saved) . ' ma hang';
        if ($reset_missing > 0) {
            $message .= ', reset ' . number_format($reset_missing) . ' ma khong co trong file ve 0';
        }
        $message .= $skipped ? ', bo qua ' . number_format($skipped) . ' dong.' : '.';

        wp_send_json_success([
            'message' => $message,
            'saved' => $saved,
            'skipped' => $skipped,
            'reset_missing' => $reset_missing,
        ]);
    }

    private function verify_request()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Khong du quyen.'], 403);
        }

        $nonce = sanitize_text_field((string) ($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'tgs_htsoft_pa_nonce')) {
            wp_send_json_error(['message' => 'Nonce khong hop le.'], 403);
        }
    }

    private function table_name()
    {
        global $wpdb;
        return defined('TGS_TABLE_GLOBAL_PURCHASE_ANALYSIS_HTSOFT')
            ? TGS_TABLE_GLOBAL_PURCHASE_ANALYSIS_HTSOFT
            : $wpdb->base_prefix . 'global_purchase_analysis_htsoft';
    }

    private function table_exists($table)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function normalize_sku($sku)
    {
        return strtoupper(trim((string) $sku));
    }

    private function decimal($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function db_decimal($value)
    {
        return number_format($this->decimal($value), 3, '.', '');
    }

    private function bulk_upsert($table, $type, array $rows, $user_id, $now)
    {
        if (empty($rows)) {
            return;
        }

        global $wpdb;

        $note = sanitize_text_field((string) ($_POST['note'] ?? ''));
        $columns = [
            'product_sku',
            'sku_cache',
            'product_name_cache',
            'month_sales_qty',
            'week_sales_qty',
            'warehouse_max_qty',
            'warehouse_stock_qty',
            'warehouse_in_transit_qty',
            'shop_in_transit_qty',
            'shop_max_qty',
            'shop_stock_qty',
            'note',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $placeholder = '(' . implode(',', array_fill(0, count($columns), '%s')) . ')';
        $placeholders = [];
        $values = [];

        foreach ($rows as $row) {
            $placeholders[] = $placeholder;
            foreach ($columns as $column) {
                if ($column === 'note') {
                    $values[] = $note;
                } elseif ($column === 'created_by' || $column === 'updated_by') {
                    $values[] = (string) ((int) $user_id);
                } elseif ($column === 'created_at' || $column === 'updated_at') {
                    $values[] = $now;
                } else {
                    $values[] = (string) ($row[$column] ?? '');
                }
            }
        }

        $updates = [
            'sku_cache = VALUES(sku_cache)',
            "product_name_cache = IF(VALUES(product_name_cache) <> '', VALUES(product_name_cache), product_name_cache)",
            "note = IF(VALUES(note) <> '', VALUES(note), note)",
            'updated_by = VALUES(updated_by)',
            'updated_at = VALUES(updated_at)',
        ];

        switch ($type) {
            case 'sales_week':
                $updates[] = 'week_sales_qty = VALUES(week_sales_qty)';
                break;
            case 'sales_month':
                $updates[] = 'month_sales_qty = VALUES(month_sales_qty)';
                break;
            case 'shop_analysis':
                $updates[] = 'shop_stock_qty = VALUES(shop_stock_qty)';
                $updates[] = 'shop_in_transit_qty = VALUES(shop_in_transit_qty)';
                $updates[] = 'shop_max_qty = VALUES(shop_max_qty)';
                break;
            case 'warehouse_analysis':
                $updates[] = 'warehouse_stock_qty = VALUES(warehouse_stock_qty)';
                $updates[] = 'warehouse_in_transit_qty = VALUES(warehouse_in_transit_qty)';
                $updates[] = 'warehouse_max_qty = VALUES(warehouse_max_qty)';
                break;
        }

        $sql = "INSERT INTO {$table} (`" . implode('`,`', $columns) . "`) VALUES "
            . implode(',', $placeholders)
            . ' ON DUPLICATE KEY UPDATE '
            . implode(', ', $updates);

        $wpdb->query($wpdb->prepare($sql, ...$values));
        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'Loi DB: ' . $wpdb->last_error], 500);
        }
    }

    private function reset_sales_qty_for_missing_skus($table, $type, array $imported_skus, $user_id, $now)
    {
        global $wpdb;

        $column = '';
        if ($type === 'sales_week') {
            $column = 'week_sales_qty';
        } elseif ($type === 'sales_month') {
            $column = 'month_sales_qty';
        }

        if ($column === '') {
            return 0;
        }

        $imported_map = [];
        foreach ($imported_skus as $sku) {
            $key = $this->normalize_sku($sku);
            if ($key !== '') {
                $imported_map[$key] = true;
            }
        }

        if (empty($imported_map)) {
            return 0;
        }

        $existing_skus = $wpdb->get_col("SELECT product_sku FROM {$table}");
        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'Loi DB: ' . $wpdb->last_error], 500);
        }

        $missing = [];
        foreach ((array) $existing_skus as $sku) {
            $key = $this->normalize_sku($sku);
            if ($key !== '' && !isset($imported_map[$key])) {
                $missing[$key] = $key;
            }
        }

        if (empty($missing)) {
            return 0;
        }

        foreach (array_chunk(array_values($missing), 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%s'));
            $args = array_merge([(int) $user_id, $now], $chunk);
            $sql = "UPDATE {$table}
                    SET `{$column}` = 0,
                        updated_by = %d,
                        updated_at = %s
                    WHERE UPPER(TRIM(product_sku)) IN ({$placeholders})";
            $wpdb->query($wpdb->prepare($sql, ...$args));
            if ($wpdb->last_error) {
                wp_send_json_error(['message' => 'Loi DB: ' . $wpdb->last_error], 500);
            }
        }

        return count($missing);
    }
}

TGS_HTSoft_Purchase_Analysis::get_instance();
