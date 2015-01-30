<?php
/*----------------------------------------------------------------------------------------------------------------------
Plugin Name: WooCommerce Stock Logs
Description: Establishes an audit log of adjustments to product stock.
Version: 1.0
Author: New Order Studios
Author URI: https://github.com/neworderstudios
----------------------------------------------------------------------------------------------------------------------*/

if ( is_admin() ) {
    new wcStockLogs();
}

class wcStockLogs {

	public function __construct() {
		global $pagenow;
		define( 'WC_STOCKLOGS_TABLE', 'woocommerce_stock_adjustments' );

		load_plugin_textdomain( 'woocommerce-stock-logs', false, basename( dirname(__FILE__) ) . '/i18n' );

		add_action( 'init', array( $this, 'stocklogs_init_db' ) );
		add_action( 'wp_ajax_save_wcstock_adjustment', array( $this, 'save_stock_adjustment' ) );
		add_action( 'wp_ajax_load_wcstock_report', array( $this, 'render_report_meta_box' ) );

		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links' ) );
		add_filter( 'woocommerce_inventory_settings', array( $this, 'render_plugin_settings' ) );

		if ( in_array( $pagenow, array( 'post.php' ) ) ) add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
	}

	/**
	 * Let's add the adjustment & report metaboxes.
	 */
	public function add_metaboxes( $post_type ) {
		if ( $post_type == 'product' ) {

			add_meta_box(
				'stock_adjustment_inputs'
				,__( 'Adjust Stock Quantity', 'woocommerce-stock-logs' )
				,array( $this, 'render_adjustment_meta_box' )
				,$post_type
				,'side'
				,'core'
			);

			add_meta_box(
				'stock_adjustment_log'
				,__( 'Product Stock History', 'woocommerce-stock-logs' )
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
		$ajax_submit = "if(!jQuery('#stock_adjustment_inputs input').val() || !jQuery('#stock_adjustment_inputs select').val()){ alert('" . __( 'Please complete all fields.', 'woocommerce-stock-logs' ) . "'); }else{ {$ajax_before}jQuery.post(ajaxurl + '?action=save_wcstock_adjustment',{$ajax_data},{$ajax_success}); }return false;";

		$adjustment_notes = array(
			'' => __( 'Select an action', 'woocommerce-stock-logs' ),
			__( 'New arrival', 'woocommerce-stock-logs' ),
			__( 'Damaged goods', 'woocommerce-stock-logs' ),
			__( 'Delivery incomplete', 'woocommerce-stock-logs' ),
			__( 'Stock movement', 'woocommerce-stock-logs' ),
			__( 'Other…', 'woocommerce-stock-logs' ) );
		$unit_label_field = get_option( 'wc_stocklogs_acf_label', 1 );
		$unit_label = $unit_label_field ? get_field( $unit_label_field, $post->ID ) : NULL;

		echo '<input type="number" id="product_stock_adjustment" placeholder="0" style="width:' . ( $unit_label ? 70 : 100 ) . '%;" />' . ( $unit_label ? "<span style='display:inline-block;width:29%;text-align:center;'> x $unit_label</span>" : '' );
		echo '<select id="product_stock_adjustment_notes" style="width:100%;margin-top:5px;">';
		foreach ( $adjustment_notes as $k => $n ) echo '<option value="' . (!is_numeric($k) ? $k : $n) . '">' . $n . '</option>';
		echo '</select>';
		echo '<a class="button button-primary button-large" href="#" onclick="' . $ajax_submit . '" style="margin-top:5px;">' . __( 'Save Adjustment', 'woocommerce-stock-logs' ) . '</a> ';
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
		$adjustments = $wpdb->get_results( "select * from " . $wpdb->prefix.WC_STOCKLOGS_TABLE . " where post_ID = {$post_ID} order by `date` desc" );

		echo '<style type="text/css">#stock_adjustment_log th { border-bottom:1px solid #eee;padding-bottom:2px; } #stock_adjustment_log td { padding-top:5px; }</style>';
		echo '<table cellpadding="0" cellspacing="0" width="100%">';
		echo '<thead><tr><th align="left">' . __( 'Date / Time', 'woocommerce-stock-logs' ) . '</th><th align="left">' . __( 'User', 'woocommerce-stock-logs' ) . '</th><th align="right">' . __( 'Initial Quantity', 'woocommerce-stock-logs' ) . '</th><th align="right">' . __( 'Adjustment', 'woocommerce-stock-logs' ) . '</th><th align="right" style="padding-right:20px;">' . __( 'Adjusted Quantity', 'woocommerce-stock-logs' ) . '</th><th align="left">' . __( 'Note', 'woocommerce-stock-logs' ) . '</th></thead><tbody>';
		foreach ( $adjustments as $a ) {
			$user = get_user_by( 'id', $a->user_ID );
			$initial_quantity = $a->adjusted_quantity - $a->adjustment;
			echo "<tr><td align='left'>{$a->date}</td><td align='left'>{$user->display_name}</td><td align='right'>" . $initial_quantity . "</td><td align='right'>" . ( $a->adjustment >= 0 ? '+' : '' ) . $a->adjustment . "</td><td align='right' style='padding-right:20px;'>" . ( $a->adjusted_quantity ) . "</td><td align='left'>{$a->notes}</td></tr>";
		}
		echo '</tbody></table>';

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) die();
	}

	/**
	 * Let's add the Settings link on the plugin page
	 */
	public function plugin_action_links( $links ) {
	   return array_merge( array( '<a href="admin.php?page=wc-settings&tab=products&section=inventory">Settings</a>' ), $links );
	}

	/**
	 * Let's update the Settings page
	 */
	public function render_plugin_settings( $settings ) {
		$select_fields = array('' => '');
		$acf_fields = get_posts( array( 'post_type' => 'acf-field' ) );
		foreach ( $acf_fields as $field ) $select_fields[$field->post_excerpt] = $field->post_title;

		$updated_settings = array();
		foreach ( $settings as $section ) {
			$updated_settings[] = $section;

			if ( isset( $section['id'] ) && 'inventory_options' == $section['id'] && isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
				$updated_settings[] = array(
					'name' => __( 'Stock Log Options', 'woocommerce-stock-logs' ),
					'type' => 'title',
					'id'   => 'wc_stocklogs_global_settings',
				);
				$updated_settings[] = array(
					'title'		=> __( 'Unit / Quantity Label', 'woocommerce-stock-logs' ),
					'desc'		=> __( 'Optionally specify an ACF field to designate products\' sale unit (e.g. "0.5kg")', 'woocommerce-stock-logs' ),
					'id'		=> 'wc_stocklogs_acf_label',
					'default'	=> '',
					'type'		=> 'select',
					'options'	=> $select_fields,
					'desc_tip'	=>  true
				);
				$updated_settings[] = array( 'type' => 'sectionend', 'id' => 'wc_stocklogs_settings' );
			}
		}

		return $updated_settings;
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
	function stocklogs_init_db() {
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
