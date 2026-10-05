<?php

/**
 * Plugin Name: Variable Product Importer
 * Description: Import WooCommerce variable products via API endpoint and view import logs in admin.
 * Version: 1.1
 * Author: Seamedia
 */

if (!defined('ABSPATH')) exit;

/**
 * Run on plugin activation
 */
function sea_json_importer_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $log_table = $wpdb->prefix . 'json_import_log';
    $product_log_table = $wpdb->prefix . 'json_import_product_log';
    $order_log_table = $wpdb->prefix . 'json_order_log'; // New table for orders
    $order_status_log_table = $wpdb->prefix . 'json_order_status_log';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Import Log Table
    $sql1 = "CREATE TABLE $log_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        import_type VARCHAR(20) NOT NULL DEFAULT 'product',
        request_data MEDIUMTEXT NULL,
        imported_count INT DEFAULT 0,
        total_items INT DEFAULT 0,
        total_items_processed INT DEFAULT 0,
        status ENUM('success','error') NOT NULL DEFAULT 'success',
        message LONGTEXT NULL,
        request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_time DATETIME NULL,
        error_data LONGTEXT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Product Log Table
    $sql2 = "CREATE TABLE $product_log_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        import_log_id BIGINT(20) UNSIGNED NOT NULL,
        import_type VARCHAR(20) NOT NULL DEFAULT 'product',
        product_id BIGINT(20) UNSIGNED NULL,
        unique_id BIGINT(20) UNSIGNED NULL,
        sku VARCHAR(100) NULL,
        product_name TEXT NULL,
        action ENUM('created','updated','skipped','error','retrying') NOT NULL,
        request_data LONGTEXT NULL,
        response_data LONGTEXT NULL,
        message TEXT NULL,
        attempts INT(11) UNSIGNED DEFAULT 0,
        error_type VARCHAR(255) NULL,
        status ENUM('success','error','retrying') NOT NULL DEFAULT 'success',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY import_log_id (import_log_id),
        KEY product_id (product_id)
    ) $charset_collate;";

    // Order Log Table (New Table for orders)
    $sql3 = "CREATE TABLE $order_log_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) NOT NULL,
        woocommerce_response TEXT DEFAULT NULL,
        filtered_data TEXT DEFAULT NULL,
        innsof_response TEXT DEFAULT NULL,
        date TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
        status TINYINT(4) DEFAULT 0,  -- Pending or other status
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) $charset_collate;";

    // Order status table
    $sql4 = "CREATE TABLE $order_status_log_table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    order_note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )$charset_collate;";


    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
}

register_activation_hook(__FILE__, 'sea_json_importer_create_tables');

class JSON_Variable_Product_Importer
{
    private $current_import_id = null;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('rest_api_init', [$this, 'register_api_endpoint']);
    }


    /**
     * Register the admin page for logs (no file upload, just logs)
     */
    public function register_admin_page()
    {
        add_menu_page(
            'JSON Product Importer',
            'Product Import Logs',
            'manage_options',
            'json-product-importer',
            [$this, 'render_logs_page'],
            'dashicons-upload',
            56
        );
    }

    /**
     * Display the logs page
     */
    public function render_logs_page()
    {
        global $wpdb;

        // Define how many logs per page for both general and product logs
        $logs_per_page = 10;
        $product_logs_per_page = 10;

        // Get the current page from the query string (default is page 1)
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $product_page = isset($_GET['product_page']) ? absint($_GET['product_page']) : 1;

        // Capture filter values from the GET request for general logs
        $request_type_filter = isset($_GET['request_type']) ? $_GET['request_type'] : 'all';
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

        // Capture filter for product search
        $product_search = isset($_GET['product_search']) ? $_GET['product_search'] : '';

        // Prepare the SQL query for general logs
        $log_table = $wpdb->prefix . 'json_import_log';
        $sql = "SELECT * FROM $log_table WHERE 1=1";

        // Apply filters for general logs
        if ($request_type_filter !== 'all') {
            $sql .= $wpdb->prepare(" AND import_type = %s", $request_type_filter);
        }

        if (!empty($start_date)) {
            $sql .= $wpdb->prepare(" AND request_time >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $sql .= $wpdb->prepare(" AND request_time <= %s", $end_date . ' 23:59:59');
        }

        // Get the total number of general logs
        $total_logs_sql = "SELECT COUNT(*) FROM $log_table WHERE 1=1";
        if ($request_type_filter !== 'all') {
            $total_logs_sql .= $wpdb->prepare(" AND import_type = %s", $request_type_filter);
        }
        if (!empty($start_date)) {
            $total_logs_sql .= $wpdb->prepare(" AND request_time >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $total_logs_sql .= $wpdb->prepare(" AND request_time <= %s", $end_date . ' 23:59:59');
        }

        $total_logs = $wpdb->get_var($total_logs_sql);
        $total_pages = ceil($total_logs / $logs_per_page);

        // Add pagination and offset for general logs
        $offset = ($current_page - 1) * $logs_per_page;
        $sql .= $wpdb->prepare(" ORDER BY id DESC LIMIT %d, %d", $offset, $logs_per_page);

        // Get the filtered general logs
        $logs = $wpdb->get_results($sql);
//=====================================================================================================
        // Product logs query with search functionality
        $product_log_table = $wpdb->prefix . 'json_import_product_log'; // Assuming a product log table

        // Start building the SQL query for product logs
        $product_sql = "SELECT * FROM $product_log_table WHERE 1=1";

        // Apply product search filter for both 'product_name' and 'sku'
        if (!empty($product_search)) {
            $product_sql .= $wpdb->prepare(" AND (product_name LIKE %s OR sku LIKE %s)", '%' . $wpdb->esc_like($product_search) . '%', '%' . $wpdb->esc_like($product_search) . '%');
        }

        // Filter to include only products with 'product_id' in 'request_data' (only parent products)
        $product_sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(request_data, '$.product_id')) IS NOT NULL";

        // Get the total number of product logs
        $total_product_logs_sql = "SELECT COUNT(*) FROM $product_log_table WHERE 1=1";
        if (!empty($product_search)) {
            $total_product_logs_sql .= $wpdb->prepare(" AND (product_name LIKE %s OR sku LIKE %s)", '%' . $wpdb->esc_like($product_search) . '%', '%' . $wpdb->esc_like($product_search) . '%');
        }

        // Apply the same filter for product_name in request_data
        $total_product_logs_sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(request_data, '$.product_id')) IS NOT NULL";

        $total_product_logs = $wpdb->get_var($total_product_logs_sql);
        $total_product_pages = ceil($total_product_logs / $product_logs_per_page);

        // Add pagination and offset for product logs
        $product_offset = ($product_page - 1) * $product_logs_per_page;
        $product_sql .= $wpdb->prepare(" ORDER BY id DESC LIMIT %d, %d", $product_offset, $product_logs_per_page);

        // Get the filtered product logs
        $product_logs = $wpdb->get_results($product_sql);

    //=========================================================================================================
        $order_log_table = $wpdb->prefix . 'json_order_log';
        $order_sql = "SELECT * FROM $order_log_table ORDER BY id DESC";  // Add any filter if needed

        $order_logs = $wpdb->get_results($order_sql);
        // Define how many logs per page for the order logs
        $order_logs_per_page = 10;  // You can adjust this value

        // Get the current page for order logs (default is page 1)
        $order_page = isset($_GET['order_page']) ? absint($_GET['order_page']) : 1;

        // Get the total number of order logs
        $total_order_logs_sql = "SELECT COUNT(*) FROM $order_log_table";
        $total_order_logs = $wpdb->get_var($total_order_logs_sql);
        $total_order_pages = ceil($total_order_logs / $order_logs_per_page);

        // Add pagination and offset for order logs
        $order_offset = ($order_page - 1) * $order_logs_per_page;
        $order_sql .= $wpdb->prepare(" LIMIT %d, %d", $order_offset, $order_logs_per_page);

        // Get the paginated order logs
        $order_logs = $wpdb->get_results($order_sql);
    //===========================================================================================================

    $order_status_log_table = $wpdb->prefix . 'json_order_status_log';

    // Pagination
     $order_status_logs_per_page = 10;
     $order_status_page = isset($_GET['order_status_page']) ? absint($_GET['order_status_page']) : 1;

      // Total count
      $total_order_status_logs = $wpdb->get_var("SELECT COUNT(*) FROM $order_status_log_table");
      $total_order_status_pages = ceil($total_order_status_logs / $order_status_logs_per_page);

      // Fetch logs
      $order_status_offset = ($order_status_page - 1) * $order_status_logs_per_page;

      $order_status_logs = $wpdb->get_results(
     $wpdb->prepare(
        "SELECT *
         FROM $order_status_log_table
         ORDER BY created_at DESC
         LIMIT %d, %d",
        $order_status_offset,
        $order_status_logs_per_page
     )
   );



?>

        <div class="wrap">
            <h2>Import Logs</h2>

            <!-- Filter Form for General Logs -->
            <form method="get" action="">
                <input type="hidden" name="page" value="json-product-importer">
                <label for="request_type">Filter by Request Type:</label>
                <select name="request_type" id="request_type">
                    <option value="all" <?php selected($request_type_filter, 'all'); ?>>All</option>
                    <option value="product" <?php selected($request_type_filter, 'product'); ?>>Product Import</option>
                    <option value="stock" <?php selected($request_type_filter, 'stock'); ?>>Stock Update</option>
                </select>
                <label for="start_date">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" />
                <label for="end_date">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" />
                <input type="submit" value="Filter" class="button" />
            </form><br>

            <!-- General Logs Table -->
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Request Type</th>

                        <th>Total Items</th>
                        <th>Total Processed</th>
                        <th>Status</th>
                        <th>Time Taken</th>
                        <!-- <th>ERP Status</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <?php
                            // Convert import_type to a readable label
                            $import_type_label = $log->import_type === 'stock' ? 'Stock Update' : 'Product Import';

                            // Calculate Time Taken
                            // Check if the values are not null or empty before using strtotime
                            $start_time = ($log->request_time) ? strtotime($log->request_time) : 0;
                            $finish_time = ($log->finished_time) ? strtotime($log->finished_time) : 0;

                            if ($start_time && $finish_time) {
                                $time_taken = $finish_time - $start_time; // Time difference in seconds
                                $time_taken = gmdate("H:i:s", $time_taken); // Convert seconds to hours, minutes, seconds
                            } else {
                                $time_taken = '00:00:00'; // Default value if either time is missing or invalid
                            }
                            ?>
                            <td><?php echo esc_html($log->request_time); ?></td>
                            <td><?php echo esc_html($import_type_label); ?></td>

                            <td><?php echo esc_html($log->total_items); ?></td>
                            <td><?php echo esc_html($log->imported_count); ?></td>
                            <td><?php echo esc_html($log->status); ?></td>
                            <td><?php echo esc_html($time_taken); ?></td>
                            <!-- <td><?php //echo esc_html($log->erp_status); 
                                        ?></td> -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <p>Total Logs: <?php echo esc_html($total_logs); ?></p>
                <?php echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'total' => $total_pages, 'current' => $current_page, 'prev_text' => __('&laquo; Previous'), 'next_text' => __('Next &raquo;')]); ?>
            </div>

            <h2>Product History</h2>

            <!-- Product Search Form -->
            <form method="get" action="">
                <input type="hidden" name="page" value="json-product-importer">
                <label for="product_search">Search by Product Name or SKU:</label>
                <input type="text" name="product_search" id="product_search" value="<?php echo esc_attr($product_search); ?>" />
                <input type="submit" value="Search" class="button" />
            </form><br>

            <!-- Product Logs Table -->
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Unique ID</th>
                        <th>Product Name</th>
                        <th>Request Data</th>
                        <th>Response Data</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($product_logs as $product_log): ?>
                        <tr>
                            <td><?php echo esc_html($product_log->sku); ?></td>
                            <td><?php echo esc_html($product_log->unique_id); ?></td>
                            <td><?php echo esc_html($product_log->product_name); ?></td>
                            <td>
                                <textarea rows="3" readonly><?php echo esc_html($product_log->request_data); ?></textarea>
                            </td>
                            <td>
                                <textarea rows="3" readonly><?php echo esc_html($product_log->response_data); ?></textarea>
                            </td>
                            <td><?php echo esc_html($product_log->created_at); ?></td>
                            <td><?php echo esc_html($product_log->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <p>Total Product Logs: <?php echo esc_html($total_product_logs); ?></p>
                <?php echo paginate_links(['base' => add_query_arg('product_page', '%#%'), 'format' => '', 'total' => $total_product_pages, 'current' => $product_page, 'prev_text' => __('&laquo; Previous'), 'next_text' => __('Next &raquo;')]); ?>
            </div>
            <h2>Order Monitoring</h2>
            <!-- Order Sync Table -->
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer Name</th>
                        <th>Total</th>
                        <th>ERP Status</th>
                        <th>ERP Response</th>
                        <th>Date</th>
                        <!-- <th>Action</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_logs as $order_log): ?>
                        <tr>
                            <td><?php echo esc_html($order_log->order_id); ?></td>
                            <td>
                                <?php
                                // Decode 'filtered_data' to get customer name
                                $woocommerce_response = json_decode($order_log->woocommerce_response, true);
                                // Customer Name
                                if (isset($woocommerce_response['params']['customer'])) {
                                    $customer = $woocommerce_response['params']['customer'];
                                    $customer_name = $customer['first_name'] . ' ' . $customer['last_name'];
                                } else {
                                    $customer_name = 'Unknown';
                                }
                                echo esc_html($customer_name);
                                ?>
                            </td>
                            <td>
                                <?php
                                // Decode 'filtered_data' to get total amount
                                // Total Amount
                                $total = 0;
                                if (isset($woocommerce_response['params'])) {
                                    // Fetch the order ID directly from woocommerce_response
                                    $order_id = $woocommerce_response['params']['order_id']; // Assuming 'order_id' is available here

                                    // Get the WooCommerce order object using the order_id
                                    $order = wc_get_order($order_id);

                                    // Check if the order exists and fetch the total
                                    if ($order) {
                                        $total = $order->get_total();
                                    } else {
                                        $total = 'Order not found'; // In case the order doesn't exist
                                    }
                                }
                                echo esc_html($total);
                                ?>
                            </td>
                            <td>
                                <?php
                                // Decode 'woocomerce_response' to get ERP Status
                                // $erp_status = isset($woocommerce_response['status']) ? $woocommerce_response['status'] : 'N/A';
                                $erp_status = $order_log->status;
                                if ($erp_status == '1') {
                                    $erp_status = "success";
                                } else {
                                    $erp_status = "failed";
                                }
                                echo esc_html($erp_status);
                                ?>
                            </td>
                            <td><?php echo esc_html($order_log->innsof_response); ?></td>
                            <td><?php echo esc_html($order_log->date); ?></td>
                            <!-- <td><a href="#"></a></td> -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">
                <p>Total Order Logs: <?php echo esc_html($total_order_logs); ?></p>
                <?php
                echo paginate_links([
                    'base' => add_query_arg('order_page', '%#%'),
                    'format' => '',
                    'total' => $total_order_pages,
                    'current' => $order_page,
                    'prev_text' => __('&laquo; Previous'),
                    'next_text' => __('Next &raquo;')
                ]);
                ?>
            </div>
            <h2>Order Status Updates Logs</h2>

<table class="widefat fixed striped">
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Request ID</th>
            <th>Old Status</th>
            <th>New Status</th>
            <th>Note</th>
            <th>Date & Time</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($order_status_logs)) : ?>
            <?php foreach ($order_status_logs as $status_log) : ?>
                <tr>
                    <td><?php echo esc_html($status_log->order_id); ?></td>
                    <td><?php echo esc_html($status_log->request_id); ?></td>
                    <td><?php echo esc_html(ucwords(str_replace('-', ' ', $status_log->old_status))); ?></td>
                    <td><?php echo esc_html(ucwords(str_replace('-', ' ', $status_log->new_status))); ?></td>
                    <td><?php echo esc_html($status_log->order_note); ?></td>
                    <td><?php echo esc_html($status_log->created_at); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="6">No order status updates found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="pagination">
    <p>Total Status Logs: <?php echo esc_html($total_order_status_logs); ?></p>
    <?php
    echo paginate_links([
        'base'      => add_query_arg('order_status_page', '%#%'),
        'format'    => '',
        'total'     => $total_order_status_pages,
        'current'   => $order_status_page,
        'prev_text' => __('&laquo; Previous'),
        'next_text' => __('Next &raquo;')
    ]);
    ?>
</div>


        </div>

<?php
    }
    //============ api key ====================

    public function verify_api_key(WP_REST_Request $request)
    {
        $sent_key  = $request->get_header('x-api-key');
        $saved_key = get_option('json_import_api_key');

        if (!$saved_key) {
            return new WP_Error(
                'api_key_not_set',
                'API Key not configured on server.',
                ['status' => 500]
            );
        }

        if (!$sent_key || $sent_key !== $saved_key) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid or missing API key.',
                ['status' => 401]
            );
        }

        return true;
    }
//=====================================================================
    /**
     * Register the API endpoint for product import
     */
    public function register_api_endpoint()
    {
        register_rest_route('json-product-importer/v1', '/import', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_api_import'],
            'permission_callback' => [$this, 'verify_api_key'], // Adjust this for user permissions if needed
        ]);
        register_rest_route('json-product-importer/v1', '/stock-update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_api_stock_update'],
            'permission_callback' => [$this, 'verify_api_key'],  // Adjust this for user permissions if needed
        ]);
        register_rest_route('json-product-importer/v1', '/order-update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_api_order_update'],
            'permission_callback' => [$this, 'verify_api_key'],  // Adjust this for user permissions if needed
        ]);
          register_rest_route('json-product-importer/v1', '/return-update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_api_return_update'],
            'permission_callback' => [$this, 'verify_api_key'],  // Adjust this for user permissions if needed
        ]);
    }
    //====================== return update =============================
    
    public function handle_api_return_update(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Return update endpoint active.'
        ], 200);
    }

    //========================== order status change ===================================

   public function handle_api_order_update( WP_REST_Request $request ) {
    global $wpdb;
    $params = $request->get_json_params();

    /* ----------------------------
       VALIDATE REQUEST
    ----------------------------- */
    if ( empty( $params['order_id'] ) || empty( $params['status'] ) ) {
        return new WP_Error(
            'missing_params',
            'order_id or Status is missing.',
            [ 'status' => 400 ]
        );
    }

    $order_id = absint( $params['order_id'] );
    $request_id = isset($params['request_id']) ? absint($params['request_id']) : null;
    $new_status = sanitize_text_field( strtolower( $params['status'] ) );
    $note = isset( $params['note'] ) ? sanitize_text_field( $params['note'] ) : '';

    /* ----------------------------
       GET ORDER
    ----------------------------- */
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return new WP_Error(
            'order_not_found',
            'Order not found.',
            [ 'status' => 404 ]
        );
    }
    $old_status = $order->get_status(); // capture old status

    /* ----------------------------
       VALIDATE STATUS
    ----------------------------- */
    $valid_statuses = [
        'pending',
        'processing',
        'order-confirmed',
        'out-for-delivery',
        'completed',
        'cancelled',
        'refunded',
        'failed',
        'on-hold'
    ];

    if ( ! in_array( $new_status, $valid_statuses, true ) ) {
        return new WP_Error(
            'invalid_status',
            'Invalid order status.',
            [ 'status' => 400 ]
        );
    }

    /* ----------------------------
       UPDATE ORDER STATUS
    ----------------------------- */
    $order->update_status(
        $new_status,
        $note ?: 'Order status updated from ERP',
        true // notify customer
    );

       /* ----------------------------
       INSERT LOG
    ----------------------------- */
    $wpdb->insert(
        $wpdb->prefix . 'json_order_status_log',
        [
            'order_id'   => $order_id,
            'request_id' => $request_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'order_note' => $note,
            'created_at' => current_time('mysql'),
        ],
        [
            '%d', '%d', '%s', '%s', '%s', '%s'
        ]
    );

    /* ----------------------------
       SUCCESS RESPONSE
    ----------------------------- */
    return [
        'success'   => true,
        'order_id'  => $order_id,
        'new_status'=> $new_status,
        'message'   => 'Order status updated successfully'
    ];
  }

  //===================================  end =======================================


    public function handle_api_stock_update(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        global $wpdb;
        $log_table = $wpdb->prefix . 'json_import_log';

        // Check if the response contains products and is an array
          if (isset($data['params']['products']) && is_array($data['params']['products'])) {
            $products = $data['params']['products'];

            // Get raw JSON for logging purposes
            $json = json_encode($data); // log the request data as JSON

            $wpdb->insert($log_table, [
                'import_type'  => "stock",
                'request_data' => $json,
                'total_items'  => count($products),
                'status'       => 'success',
                'message'      => 'Import started'
            ]);
            $this->current_import_id = $wpdb->insert_id;

            // Pass the correct data to the update_stock function
            $updated_count = $this->update_stock($products); // Fix this line

            $total_items  = count($products);
            $failed_items = $total_items - $updated_count;

            // Collect error data if there are failed items
            $error_data = null;
            if ($failed_items > 0) {
                // Collect sample errors from the product log
                $errors = $wpdb->get_results(
                    $wpdb->prepare("
                    SELECT sku, message 
                    FROM {$wpdb->prefix}json_import_product_log 
                    WHERE import_log_id = %d 
                      AND status = 'error' 
                    LIMIT 5
                ", $this->current_import_id),
                    ARRAY_A
                );

                $error_data = wp_json_encode([
                    'failed_items' => $failed_items,
                    'examples'     => $errors
                ]);
            }

            // Update the log table with final status and error data if needed
            $wpdb->update($log_table, [
                'imported_count'        => $updated_count,
                'total_items_processed' => $total_items,
                'finished_time'         => current_time('mysql'),
                'message'               => 'Import finished successfully with ' . $updated_count . ' products',
                'error_data'            => $error_data
            ], ['id' => $this->current_import_id]);

            // return new WP_REST_Response(['message' => "$updated_count products stock updated successfully"], 200);
            // ---- Build ERP Response ---- //
            $erp_response = [
                'success'      => 1,
                'message'      => "$updated_count products stock updated successfully",
                'import_id'    => $this->current_import_id,
                'total_items'  => $total_items,
                'updated'      => $updated_count,
                'failed'       => $failed_items,
                'errors'       => []
            ];

            // If failed items exist, fetch FULL error list
            if ($failed_items > 0) {
                $error_rows = $wpdb->get_results(
                    $wpdb->prepare("
            SELECT sku, unique_id, message
            FROM {$wpdb->prefix}json_import_product_log
            WHERE import_log_id = %d
              AND status = 'error'
        ", $this->current_import_id),
                    ARRAY_A
                );

                $erp_response['errors'] = $error_rows;
            }

            // Send complete JSON response back to ERP
            return new WP_REST_Response($erp_response, 200);
        }

        return new WP_REST_Response('Invalid or missing product data.', 400);
    }

    /**
     * Handle the API import request
     */
    public function handle_api_import(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        global $wpdb;
        $log_table = $wpdb->prefix . 'json_import_log';

        // Check if the response contains products and is an array
        //if (isset($data['response']['products']) && is_array($data['response']['products'])) {
        if (isset($data['params']['products']) && is_array($data['params']['products'])) {

            // Get raw JSON for logging purposes
            $json = json_encode($data); // log the request data as JSON


            // No stock update data, proceed with importing products
            $wpdb->insert($log_table, [
                'import_type'  => "product",
                'request_data' => $json,
                //'total_items'  => count($data['response']['products']),
                'total_items' => count($data['params']['products']),
                'status'       => 'success',
                'message'      => 'Import started'
            ]);
            $this->current_import_id = $wpdb->insert_id;
            // $imported_count = $this->import_products($data['response']['products']);
            // $total_items  = count($data['response']['products']);
            $imported_count = $this->import_products($data['params']['products']);
            $total_items = count($data['params']['products']);
            $failed_items = $total_items - $imported_count;

            // Collect error data if there are failed items
            $error_data = null;
            if ($failed_items > 0) {
                // Collect sample errors from the product log
                $errors = $wpdb->get_results(
                    $wpdb->prepare("
                        SELECT sku, message 
                        FROM {$wpdb->prefix}json_import_product_log 
                        WHERE import_log_id = %d 
                          AND status = 'error' 
                        LIMIT 5
                    ", $this->current_import_id),
                    ARRAY_A
                );

                $error_data = wp_json_encode([
                    'failed_items' => $failed_items,
                    'examples'     => $errors
                ]);
            }

            // Update the log table with final status and error data if needed
            $wpdb->update($log_table, [
                'imported_count'        => $imported_count,
                'total_items_processed' => $total_items,
                'finished_time'         => current_time('mysql'),
                'message'               => 'Import finished successfully with ' . $imported_count . ' products',
                'error_data'            => $error_data
            ], ['id' => $this->current_import_id]);

            // return new WP_REST_Response(['message' => "$imported_count products imported successfully"], 200);
            // Build full ERP response
            $erp_response = [
                'success' => 1,
                'message' => "$imported_count products imported successfully",
                'import_id' => $this->current_import_id,
                'total_items' => $total_items,
                'imported' => $imported_count,
                'failed' => $failed_items,
                'errors' => []
            ];

            // If failed items exist, fetch their errors
            if ($failed_items > 0) {
                $error_rows = $wpdb->get_results(
                    $wpdb->prepare("
            SELECT sku, product_name, message 
            FROM {$wpdb->prefix}json_import_product_log 
            WHERE import_log_id = %d 
              AND status = 'error'
        ", $this->current_import_id),
                    ARRAY_A
                );

                $erp_response['errors'] = $error_rows;
            }

            // Return full JSON response to ERP
            return new WP_REST_Response($erp_response, 200);
        }

        return new WP_REST_Response('Invalid or missing product data.', 400);
    }


    private function update_stock($products)
    {
        global $wpdb;
        $product_log_table = $wpdb->prefix . 'json_import_product_log';
        $updated_count = 0;
        $max_attempts = 3;

        foreach ($products as $p) {
            $attempts = 0;
            $success = false;

            if ((empty($p['product_id'])) || !isset($p['quantity']) || !is_numeric($p['quantity']) || $p['quantity'] < 0) {
                $wpdb->insert($product_log_table, [
                    'import_log_id' => $this->current_import_id,
                    'sku'           => $p['code'] ?? 'N/A',
                    'unique_id'     => $p['product_id'] ?? 'N/A',
                    'product_name'  => $p['name'] ?? '',
                    'action'        => 'error',
                    'request_data'  => wp_json_encode($p),
                    'response_data' => '{}',
                    'message'       => '❌ Missing required fields or invalid quantity',
                    'status'        => 'error',
                    'error_type'    => 'permanent',
                    'attempts'      => 1
                ]);
                continue;
            }

            // Fetch product using the _unique_id meta key (unique_id is stored in product's custom meta)
            $product_id = $this->get_product_id_by_unique_id($p['product_id']); // Search by unique_id meta key
            $product    = $product_id ? wc_get_product($product_id) : null;

            if (!$product_id || !$product) {
                // Log failure when product is not found
                $wpdb->insert($product_log_table, [
                    'import_log_id' => $this->current_import_id,
                    'sku'           => $p['code'] ?? 'N/A',
                    'unique_id'     => $p['product_id'] ?? 'N/A',
                    'product_name'  => $p['name'] ?? '',
                    'action'        => 'error',
                    'request_data'  => wp_json_encode($p),
                    'response_data' => '{}',
                    'message'       => '❌ Product not found in WooCommerce for product_id: ' . ($p['product_id'] ?? 'N/A'),
                    'status'        => 'error',
                    'error_type'    => 'permanent',
                    'attempts'      => 1
                ]);
                continue;
            }
            $sku = $product->get_sku();

            $qty = (float) $p['quantity'];
            $total_stock = 0;

            // Start retry logic
            while ($attempts < $max_attempts && !$success) {
                try {
                    $attempts++;

                    // For variable products, distribute stock based on stored ratio
                    if ($product->is_type('variable')) {
                        $children = $product->get_children();
                        foreach ($children as $variation_id) {
                            $variation = wc_get_product($variation_id);
                            if ($variation) {
                                $ratio = (float) get_post_meta($variation_id, '_uom_ratio', true);
                                if ($ratio <= 0) {
                                    $ratio = 1; // fallback
                                }
                                $var_stock = floor($qty / $ratio);
                                $variation->set_manage_stock(true);
                                $variation->set_stock_quantity($var_stock);
                                $variation->set_stock_status($var_stock > 0 ? 'instock' : 'outofstock');
                                $variation->save();
                                // **Set maximum allowed quantity for WooCommerce Min/Max plugin**
                                update_post_meta($variation_id, '_wc_max_qty', $var_stock);
                                update_post_meta($variation_id, '_wc_min_qty', 1);
                                $total_stock += $var_stock;
                            }
                        }
                    } else {
                        $product->set_manage_stock(true);
                        $product->set_stock_quantity($qty);
                        $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
                        $product->save();
                        // **Set maximum allowed quantity**
                        update_post_meta($product_id, '_wc_max_qty', $qty);
                        update_post_meta($product_id, '_wc_min_qty', 1);
                        $total_stock = $qty;
                    }

                    // Success if no exceptions are thrown
                    $success = true;

                    // Log success
                    $wpdb->insert($product_log_table, [
                        'import_log_id' => $this->current_import_id,
                        'import_type'   => 'stock',
                        'product_id'    => $product_id,
                        'sku'           => $sku,
                        'product_name'  => $p['name'] ?? '',
                        'unique_id'     => $p['product_id'] ?? '',
                        'action'        => 'updated',
                        'request_data'  => wp_json_encode($p),
                        'response_data' => wp_json_encode(['stock' => $total_stock]),
                        'message'       => "Stock updated to $total_stock",
                        'status'        => 'success',
                        'error_type'    => null, // No error
                        'attempts'      => $attempts
                    ]);

                    $updated_count++;
                } catch (\Throwable $e) {
                    // Retry or log failure after max attempts
                    if ($attempts < $max_attempts && !$success) {
                        // Log retry attempt
                        $wpdb->insert($product_log_table, [
                            'import_log_id' => $this->current_import_id,
                            'sku'           => $p['code'] ?? 'N/A',
                            'action'        => 'retrying',
                            'request_data'  => wp_json_encode($p),
                            'response_data' => '{}',
                            'message'       => '🔄 Retrying... Attempt ' . $attempts . ' | Error: ' . $e->getMessage(),
                            'error_type'    => 'temporary',
                            'status'        => 'retrying',
                            'attempts'      => $attempts
                        ]);

                        sleep(1); // Optionally wait a second before retrying
                    } else {
                        // Log permanent failure after max attempts
                        $wpdb->insert($product_log_table, [
                            'import_log_id' => $this->current_import_id,
                            'sku'           => $p['code'] ?? 'N/A',
                            'action'        => 'error',
                            'request_data'  => wp_json_encode($p),
                            'response_data' => '{}',
                            'message'       => '❌ Failed after ' . $max_attempts . ' attempts | Error: ' . $e->getMessage(),
                            'error_type'    => 'permanent',
                            'status'        => 'error',
                            'attempts'      => $attempts
                        ]);
                    }
                }
            }
        }

        return $updated_count;
    }

    /**
     * Get product ID by the unique_id stored in product's meta field.
     *
     * @param string $unique_id The unique identifier for the product.
     * @return int|null The product ID if found, or null if not found.
     */
    private function get_product_id_by_unique_id($unique_id)
    {
        $product_id = null;
        if ($unique_id) {
            // Query for the product using the custom meta key '_unique_id'
            $product_id = get_posts([
                'post_type'      => 'product',  // Make sure it's a product
                'posts_per_page' => 1,          // Limit to 1 result
                'meta_key'       => '_unique_id', // The custom meta field
                'meta_value'     => $unique_id,  // The value to search for
                'fields'         => 'ids',      // Only return the product ID
            ]);
            // If a product is found, return the first ID
            $product_id = !empty($product_id) ? $product_id[0] : null;
        }

        return $product_id;
    }


    /**
     * Import products and their variations
     */
    private function import_products($products)
    {
        global $wpdb;
        $product_log_table = $wpdb->prefix . 'json_import_product_log';
        $imported_count = 0;

        foreach ($products as $product_data) {
            $attempts = 0;
            $max_attempts = 3;
            $success = false;

            $sku = $product_data['default_code'] ?? '';
            $existing_id = $sku ? wc_get_product_id_by_sku($sku) : 0;

            if (empty($sku) || (!$existing_id && empty($product_data['product_name']))) {
                $wpdb->insert($product_log_table, [
                    'import_log_id' => $this->current_import_id,
                    'sku'           => $sku ?: 'N/A',
                    'product_name'  => $product_data['product_name'] ?? 'N/A',
                    'unique_id'     => $product_data['product_id'] ?? 'N/A',
                    'action'        => 'error',
                    'request_data'  => wp_json_encode($product_data),
                    'response_data' => '{}',
                    'message'       => '❌ Missing required SKU or missing Product Name for new product creation',
                    'status'        => 'error',
                    'error_type'    => 'permanent',
                    'attempts'      => 1
                ]);
                continue;
            }

            while ($attempts < $max_attempts && !$success) {
                try {
                    $attempts++;

                    // 1) Load or create parent product
                    $product_id = $existing_id;
                    $product    = $product_id ? wc_get_product($product_id) : null;
                    $is_new_parent = false;
                    if ($product) {
                        // Ensure parent is variable type
                        if ($product->get_type() !== 'variable') {
                            wp_set_object_terms($product->get_id(), 'variable', 'product_type');
                            $product = new WC_Product_Variable($product->get_id());
                        }
                    } else {
                        // Create parent variable product
                        $product = new WC_Product_Variable();
                        $product->set_sku($sku);
                        $is_new_parent = true;
                    }

                    // Update common parent fields - only update name if provided in JSON
                    if (!empty($product_data['product_name'])) {
                        $product->set_name($product_data['product_name']);
                    }
                    $product->set_status('publish');
                    $product->set_catalog_visibility('visible');

                    // For variable products, stock should generally be managed at variation level
                    $product->set_manage_stock(false);

                    if (!empty($product_data['description'])) {
                        $product->set_description($product_data['description']);
                    }
                    // Set short description
                    if (!empty($product_data['short_description'])) {
                        $product->set_short_description($product_data['short_description']);
                    }
                    $product->save();
                    $product_id = $product->get_id();

                    // Categories
                    if (!empty($product_data['category'])) {
                        // Ensure that category is passed as an array, even if it's a single category
                        $categories = is_array($product_data['category']) ? $product_data['category'] : [$product_data['category']];
                        $this->assign_categories($product_id, $categories);
                    }


                    // Brands - only set if provided in JSON to prevent erasing existing brand
                    if (!empty($product_data['brand'])) {
                        wp_set_object_terms($product_id, $product_data['brand'], 'product_brand', true);
                    }

                    // Inside your import logic for each product
                    if (!empty($product_data['product_id'])) {
                        update_post_meta($product_id, '_unique_id', sanitize_text_field($product_data['product_id']));
                    }


                    // Assign Customer Category
                    if (!empty($product_data['customer_category'])) {
                        $customer_category = sanitize_text_field($product_data['customer_category']);

                        // Log before saving
                        error_log('Customer Category: ' . $customer_category);

                        // Save to post meta
                        update_post_meta($product_id, '_customer_roles', $customer_category);

                        // Verify if it is saved
                        $saved_category = get_post_meta($product_id, '_customer_roles', true);
                        error_log('Saved Customer Category: ' . print_r($saved_category, true));
                    }


                    // Handle ACF fields - only update if non-empty in JSON data
                    $arabic_name_val = !empty($product_data['arbic_name']) ? $product_data['arbic_name'] : (!empty($product_data['arabic_name']) ? $product_data['arabic_name'] : null);

                    $acf_fields = [
                        'product_name_arabic'   => $arabic_name_val,
                        'shelf_life'            => $product_data['shelf_life'] ?? null,
                        'expiry_date'           => $product_data['expiry_date'] ?? null,
                        'weight_title'          => $product_data['product_package'] ?? null,
                        'return_policies'       => $product_data['return_policy'] ?? null,
                        'piece_rate_vat'        => $product_data['piece_rate_vat'] ?? null
                    ];

                    // Update ACF fields safely (only if value is provided and non-empty)
                    foreach ($acf_fields as $acf_key => $acf_value) {
                        if ($acf_value !== null && $acf_value !== '') {
                            update_field($acf_key, $acf_value, $product_id);
                        }
                    }

                    // Handle ACF group fields inside 'product_details' group - only update if non-empty
                    $acf_group_fields = [
                        'overview'          => $product_data['product_overview'] ?? null,
                        'available_offers'  => $product_data['product_offers'] ?? null,
                        'save_more'         => $product_data['see_more'] ?? null,
                        'esti_time'         => $product_data['estimate_delivery_time'] ?? null
                    ];

                    // Update ACF group fields inside product_details group safely
                    foreach ($acf_group_fields as $acf_key => $acf_value) {
                        if ($acf_value !== null && $acf_value !== '') {
                            $acf_key_full = 'product_details_' . $acf_key;
                            update_field($acf_key_full, $acf_value, $product_id);
                        }
                    }

                    // Log parent action
                    $wpdb->insert($product_log_table, [
                        'import_log_id' => $this->current_import_id,
                        'product_id'    => $product_id,
                        'sku'           => $product_data['default_code'],
                        'unique_id'     => $product_data['product_id'],
                        'product_name'  => $product_data['product_name'],
                        'action'        => $is_new_parent ? 'created' : 'updated',
                        'request_data'  => wp_json_encode($product_data),
                        //'response_data' => wp_json_encode($product->get_data()),
                        'response_data' => wp_json_encode($this->build_full_parent_response($product_id)),

                        'message'       => $is_new_parent ? 'Parent product created' : 'Parent product updated',
                        'status'        => 'success',
                        'error_type'    => null,
                        'attempts'      => $attempts
                    ]);
                    $imported_count++;

                    // 2) Attributes & variations (run for both create and update)
                    if (!empty($product_data['package_lines'])) {

                        global $wpdb;

                        // 🔹 Separate Vansale and ECOM slabs
                        $vansale_lines = array_filter($product_data['package_lines'], function ($line) {
                            return isset($line['slab_name']) && strtolower(trim($line['slab_name'])) === 'vansale';
                        });

                        $ecom_lines = array_filter($product_data['package_lines'], function ($line) {
                            return isset($line['slab_name']) && strtolower(trim($line['slab_name'])) === 'ecom';
                        });

                        // 🔹 Save structured ECOM slab prices as meta (merging with existing meta if available)
                        if (!empty($ecom_lines)) {
                            $existing_ecom = get_post_meta($product_id, '_ecom_slab_prices', true);
                            if (!is_array($existing_ecom)) {
                                $existing_ecom = [];
                            }
                            $ecom_meta = [];
                            foreach ($ecom_lines as $line) {
                                $uom_id = $line['uom_id'] ?? 0;
                                $ecom_meta[$uom_id] = [
                                    'uom_name'    => $line['uom_name'] ?? '',
                                    'unit_price'  => floatval($line['unit_price'] ?? 0),
                                    'sale_price'  => floatval($line['sale_price'] ?? 0),
                                    'cost_price'  => floatval($line['cost_price'] ?? 0),
                                    'quantity'    => floatval($line['quantity'] ?? 0),
                                    'ratio'       => $line['quantity'] ?? '',
                                ];
                            }
                            $merged_ecom = array_replace($existing_ecom, $ecom_meta);
                            update_post_meta($product_id, '_ecom_slab_prices', $merged_ecom);
                        }

                        // 🔹 Skip product if no Vansale slabs
                        if (empty($vansale_lines)) {
                            $wpdb->insert($product_log_table, [
                                'import_log_id' => $this->current_import_id,
                                'sku'           => $product_data['default_code'] ?? 'N/A',
                                'product_name'  => $product_data['product_name'] ?? '',
                                'action'        => 'skipped',
                                'request_data'  => wp_json_encode($product_data['package_lines']),
                                'response_data' => '{}',
                                'message'       => '⚠️ No Vansale slab found, product skipped for variation creation',
                                'error_type'    => 'info',
                                'attempts'      => $attempts
                            ]);
                            continue;
                        }

                        // --- Attribute preparation (pa_size) ---
                        $attribute_slug = 'pa_size';
                        $term_ids = [];
                        $slugs    = [];

                        foreach ($vansale_lines as $line) {
                            $uom_name = $line['uom_name'] ?? '';
                            if (!$uom_name) continue;

                            $uom_slug = sanitize_title($uom_name);

                            // Ensure term exists
                            $term = get_term_by('slug', $uom_slug, $attribute_slug);
                            if (!$term) {
                                $result = wp_insert_term($uom_name, $attribute_slug, ['slug' => $uom_slug]);
                                if (!is_wp_error($result)) {
                                    $term = get_term_by('id', (int)$result['term_id'], $attribute_slug);
                                }
                            }

                            if ($term) {
                                $term_ids[] = (int)$term->term_id;
                                $slugs[$uom_name] = $uom_slug;
                            }
                        }

                        $default_uom_name = $product_data['product_uom'] ?? '';
                        $default_uom_slug = sanitize_title($default_uom_name);
                        // Attach attribute to parent product (preserving existing parent attributes if any)
                        $attribute = new WC_Product_Attribute();
                        $attribute->set_id(wc_attribute_taxonomy_id_by_name($attribute_slug));
                        $attribute->set_name($attribute_slug);
                        $attribute->set_options($term_ids);
                        $attribute->set_visible(true);
                        $attribute->set_variation(true);

                        $attributes = $product->get_attributes();
                        $attributes[$attribute_slug] = $attribute;
                        $product->set_attributes($attributes);
                        $product->save();

                        // 🔹 Set default variation (WooCommerce uses slug)
                        if ($default_uom_slug && in_array($default_uom_slug, $slugs)) {
                            $product->set_default_attributes(['pa_size' => $default_uom_slug]);
                            $product->save();
                        }

                        // --- Create or update Vansale variations ---
                        foreach ($vansale_lines as $variation_data) {

                            $uom_name = $variation_data['uom_name'] ?? '';
                            $unit_price = floatval($variation_data['unit_price'] ?? 0);

                            if (!$uom_name || !$unit_price) {
                                // Log missing variation data
                                $wpdb->insert($product_log_table, [
                                    'import_log_id' => $this->current_import_id,
                                    'sku'           => $product_data['default_code'] ?? 'N/A',
                                    'unique_id'     => $product_data['product_id'] ?? 'N/A',
                                    'product_name'  => $product_data['product_name'] ?? '',
                                    'action'        => 'error',
                                    'request_data'  => wp_json_encode($variation_data),
                                    'response_data' => '{}',
                                    'message'       => '❌ Missing UOM name or price',
                                    'error_type'    => 'permanent',
                                    'attempts'      => $attempts
                                ]);
                                continue;
                            }

                            $uom_slug = $slugs[$uom_name] ?? sanitize_title($uom_name);
                            $sku = $product_data['default_code'] . '-' . ($variation_data['uom_id'] ?? 0);

                            $variation_id = wc_get_product_id_by_sku($sku);
                            $is_new = false;

                            if (!$variation_id) {
                                $variation = new WC_Product_Variation();
                                $variation->set_parent_id($product_id);
                                $variation->set_attributes(['pa_size' => $uom_slug]);
                                $variation->set_sku($sku);
                                $is_new = true;
                            } else {
                                $variation = new WC_Product_Variation($variation_id);
                            }

                            // Prices - set regular price; handle sale price explicitly if present in JSON
                            $variation->set_regular_price($unit_price);
                            if (array_key_exists('sale_price', $variation_data)) {
                                $sp = $variation_data['sale_price'];
                                if ($sp !== '' && $sp !== null && floatval($sp) > 0) {
                                    $sale_price = floatval($sp);
                                    $variation->set_sale_price($sale_price);
                                    $variation->set_price($sale_price);
                                } else {
                                    // Explicitly empty / 0 -> clear sale price!
                                    $variation->set_sale_price('');
                                    $variation->set_price($unit_price);
                                }
                            } else {
                                // Key omitted -> keep existing sale price if available
                                if ($is_new) {
                                    $variation->set_sale_price('');
                                    $variation->set_price($unit_price);
                                } else {
                                    $current_sale = $variation->get_sale_price();
                                    $variation->set_price($current_sale ?: $unit_price);
                                }
                            }

                            // Default to 1 if ratio is empty or 0
                            $ratio = isset($variation_data['quantity']) && floatval($variation_data['quantity']) > 0 ? floatval($variation_data['quantity']) : 1;

                            // Quantity available from JSON
                            $quantity_available = isset($product_data['quantity_available']) ? floatval($product_data['quantity_available']) : 1;

                            // Calculate stock for this variation
                            $variation_stock = floor($quantity_available / $ratio);

                            // Set variation stock
                            $variation->set_manage_stock(true);
                            $variation->set_stock_quantity($variation_stock);
                            $variation->set_stock_status(
    $variation_stock > 0 ? 'instock' : 'outofstock'
);
                            $variation->set_status('publish');
                            $variation->save();
                            if (!empty($variation_data['barcode'])) {
    $barcode = sanitize_text_field($variation_data['barcode']);

    // 🔥 use direct DB (most reliable)
    update_post_meta($variation->get_id(), '_barcode', $barcode);
}

                            // Save UOM ID - only if present and non-empty
                            if (!empty($variation_data['uom_id'])) {
                                update_post_meta($variation->get_id(), '_uom_id', $variation_data['uom_id']);
                            }

                            // Ratio meta - only if present and non-empty
                            if (!empty($variation_data['quantity'])) {
                                update_post_meta($variation->get_id(), '_uom_ratio', $variation_data['quantity']);
                            }

                            // Log
                            $wpdb->insert($product_log_table, [
                                'import_log_id' => $this->current_import_id,
                                'product_id'    => $variation->get_id(),
                                'sku'           => $sku,
                                'unique_id'     => $product_data['product_id'] ?? '',
                                'product_name'  => $product_data['product_name'] ?? '',
                                'action'        => $is_new ? 'created' : 'updated',
                                'request_data'  => wp_json_encode($variation_data),
                                'response_data' => wp_json_encode($variation->get_data()),
                                'message'       => $is_new ? 'Variation created (Vansale)' : 'Variation updated (Vansale)',
                                'error_type'    => null,
                                'attempts'      => $attempts
                            ]);
                        }

                        // 🔹 Sync variable product
                        if (function_exists('wc_delete_product_transients')) {
                            wc_delete_product_transients($product_id);
                        }
                        if (function_exists('wc_update_product_lookup_tables')) {
                            wc_update_product_lookup_tables([$product_id]);
                        }
                        try {
                            if ($product && method_exists($product, 'sync')) {
                                $product->sync();
                            }
                        } catch (\Throwable $t) {
                        }
                    }



                    $success = true;
                } catch (\Throwable $e) {
                    if ($attempts < $max_attempts && !$success) {
                        $wpdb->insert($product_log_table, [
                            'import_log_id' => $this->current_import_id,
                            'sku'           => $product_data['default_code'] ?? 'N/A',
                            'unique_id'     => $product_data['product_id'] ?? 'N/A',
                            'product_name'  => $product_data['product_name'] ?? ($product ? $product->get_name() : 'N/A'),
                            'action'        => 'retrying',
                            'request_data'  => wp_json_encode($product_data),
                            'response_data' => '{}',
                            'message'       => '🔄 Retrying... Attempt ' . $attempts . ' | Error: ' . $e->getMessage(),
                            'error_type'    => 'temporary',
                            'status'        => 'retrying',
                            'attempts'      => $attempts
                        ]);
                        sleep(1);
                    } else {
                        $wpdb->insert($product_log_table, [
                            'import_log_id' => $this->current_import_id,
                            'sku'           => $product_data['default_code'] ?? 'N/A',
                            'unique_id'     => $product_data['product_id'] ?? 'N/A',
                            'product_name'  => $product_data['product_name'] ?? ($product ? $product->get_name() : 'N/A'),
                            'action'        => 'error',
                            'request_data'  => wp_json_encode($product_data),
                            'response_data' => '{}',
                            'message'       => '❌ Failed after ' . $max_attempts . ' attempts | ' . $e->getMessage(),
                            'error_type'    => 'permanent',
                            'status'        => 'error',
                            'attempts'      => $attempts
                        ]);
                    }
                }
            }
        }

        return $imported_count;
    }

    /**
     * Build full parent response including variations & meta
     */
    private function build_full_parent_response($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) return [];

        $data = $product->get_data();

        // 🔹 Add meta data
        $data['meta_data'] = [];
        foreach (get_post_meta($product_id) as $key => $value) {
            $data['meta_data'][$key] = maybe_unserialize($value[0]);
        }

        // 🔹 Add variations
        $data['variations'] = [];
        if ($product->is_type('variable')) {
            $children = $product->get_children();
            foreach ($children as $child_id) {
                $child = wc_get_product($child_id);
                if (!$child) continue;

                $child_data = $child->get_data();

                // Include meta for variation
                $child_data['meta_data'] = [];
                foreach (get_post_meta($child_id) as $key => $value) {
                    $child_data['meta_data'][$key] = maybe_unserialize($value[0]);
                }

                $data['variations'][] = $child_data;
            }
        }

        return $data;
    }


    private function assign_categories($product_id, $categories)
    {
        $ids = [];
        // Ensure categories is an array, even if a single category is passed
        $categories = (array) $categories;

        foreach ($categories as $cat) {
            if (is_numeric($cat)) {
                // If category is a numeric ID
                $ids[] = (int) $cat;
            } else if (!empty(trim((string)$cat))) {
                // If category is a string, look it up or create it
                $term = term_exists($cat, 'product_cat');
                if (!$term) {
                    // If term doesn't exist, create it
                    $term = wp_insert_term($cat, 'product_cat');
                }
                if (!is_wp_error($term)) {
                    $ids[] = (int) $term['term_id'];
                }
            }
        }

        // Assign categories to the product
        if (!empty($ids)) {
            wp_set_object_terms($product_id, $ids, 'product_cat');
        }
    }
}

new JSON_Variable_Product_Importer();

function enqueue_json_importer_styles()
{
    // Ensure the CSS file is correctly enqueued for the admin area
    wp_enqueue_style('json-importer-styles', plugin_dir_url(__FILE__) . 'css/admin-style.css');
}
add_action('admin_enqueue_scripts', 'enqueue_json_importer_styles');

//============= order data ===================

 add_action('woocommerce_order_status_processing', 'send_order_data_to_log', 10, 1);

function send_order_data_to_log($order_id)
{
    global $wpdb;

    // Get WooCommerce order object
    $order = wc_get_order($order_id);
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }
    $ordr_number = $order->get_order_number();
    $invoice_number = 'INV' . $ordr_number;
    $items_raw = $order->get_items();
    $vat_total = $order->get_total_tax(); // VAT total
    $discount = $order->get_total_discount();  // FIXED
    $order_statuss = $order->get_status();

    $items = array_map(function ($item) {

        $product = $item->get_product();
        $product_id = $product->get_id();
        $parent_id  = $product->get_parent_id() ?: $product->get_id();

        $unique_id = (int) get_post_meta($parent_id, '_unique_id', true); // FIXED
        $uom_id    = (int) get_post_meta($product_id, '_uom_id', true);   // FIXED

        $quantity = (float) $item->get_quantity();
        $unit_price = $quantity > 0 ? ($item->get_subtotal() / $quantity) : 0; // FIXED

        $regular_price = $item->get_product()->get_regular_price();
        $sale_price = $item->get_product()->get_sale_price();

        // Calculate the discount at the product level
        //$product_discount = $regular_price - $sale_price;
        $regular_price = (float) $regular_price;
        $sale_price    = (float) $sale_price;

        $product_discount = 0;

        if ($regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price) {
        $product_discount = $regular_price - $sale_price;
       }

        $vat_amount = $item->get_total_tax();

        return [
            'product_id' => $unique_id,
            'uom_id'     => $uom_id ?: 0,
            'quantity'   => $quantity,
            'unit_price' => floatval(number_format($unit_price, 2, '.', '')),
            'discount'   => floatval(number_format($product_discount, 2, '.', '')),
            'vat_amount' => floatval(number_format($vat_amount, 2, '.', '')), // Add VAT amount for this item

        ];
    }, $items_raw);

    $items = array_values($items);  // FIX: remove keys


    /* ----------------------------
       FIX 2: SHIPPING LINES
    ----------------------------- */

    $shipping_lines_raw = [];

    foreach ($order->get_shipping_methods() as $shipping) {
        $shipping_lines_raw[] = [
            'method' => $shipping->get_name(),
            'cost'   => floatval($shipping->get_total()),      // FIXED
            'tax'    => floatval($shipping->get_total_tax()),  // FIXED
        ];
    }


    $shipping_lines = array_values($shipping_lines_raw);


    /* ----------------------------
       FIX 3: CREATE ERP JSON BODY
    ----------------------------- */
    $customer_id = $order->get_customer_id();
    $trade_license_number = get_user_meta($customer_id, 'license_number', true);
    // Check if the payment method is COD and add the COD fee
    $cod_fee = 0;
    if ($order->get_payment_method() === 'cod') {
        $cod_fee = 10; // Add a fixed COD fee of 10 (can be changed as needed)
    }
    $data = [
        'params' => [
            'order_id'   => (int) $order->get_id(),
            'order_no'   => $ordr_number,
            'order_date' => $order->get_date_created()->date('c'),
            'order_status' => $order_statuss,
            'notes'      => $order->get_customer_note(),
            'order_discount' => $discount,
            'vat_total'      => $vat_total,

            'customer' => [
                'customer_id' => (int) $order->get_customer_id(),
                'email'       => $order->get_billing_email(),
                'phone'       => $order->get_billing_phone(),
                'first_name'  => $order->get_billing_first_name(),
                'last_name'   => $order->get_billing_last_name(),
                'trn'         => $trade_license_number,
            ],

            'billing_address' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
            ],

            'shipping_address' => [
                'first_name' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
                'last_name'  => $order->get_shipping_last_name()  ?: $order->get_billing_last_name(),
                'address_1'  => $order->get_shipping_address_1()  ?: $order->get_billing_address_1(),
                'address_2'  => $order->get_shipping_address_2()  ?: $order->get_billing_address_2(),
                'city'       => $order->get_shipping_city()      ?: $order->get_billing_city(),
                'state'      => $order->get_shipping_state()     ?: $order->get_billing_state(),
                'postcode'   => $order->get_shipping_postcode()  ?: $order->get_billing_postcode(),
                'country'    => $order->get_shipping_country()   ?: $order->get_billing_country(),
            ],

            'items' => $items,

            'payment_method' => [
                'method'         => $order->get_payment_method() === 'cod' ? 'cod' : 'online',
                'invoice_number' => $invoice_number,
                'provider'       => $order->get_payment_method() === 'cod' ? null : 'ngenius',
                'transaction_id' => $order->get_payment_method() === 'cod' ? null : $order->get_transaction_id(),
                'payment_date'   => $order->get_date_paid() ? $order->get_date_paid()->date('c') : null,
                'payment_status' => (
        $order->get_payment_method() === 'cod'
            ? ($order->get_status() === 'completed' ? 'paid' : 'unpaid')
            : ($order->is_paid() ? 'paid' : 'unpaid')
    ),
                'cod_fee'        => $order->get_payment_method() === 'cod' ? $cod_fee : null,
            ],

            'shipping_lines' => $shipping_lines,
        ]
    ];

    $json_data = wp_json_encode($data);


    /* ----------------------------
       SEND TO ERP (ONE TIME ONLY)
    ----------------------------- */

    $erp_url     = get_option('json_import_erp_order_url', 'http://69.16.200.165:8045/api/order/create');
    $erp_api_key = get_option('json_import_erp_api_key', '45635609-e74c-4f07-9c81-73c819dbf85f');

    $erp_response_raw = wp_remote_post($erp_url, [
        'method'  => 'POST',
        'body'    => $json_data,
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $erp_api_key,
        ],
        'timeout' => 30,
    ]);

    $innsof_response = is_wp_error($erp_response_raw)
        ? json_encode(['status' => 'error', 'message' => $erp_response_raw->get_error_message()])
        : wp_remote_retrieve_body($erp_response_raw);

    /* Determine status from ERP response */
    $erp_decoded = json_decode($innsof_response, true);

    if (isset($erp_decoded['result']['success']) && $erp_decoded['result']['success'] == 1) {
        $log_status = 1; // success
    } else {
        $log_status = 0; // failure
    }
    /* ----------------------------
       SAVE LOG
    ----------------------------- */

    $wpdb->insert(
        $wpdb->prefix . 'json_order_log',
        [
            'order_id'        => $order_id,
            'woocommerce_response' => $json_data,
            'innsof_response' => $innsof_response,
            'status'          => $log_status,
            'date'            => current_time('mysql')
        ]
    );
}

/**
 * Helper to match variation with ECOM slab pricing by stored _uom_id or exact SKU pattern
 */
function sea_json_importer_get_ecom_data_for_variation($variation, $ecom_prices)
{
    if (empty($ecom_prices) || !is_array($ecom_prices) || !$variation) {
        return null;
    }

    $variation_id = $variation->get_id();
    $uom_id       = get_post_meta($variation_id, '_uom_id', true);

    if ($uom_id && isset($ecom_prices[$uom_id])) {
        return ['uom_id' => $uom_id, 'data' => $ecom_prices[$uom_id]];
    }

    $sku = $variation->get_sku();
    if ($sku) {
        if (preg_match('/-(\d+)$/', $sku, $matches)) {
            $sku_uom_id = $matches[1];
            if (isset($ecom_prices[$sku_uom_id])) {
                return ['uom_id' => $sku_uom_id, 'data' => $ecom_prices[$sku_uom_id]];
            }
        }
        foreach ($ecom_prices as $u_id => $d) {
            if ((string)$u_id === $sku || (isset($d['uom_name']) && $d['uom_name'] === $sku)) {
                return ['uom_id' => $u_id, 'data' => $d];
            }
        }
    }

    return null;
}

/**
 * Helper to check if current user has company-account role
 */
function sea_json_importer_is_company_account_user()
{
    if (!is_user_logged_in()) {
        return false;
    }
    $user = wp_get_current_user();
    return in_array('company-account', (array) $user->roles);
}

/**
 * Apply ECOM slab active price for "Company Account" users
 */
add_filter('woocommerce_product_get_price', 'apply_company_account_price', 20, 2);
add_filter('woocommerce_product_variation_get_price', 'apply_company_account_price', 20, 2);

/**
 * Apply ECOM slab regular price (Unit Price) for "Company Account" users
 */
add_filter('woocommerce_product_get_regular_price', 'apply_company_account_regular_price', 20, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'apply_company_account_regular_price', 20, 2);

/**
 * Apply ECOM slab sale price for "Company Account" users
 */
add_filter('woocommerce_product_get_sale_price', 'apply_company_account_sale_price', 20, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'apply_company_account_sale_price', 20, 2);

/**
 * Apply runtime pricing for variations (avoid caching conflicts)
 */
add_filter('woocommerce_variation_prices_price', 'apply_company_account_price_runtime', 20, 3);
add_filter('woocommerce_variation_prices_regular_price', 'apply_company_account_regular_price_runtime', 20, 3);
add_filter('woocommerce_variation_prices_sale_price', 'apply_company_account_sale_price_runtime', 20, 3);

function apply_company_account_price_runtime($price, $variation, $product)
{
    return apply_company_account_price($price, $variation);
}

function apply_company_account_regular_price_runtime($price, $variation, $product)
{
    return apply_company_account_regular_price($price, $variation);
}

function apply_company_account_sale_price_runtime($price, $variation, $product)
{
    return apply_company_account_sale_price($price, $variation);
}

/**
 * Main active price logic for company-account users
 */
function apply_company_account_price($price, $product)
{
    if (!sea_json_importer_is_company_account_user() || !$product) {
        return $price;
    }

    $product_id  = $product->get_parent_id() ?: $product->get_id();
    $ecom_prices = get_post_meta($product_id, '_ecom_slab_prices', true);

    $matched = sea_json_importer_get_ecom_data_for_variation($product, $ecom_prices);
    if ($matched && !empty($matched['data'])) {
        $sp = !empty($matched['data']['sale_price']) ? floatval($matched['data']['sale_price']) : 0;
        $up = !empty($matched['data']['unit_price']) ? floatval($matched['data']['unit_price']) : 0;
        if ($sp > 0) {
            return $sp;
        } elseif ($up > 0) {
            return $up;
        }
    }

    return $price;
}

/**
 * Regular price (Unit Price) logic for company-account users
 */
function apply_company_account_regular_price($price, $product)
{
    if (!sea_json_importer_is_company_account_user() || !$product) {
        return $price;
    }

    $product_id  = $product->get_parent_id() ?: $product->get_id();
    $ecom_prices = get_post_meta($product_id, '_ecom_slab_prices', true);

    $matched = sea_json_importer_get_ecom_data_for_variation($product, $ecom_prices);
    if ($matched && !empty($matched['data'])) {
        $up = !empty($matched['data']['unit_price']) ? floatval($matched['data']['unit_price']) : 0;
        if ($up > 0) {
            return $up;
        }
    }

    return $price;
}

/**
 * Sale price logic for company-account users
 */
function apply_company_account_sale_price($price, $product)
{
    if (!sea_json_importer_is_company_account_user() || !$product) {
        return $price;
    }

    $product_id  = $product->get_parent_id() ?: $product->get_id();
    $ecom_prices = get_post_meta($product_id, '_ecom_slab_prices', true);

    $matched = sea_json_importer_get_ecom_data_for_variation($product, $ecom_prices);
    if ($matched && !empty($matched['data'])) {
        $sp = !empty($matched['data']['sale_price']) ? floatval($matched['data']['sale_price']) : 0;
        if ($sp > 0) {
            return $sp;
        }
    }

    return $price;
}

/**
 * ✅ Update variation price ranges too
 */
add_filter('woocommerce_variation_prices', 'apply_company_account_variation_prices', 20, 3);
function apply_company_account_variation_prices($prices_array, $product, $include_taxes)
{
    if (!sea_json_importer_is_company_account_user() || !$product) {
        return $prices_array;
    }

    $product_id  = $product->get_id();
    $ecom_prices = get_post_meta($product_id, '_ecom_slab_prices', true);

    if (!empty($ecom_prices) && is_array($ecom_prices)) {
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) continue;

            $matched = sea_json_importer_get_ecom_data_for_variation($variation, $ecom_prices);
            if ($matched && !empty($matched['data'])) {
                $up = !empty($matched['data']['unit_price']) ? floatval($matched['data']['unit_price']) : 0;
                $sp = !empty($matched['data']['sale_price']) ? floatval($matched['data']['sale_price']) : 0;

                if (function_exists('wc_get_price_excluding_tax')) {
                    $reg_price  = $up > 0 ? wc_get_price_excluding_tax($variation, ['price' => $up]) : 0;
                    $sale_price = $sp > 0 ? wc_get_price_excluding_tax($variation, ['price' => $sp]) : 0;
                } else {
                    $tax_rate   = 5;
                    $reg_price  = $up > 0 ? round($up / (1 + ($tax_rate / 100)), 2) : 0;
                    $sale_price = $sp > 0 ? round($sp / (1 + ($tax_rate / 100)), 2) : 0;
                }

                $active_price = ($sale_price > 0) ? $sale_price : $reg_price;

                if ($reg_price > 0) {
                    $prices_array['regular_price'][$variation_id] = $reg_price;
                }
                if ($sale_price > 0) {
                    $prices_array['sale_price'][$variation_id] = $sale_price;
                }
                if ($active_price > 0) {
                    $prices_array['price'][$variation_id] = $active_price;
                }
            }
        }
    }

    return $prices_array;
}

/**
 * Add ECOM Pricing meta box
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'ecom_pricing_box',
        'ECOM Pricing',
        'render_ecom_pricing_box',
        'product',
        'normal',
        'default'
    );
});

/**
 * Display ECOM Pricing fields
 */
function render_ecom_pricing_box($post)
{
    $product = wc_get_product($post->ID);
    if (!$product) {
        return;
    }

    wp_nonce_field('save_ecom_pricing', 'ecom_pricing_nonce');

    $ecom_prices = get_post_meta($post->ID, '_ecom_slab_prices', true);
    if (!is_array($ecom_prices)) {
        $ecom_prices = [];
    }

    $variations = [];
    if ($product->is_type('variable')) {
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variations[] = $variation;
            }
        }
    }
    ?>

    <p>Enter the ECOM price for each product variation/UOM.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Variation / UOM</th>
                <th>SKU</th>
                <th>ECOM Price</th>
                <th>ECOM Sale Price</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($variations)) : ?>
            <?php foreach ($variations as $variation) : ?>
                <?php
                $variation_id = $variation->get_id();
                $sku          = $variation->get_sku();

                $matched        = sea_json_importer_get_ecom_data_for_variation($variation, $ecom_prices);
                $matched_uom_id = $matched ? $matched['uom_id'] : '';
                $matched_data   = $matched ? $matched['data'] : ['uom_name' => '', 'unit_price' => '', 'sale_price' => ''];
                ?>
                <tr>
                    <td><?php echo esc_html(wc_get_formatted_variation($variation, true)); ?></td>
                    <td><?php echo esc_html($sku); ?></td>
                    <td>
                        <input type="number" step="0.01" name="ecom_pricing[<?php echo esc_attr($variation_id); ?>][unit_price]" value="<?php echo esc_attr($matched_data['unit_price'] ?? ''); ?>" style="width:120px;">
                    </td>
                    <td>
                        <input type="number" step="0.01" name="ecom_pricing[<?php echo esc_attr($variation_id); ?>][sale_price]" value="<?php echo esc_attr(!empty($matched_data['sale_price']) ? $matched_data['sale_price'] : ''); ?>" style="width:120px;">
                    </td>
                </tr>
                <?php if ($matched_uom_id !== '') : ?>
                    <input type="hidden" name="ecom_pricing[<?php echo esc_attr($variation_id); ?>][uom_id]" value="<?php echo esc_attr($matched_uom_id); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4">No variations found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Save ECOM pricing
 */
add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['ecom_pricing_nonce']) || !wp_verify_nonce($_POST['ecom_pricing_nonce'], 'save_ecom_pricing')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['ecom_pricing'])) {
        return;
    }

    $submitted = $_POST['ecom_pricing'];
    $ecom_meta = [];

    foreach ($submitted as $variation_id => $data) {
        $variation_id = absint($variation_id);
        if (!$variation_id) continue;

        $variation = wc_get_product($variation_id);
        if (!$variation) continue;

        $uom_id = get_post_meta($variation_id, '_uom_id', true);
        if (!$uom_id) {
            $existing_ecom = get_post_meta($post_id, '_ecom_slab_prices', true);
            $matched       = sea_json_importer_get_ecom_data_for_variation($variation, $existing_ecom);
            $uom_id        = $matched ? $matched['uom_id'] : $variation_id;
        }

        $unit_price = isset($data['unit_price']) ? floatval($data['unit_price']) : 0;
        $sale_price = isset($data['sale_price']) ? floatval($data['sale_price']) : 0;

        $ecom_meta[$uom_id] = [
            'uom_name'   => $variation->get_sku(),
            'unit_price' => $unit_price,
            'sale_price' => $sale_price,
            'cost_price' => 0,
            'quantity'   => 1,
            'ratio'      => 1,
        ];
    }

    update_post_meta($post_id, '_ecom_slab_prices', $ecom_meta);
});
