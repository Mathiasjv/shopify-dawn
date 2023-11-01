<?php

// Setup the price for manual orders based of the price setup in pricing rules

function update_order_prices_on_admin_ajax( $item_id, $item, $order ){
		error_log("Customer ".$order->get_customer_id());
		
		if($order->get_customer_id()==0)
		{
			return;
		}
	foreach ( $order->get_items() as $order_item_id => $order_item_data ) {
		
		
		if ( $order_item_id == $item_id ) {
			
			$product = $order_item_data->get_product();

			$newRegularPrice = $order_item_data->get_subtotal();

				$categories = get_the_terms( $product->get_id(), 'product_cat' );
				$category_id = $categories?reset($categories)->term_id:0;
				$args = array(
				  'numberposts' => -1,
				  'post_type'   => 'rcbp-rule',
					'meta_query' => array(
							'relation' => 'AND',
								array(
									'key'     => '_rps_included_users',
									'value'   => serialize( intval( $order->get_user_id() ) ), // Saved as integer
									'compare' => 'LIKE',
								),
							 	array(
									'key'     => '_rps_is_suspended',
									'value'   => "no", // Saved as string
									'compare' => 'LIKE',
								),
								array(
									'relation' => 'OR', // Lets it know that all of the following is acceptable
									array(
										'key'     => '_rps_included_products',
										'value'   => serialize( intval(  $product->get_id() ) ), // Saved as integer
										'compare' => 'LIKE',
									),
									array(
										'key'     => '_rps_included_categories',
										'value'   => serialize( intval( $category_id ) ), // Saved as integer
										'compare' => 'LIKE',
									),
								)));
				$mrules = get_posts( $args );
				foreach($mrules as $mrule){
					$users = get_post_meta($mrule->ID,"_rps_included_users");
					error_log($order->get_user_id()." in ". json_encode($users));
				}
				
				

				if($mrules){
					$price = get_post_meta(reset($mrules)->ID,"_rcbp_sale_price",true);
					error_log("Old Price = ".$newRegularPrice);
					error_log("Sale Price = ".$price);
					$newRegularPrice = floatval($price);
					
					if($newRegularPrice){
						wc_update_order_item_meta($order_item_id, '_old_line_total', $newRegularPrice*$order_item_data->get_quantity() );
						wc_update_order_item_meta($order_item_id, '_old_line_subtotal', $newRegularPrice );
						$order_item_data->set_subtotal($newRegularPrice);
						$order_item_data->set_total($newRegularPrice*$order_item_data->get_quantity());
						$order_item_data->calculate_taxes();
						$order_item_data->apply_changes();
						$order_item_data->save();
						wc_update_order_item_meta($order_item_id, '_line_total', $newRegularPrice*$order_item_data->get_quantity() );
						wc_update_order_item_meta($order_item_id, '_line_subtotal', $newRegularPrice );
					}
				}else{
					error_log("Ooops! Nothing Found!");
				}
			
		}
	}

  
	// Runs this after making a change to $order_item_data
	$order->calculate_totals();
	$order->apply_changes();
	$order->save();
	
	
}
add_action( 'woocommerce_ajax_add_order_item_meta', 'update_order_prices_on_admin_ajax', 99, 3 );


/**
 * Apply customer pricing rules to an order.
 *
 * @param int $order_id The ID of the order to apply pricing rules to.
 */
function apply_customer_pricing_rules( $_product, $item,  $item_id) {
    // Get the customer ID from the order.
    // Load the global $post
	global $woocommerce, $post;
	error_log("Item id".$item_id);
	if(!$post){
		return;
	}
	// Get the post ID
	$order_id = $post->ID;
    $order = wc_get_order( $order_id );
	
	if($order->get_user_id()==0){
		return;
	}
	
	if ($order) {
    foreach ( $order->get_items() as $order_item_id => $order_item_data ) {
		error_log("Order Item id ".$order_item_id);
		if ( $order_item_id == $item_id ) {
			
			$product = $item->get_product();

			$newRegularPrice = $item->get_subtotal();
			if(!$order->has_status('completed')){
				$categories = get_the_terms( $product->get_id(), 'product_cat' );
				$category_id = $categories?reset($categories)->term_id:0;
		
				$args = array(
				  'numberposts' => -1,
				  'post_type'   => 'rcbp-rule',
					'meta_query' => array(
							'relation' => 'AND',
								array(
									'key'     => '_rps_included_users',
									'value'   => serialize( intval( $order->get_user_id() ) ), // Saved as integer
									'compare' => 'LIKE',
								),
							 	array(
									'key'     => '_rps_is_suspended',
									'value'   => "no", // Saved as string
									'compare' => 'LIKE',
								),
								array(
									'relation' => 'OR', // Lets it know that all of the following is acceptable
									array(
										'key'     => '_rps_included_products',
										'value'   => serialize( intval(  $product->get_id() ) ), // Saved as integer
										'compare' => 'LIKE',
									),
									array(
										'key'     => '_rps_included_categories',
										'value'   => serialize( intval( $category_id ) ), // Saved as integer
										'compare' => 'LIKE',
									),
								)));
				$mrules = get_posts( $args );

				if($mrules){
					$price = get_post_meta(reset($mrules)->ID,"_rcbp_sale_price",true);
// 					error_log("Old Price = ".$newRegularPrice);
// 					error_log("Sale Price = ".$price);
					$newRegularPrice = floatval($price);
					
					if($newRegularPrice){
						$old_total = wc_get_order_item_meta( $item_id, '_old_line_total');

						if(empty($old_total)){
							$old_total = $item->get_subtotal();
							
							wc_add_order_item_meta($item_id, '_old_line_total',$old_total );
						}
						
						
						$item->set_subtotal($newRegularPrice);
						$item->set_total($newRegularPrice*$order_item_data->get_quantity());
						$item->calculate_taxes();
						$item->apply_changes();
						$item->save();
						
						wc_update_order_item_meta($item_id, '_line_total', $newRegularPrice*$order_item_data->get_quantity() );
						wc_update_order_item_meta($item_id, '_line_subtotal', $newRegularPrice );

					}
				}else{
					error_log("Ooops! Nothing Found!");
				}
			}
// 			else{
// 				$args = array(
// 				  'numberposts' => -1,
// 				  'post_type'   => 'rcbp-rule',
// 					'meta_query' => array(
// 							'relation' => 'AND',
// 								array(
// 									'key'     => '_rps_included_users',
// 									'value'   => serialize( intval( $order->get_user_id() ) ), // Saved as integer
// 									'compare' => 'LIKE',
// 								),
// 							 	array(
// 									'key'     => '_rps_is_suspended',
// 									'value'   => "no", // Saved as string
// 									'compare' => 'LIKE',
// 								),
// 								array(
// 									'relation' => 'OR', // Lets it know that all of the following is acceptable
// 									array(
// 										'key'     => '_rps_included_products',
// 										'value'   => serialize( intval(  $product->get_id() ) ), // Saved as integer
// 										'compare' => 'LIKE',
// 									),
// 									array(
// 										'key'     => '_rps_included_categories',
// 										'value'   => serialize( intval( 0 ) ), // Saved as integer
// 										'compare' => 'LIKE',
// 									),
// 								)));
// 				$mrules = get_posts( $args );
// 				if($order_id == 15320 && $mrules){
					
// 					$newRegularPrice = 43;
// 					$item->set_subtotal($newRegularPrice);
// 					$item->set_total($newRegularPrice*$order_item_data->get_quantity());
// 					$item->calculate_taxes();
// 					$item->apply_changes();
// 					$item->save();

// 					wc_update_order_item_meta($item_id, '_line_total', $newRegularPrice*$order_item_data->get_quantity() );
// 					wc_update_order_item_meta($item_id, '_line_subtotal', $newRegularPrice );
// 				}
				
// 			}
			
		}
	}

    // Recalculate order totals.
    $order->calculate_totals();
		$order->apply_changes();
	$order->save();
}else{
		error_log("Ooops! Nothing Found!");
	}
}
add_action( 'woocommerce_admin_order_item_values', 'apply_customer_pricing_rules', 10, 3 );

