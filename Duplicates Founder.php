<?php

/*  

Plugin Name: Duplicates Founder

Description: *Compatible with HPOS*  Plugin allow to detect if 2 or more Users have same phone number or address, media have same title or description, 2 or more orders have same customer id or billing phone or same content, 2 or more products have the Same Title or Description.

Author: Yousseif Ahmed 

Version: 1.4  

*/


// For Products Duplicates Founder
function generate_duplicate_dropdown($name, $label, $choices, $selected)
{

  echo '<div class="alignleft actions">';
  echo '<span class="alignleft">';
  echo '<select name="' . $name . '">';
  echo '<option value=""' . ($selected == '' ? ' selected="selected"' : '') . '>' . $label . '</option>';

  foreach ($choices as $key => $value) {
    echo '<option value="' . $key . '"' . ($selected == $key ? ' selected="selected"' : '') . '>' . $value . '</option>';
  }

  echo '</select></span></div>';
}

function add_filter_duplicates_dropdown($post_type)
{
  if ($post_type != 'product') {
    return;
  }
  $filters = [
    'filter_duplicates' => [
      'label' => 'Find Duplicates',
      'choices' => [
        'title' => 'By Title',
        'description' => 'By Description',
      ],
      'selected' => filter_input(INPUT_GET, 'filter_duplicates', FILTER_SANITIZE_STRING)
    ]
  ];

  foreach ($filters as $name => $filter) {
    generate_duplicate_dropdown($name, $filter['label'], $filter['choices'], $filter['selected']);
  }
}


function filter_duplicates($query)
{
  if (isset($_GET['filter_duplicates'])) {
    global $wpdb;

    $filter_by = $_GET['filter_duplicates'];

    if ($filter_by === 'title') {

      $product_ids = array();
      $product_titles = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product'");

      $title_counts = array();
      foreach ($product_titles as $product) {
        $title = $product->post_title;
        if (!isset($title_counts[$title])) {
          $title_counts[$title] = 0;
        }
        $title_counts[$title]++;
        if ($title_counts[$title] > 1) {
          $product_ids[] = $product->ID;
        }
      }

      $query->set('post__in', $product_ids);

      // Check if the result is empty
      if (empty($product_ids)) {
        $query->set('post__in', array(-1)); // Assuming -1 won't match any post ID
      }
    }

    if ($filter_by === 'description') {
      $product_ids = array();
      $product_desc = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'product'");

      $desc_counts = array();
      foreach ($product_desc as $product) {
        $desc = $product->post_content;
        if (!isset($desc_counts[$desc])) {
          $desc_counts[$desc] = 0;
        }
        $desc_counts[$desc]++;
        if ($desc_counts[$desc] > 1) {
          $product_ids[] = $product->ID;
        }
      }

      $query->set('post__in', $product_ids);

      // Check if the result is empty
      if (empty($product_ids)) {
        $query->set('post__in', array(-1)); // Assuming -1 won't match any post ID
      }
    }

  }
}

// For Orders Duplicates Founder

function add_filter_duplicates_order_dropdown()
{

  $filters = [
    'filter_order_duplicates' => [
      'label' => 'Find Order Duplicates',
      'choices' => [
        'user' => 'By User',
        'mobile' => 'By Mobile Number',
        'products' => 'By Same Products'
      ],
      'selected' => filter_input(INPUT_GET, 'filter_order_duplicates', FILTER_SANITIZE_STRING)
    ]
  ];

  foreach ($filters as $name => $filter) {
    generate_duplicate_dropdown($name, $filter['label'], $filter['choices'], $filter['selected']);
  }
}

function filter_order_duplicates($query_args)
{
  if (isset($_GET['filter_order_duplicates'])) {
    $filter_by = $_GET['filter_order_duplicates'];
    global $wpdb;

    if ($filter_by === 'user') {

      // Custom SQL query to retrieve orders with duplicated customer_id
      $sql = "
                SELECT customer_id, COUNT(customer_id) as order_count
                FROM {$wpdb->prefix}wc_orders
                WHERE customer_id > 0
                GROUP BY customer_id
                HAVING order_count > 1
            ";

      $duplicate_customer_ids = $wpdb->get_col($sql);

      // Modify the query args to include the duplicated customer_ids
      if (!empty($duplicate_customer_ids)) {
        // Modify the query args to include the duplicated customer_ids
        $query_args['customer_id'] = $duplicate_customer_ids;
      } else {
        // If no duplicates, set customer_id to a non-existent ID to return an empty result
        $query_args['customer_id'] = -1; // Adjust this value as needed
      }
      return $query_args;
    }

    if ($filter_by === 'mobile') {
      $sql = "
      SELECT phone, COUNT(phone) as order_count
      FROM {$wpdb->prefix}wc_order_addresses
      WHERE phone IS NOT NULL
      GROUP BY phone
      HAVING order_count > 1
  ";
      $duplicate_mobile_numbers = $wpdb->get_col($sql);

      if (!empty($duplicate_mobile_numbers)) {
        // Modify the query args to include the duplicated customer_ids
        $query_args['billing_phone'] = $duplicate_mobile_numbers;
      } else {
        // If no duplicates, set customer_id to a non-existent ID to return an empty result
        $query_args['billing_phone'] = -1; // Adjust this value as needed
      }
      return $query_args;
    }


    if ($filter_by === 'products') {


      $duplicate_orders = $wpdb->get_results("
          SELECT o1.ID
          FROM {$wpdb->prefix}wc_orders o1
          INNER JOIN {$wpdb->prefix}woocommerce_order_items oi1 ON o1.ID = oi1.order_id
          INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim1 ON oi1.order_item_id = oim1.order_item_id
          WHERE oim1.meta_key = '_product_id'
          GROUP BY o1.ID
          HAVING (
              SELECT GROUP_CONCAT(CONCAT(oim2.meta_value, ':', oim_qty2.meta_value) ORDER BY oim2.meta_value)
              FROM {$wpdb->prefix}woocommerce_order_items oi2
              INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi2.order_item_id = oim2.order_item_id
              INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty2 ON oi2.order_item_id = oim_qty2.order_item_id
              WHERE oi2.order_id = o1.ID
                  AND oim2.meta_key = '_product_id'
                  AND oim_qty2.meta_key = '_qty'
              GROUP BY oi2.order_id
          ) IN (
              SELECT GROUP_CONCAT(CONCAT(oim3.meta_value, ':', oim_qty3.meta_value) ORDER BY oim3.meta_value)
              FROM {$wpdb->prefix}woocommerce_order_items oi3
              INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 ON oi3.order_item_id = oim3.order_item_id
              INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty3 ON oi3.order_item_id = oim_qty3.order_item_id
              WHERE oi3.order_id = o1.ID
                  AND oim3.meta_key = '_product_id'
                  AND oim_qty3.meta_key = '_qty'
              GROUP BY oi3.order_id
              HAVING COUNT(*) = (SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = oi3.order_id)
          )
      ");
      if (!empty($duplicate_orders)) {
        $order_ids = wp_list_pluck($duplicate_orders, 'ID');
        $query_args['id'] = $order_ids; // Use $order_ids instead of $duplicate_orders

        if (empty($order_ids)) {
          $query_args['id'] = -1; // Assuming -1 won't match any post ID
        }
      }

      return $query_args;

    }

  }
}


// For Media Duplicates Founder

function add_filter_duplicates_dropdown_media()
{
  global $pagenow;
  if ($pagenow != 'upload.php') {
    return;
  }
  $filters = [
    'filter_duplicates_media' => [
      'label' => 'Find Duplicates',
      'choices' => [
        'title' => 'By Title',
        'description' => 'By Description',
      ],
      'selected' => filter_input(INPUT_GET, 'filter_duplicates_media', FILTER_SANITIZE_STRING)
    ]
  ];

  foreach ($filters as $name => $filter) {
    generate_duplicate_dropdown($name, $filter['label'], $filter['choices'], $filter['selected']);
  }
}

function filter_duplicates_media($query)
{
  if (isset($_GET['filter_duplicates_media'])) {
    global $wpdb;

    $filter_by = $_GET['filter_duplicates_media'];

    if ($filter_by === 'title') {
      $media_ids = array();
      $media_titles = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'attachment'");

      $title_counts = array();
      foreach ($media_titles as $media) {
        $title = $media->post_title;
        if (!isset($title_counts[$title])) {
          $title_counts[$title] = 0;
        }
        $title_counts[$title]++;
        if ($title_counts[$title] > 1) {
          $media_ids[] = $media->ID;
        }
      }

      $query->set('post__in', $media_ids);
      if (empty($media_ids)) {
        $query->set('post__in', array(-1)); // Assuming -1 won't match any post ID
      }
    }

    if ($filter_by === 'description') {
      $media_ids = array();
      $media_desc = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'attachment'");

      $desc_counts = array();
      foreach ($media_desc as $media) {
        $desc = $media->post_content;
        if (!isset($desc_counts[$desc])) {
          $desc_counts[$desc] = 0;
        }
        $desc_counts[$desc]++;
        if ($desc_counts[$desc] > 1) {
          $media_ids[] = $media->ID;
        }
      }

      $query->set('post__in', $media_ids);
      if (empty($media_ids)) {
        $query->set('post__in', array(-1)); // Assuming -1 won't match any post ID
      }
    }

  }
}


// For Users Duplicates Founder

function add_filter_duplicates_dropdown_users()
{
  global $pagenow;
  if ($pagenow != 'users.php') {
    return;
  }
  $filters = [
    'filter_duplicates_users' => [
      'label' => 'Find Duplicates',
      'choices' => [
        'mobile' => 'By Mobile Number',
        'address' => 'By Address',
      ],
      'selected' => filter_input(INPUT_GET, 'filter_duplicates_users', FILTER_SANITIZE_STRING)
    ]
  ];

  echo '<div class="alignright actions">';
  echo '<form method="post">';
  foreach ($filters as $name => $filter) {
    generate_duplicate_dropdown($name, $filter['label'], $filter['choices'], $filter['selected']);
  }
  echo '<input type="submit" class="button" value="Filter">';
  echo '</form>';
  echo '</div>';

}

function filter_duplicates_users($query)
{
  if (isset($_GET['filter_duplicates_users'])) {
    global $wpdb;

    $filter_by = $_GET['filter_duplicates_users'];

    if ($filter_by === 'mobile') {
      $user_ids = array();
      $user_mobiles = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone'");

      $mobile_counts = array();
      foreach ($user_mobiles as $user) {
        $mobile = $user->meta_value;
        if (!isset($mobile_counts[$mobile])) {
          $mobile_counts[$mobile] = 0;
        }
        $mobile_counts[$mobile]++;
        if ($mobile_counts[$mobile] > 1) {
          $user_ids[] = $user->user_id;
        }
      }

      $query->set('include', $user_ids);
      if (empty($user_ids)) {
        $query->set('include', array(-1));
      }
    }

    if ($filter_by === 'address') {
      $user_ids = array();
      $user_addresses = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'billing_address_1'");

      $address_counts = array();
      foreach ($user_addresses as $user) {
        $address = $user->meta_value;
        if (!isset($address_counts[$address])) {
          $address_counts[$address] = 0;
        }
        $address_counts[$address]++;
        if ($address_counts[$address] > 1) {
          $user_ids[] = $user->user_id;
        }
      }

      $query->set('include', $user_ids);
      if (empty($user_ids)) {
        $query->set('include', array(-1));
      }
    }
  }
}
