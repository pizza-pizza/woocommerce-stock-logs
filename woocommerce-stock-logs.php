<?php
/*----------------------------------------------------------------------------------------------------------------------
Plugin Name: WooCommerce Stock Logs
Description: Establishes an audit log of adjustments to product stock.
Version: 1.0
Author: New Order Studios
Author URI: https://github.com/neworderstudios
----------------------------------------------------------------------------------------------------------------------*/

/**
 * TODO: integrate unit/label custom fields
 * TODO: i18n for strings
 */

if ( is_admin() ) {
    new wcStockLogs();
}

class wcStockLogs {

	public function __construct() {
		global $pagenow;
		define( 'WC_STOCKLOGS_TABLE', 'woocommerce_stock_adjustments' );
		add_action( 'init', array( $this, 'stocklogs_init_db' ) );
		add_action( 'wp_ajax_save_wcstock_adjustment', array( $this, 'save_stock_adjustment' ) );
		add_action( 'wp_ajax_load_wcstock_report', array( $this, 'render_report_meta_box' ) );

		if ( in_array( $pagenow, array( 'post.php' ) ) ) add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
	}

	/**
	 * Let's add the adjustment & report metaboxes.
	 */
	public function add_metaboxes( $post_type ) {
		if ( $post_type == 'product' ) {

			add_meta_box(
				'stock_adjustment_inputs'
				,'Adjust Stock Quantity'
				,array( $this, 'render_adjustment_meta_box' )
				,$post_type
				,'side'
				,'core'
			);

			add_meta_box(
				'stock_adjustment_log'
				,'Product Stock History'
				,array( $this, 'render_report_meta_box' )
				,$post_type
				,'advanced'
				,'high'
			);
		
		}
	}

	/**
	 * Let's render the adjustment box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_adjustment_meta_box( $post ) {
		$ajax_load_logs = "jQuery('#stock_adjustment_log .inside').load(ajaxurl + '?action=load_wcstock_report',{post_ID:{$post->ID}});";
		$ajax_success = "function(r){ jQuery('#stock_adjustment_inputs img').fadeOut();jQuery('#stock_adjustment_inputs input').val('');jQuery('#stock_adjustment_inputs select').val('');{$ajax_load_logs}jQuery('#_stock').val(r); }";
		$ajax_before = "jQuery('#stock_adjustment_inputs img').fadeIn();";
		$ajax_data = "{post_ID:{$post->ID},adjustment:jQuery('#product_stock_adjustment').val(),notes:jQuery('#product_stock_adjustment_notes').val()}";
		$ajax_submit = "{$ajax_before}jQuery.post(ajaxurl + '?action=save_wcstock_adjustment',{$ajax_data},{$ajax_success});return false;";

		$adjustment_notes = array( '' => 'Select an action','New Arrival','Trash','Correction' );

		echo '<label for="product_stock_adjustment" style="display:inline-block;margin-bottom:5px;">Adjust Stock Quantity:</label>';
		echo '<input type="number" id="product_stock_adjustment" style="width:100%;" />';
		echo '<select id="product_stock_adjustment_notes" style="width:100%;">';
		foreach ( $adjustment_notes as $k => $n ) echo '<option value="' . (!is_numeric($k) ? $k : $n) . '">' . $n . '</option>';
		echo '</select>';
		echo '<a class="button button-primary button-large" href="#" onclick="' . $ajax_submit . '" style="margin-top:5px;">Save Adjustment</a> ';
		echo '&nbsp;<img src="images/loading.gif" style="display:none;padding-top:12px;" />';
	}

	/**
	 * Let's render the report box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_report_meta_box( $post = NULL ) {
		global $wpdb;

		$post_ID = $post ? $post->ID : $_REQUEST['post_ID'];
		$adjustments = $wpdb->get_results( "select * from " . $wpdb->prefix.WC_STOCKLOGS_TABLE . " where post_ID = {$post_ID}" );

		echo '<style type="text/css">#stock_adjustment_log th { border-bottom:1px solid #eee;padding-bottom:2px; } #stock_adjustment_log td { padding-top:5px; }</style>';
		echo '<table cellpadding="0" cellspacing="0" width="100%">';
		echo '<thead><tr><th align="left">Date / Time</th><th align="left">User</th><th align="right">Initial Quantity</th><th align="right">Adjustment</th><th align="right" style="padding-right:20px;">Adjusted Quantity</th><th align="left">Note</th></thead><tbody>';
		foreach ( $adjustments as $a ) {
			$user = get_user_by( 'id', $a->user_ID );
			$initial_quantity = $a->adjusted_quantity - $a->adjustment;
			echo "<tr><td align='left'>{$a->date}</td><td align='left'>{$user->display_name}</td><td align='right'>" . $initial_quantity . "</td><td align='right'>" . ( $a->adjustment >= 0 ? '+' : '' ) . $a->adjustment . "</td><td align='right' style='padding-right:20px;'>" . ( $a->adjusted_quantity ) . "</td><td align='left'>{$a->notes}</td></tr>";
		}
		echo '</tbody></table>';

		if(defined('DOING_AJAX') && DOING_AJAX) die();
	}

	/**
	 * AJAX action for saving an adjustment.
	 */
	function save_stock_adjustment() {
		global $wpdb;
		global $current_user;

		get_currentuserinfo();
		$user_id = $current_user->ID;
		
		$product = new WC_Product( $_REQUEST['post_ID'] );
		$new_stock = $product->increase_stock( $_REQUEST['adjustment'] );

		$wpdb->insert( $wpdb->prefix.WC_STOCKLOGS_TABLE, array( 'post_ID' => $_REQUEST['post_ID'], 'user_ID' => $user_id, 'adjustment' => $_REQUEST['adjustment'],'adjusted_quantity' => $new_stock,'notes' => $_REQUEST['notes'] ) );
		echo $new_stock;
		die();
	}

	/**
	 * Initialize our log table.
	 */
	public function stocklogs_init_db() {
		global $wpdb;

		if ( in_array( $wpdb->prefix . WC_STOCKLOGS_TABLE, $wpdb->get_col( 'show tables' ) ) === false ){

			$charset_collate = '';
			if ( !empty( $wpdb->charset ) ) $charset_collate = "default character set " . $wpdb->charset;
			if ( !empty( $wpdb->collate ) ) $charset_collate .= " collate " . $wpdb->collate;

			$wpdb->query(
				"create table if not exists `" . $wpdb->prefix.WC_STOCKLOGS_TABLE . "` (`ID` int primary key auto_increment,`post_ID` bigint(20) unsigned not null,`user_ID` bigint(20) unsigned not null,`adjustment` int,`adjusted_quantity` int,`notes` varchar(255),`date` timestamp not null default current_timestamp) $charset_collate;"
			);
		}
	}
}
