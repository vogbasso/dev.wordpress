<?php
/** Function Pour changer l'email de wordpress@dansmamaison.ma à info@dansmamaison.ma to change email address***/
 // changement de l'emain
function wpb_sender_email( $original_email_address ) {
    return 'info@dansmamaison.ma';
}
 
/*** Function pour changer le nom de l'envoyeur **/
//change sender name
function wpb_sender_name( $original_email_from ) {
    return 'Dansmamaison';
}
 /*** Le filter qui applique les modifications***/
// Hooking up our functions to WordPress filters 
add_filter( 'wp_mail_from', 'wpb_sender_email' );
add_filter( 'wp_mail_from_name', 'wpb_sender_name' );


/** Enlever le champ State de la page checkout
 * 
 */
function vogelpaolo_remove_state_field( $fields ) {
	unset( $fields['state'] );
	return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'vogelpaolo_remove_state_field' );

/**
 * Change a currency symbol
 */
add_filter('woocommerce_currency_symbol', 'change_existing_currency_symbol', 10, 2);

function change_existing_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'MAD': $currency_symbol = 'DH'; break;
     }
     return $currency_symbol;
}


// AJOUTER LE PRIX BARRE APRES USAGE DE CODE PROMO

add_filter( 'woocommerce_cart_item_price', 'vogelpaolo_change_cart_table_price_display', 30, 3 );
 
function vogelpaolo_change_cart_table_price_display( $price, $values, $cart_item_key ) {
$slashed_price = $values['data']->get_price_html();
$is_on_sale = $values['data']->is_on_sale();
if ( $is_on_sale ) {
 $price = $slashed_price;
}
return $price;
}

// ADD SAVED AMOUNT

function you_save_echo_product() {
	global $product;

	// works for Simple and Variable type
	$regular_price 	= get_post_meta( $product->get_id(), '_regular_price', true ); // 36.32
	$sale_price 	= get_post_meta( $product->get_id(), '_sale_price', true ); // 24.99
		
	if( !empty($sale_price) ) {
	
		$saved_amount 		= $regular_price - $sale_price;
		$currency_symbol 	= get_woocommerce_currency_symbol();

		$percentage = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
		?>
			<p class="you_save_price">Vous économisez sur cet article : <?php echo ''. number_format($saved_amount, 2, '.', '').' '.$currency_symbol; ?></p>				
		<?php		
	} 
		
}
add_action( 'woocommerce_single_product_summary', 'you_save_echo_product', 11 ); // hook number


// AJOUT DU THUMBNAIL IMAGE SUR LA PAGE DE COMMANDE CHECKOUT

function isa_woo_cart_attributes($cart_item, $cart_item_key){ 
	global $product; 
	if (is_cart()){ echo "<style>#checkout_thumbnail{display:none;}</style>"; } 
	$item_data = $cart_item_key['data']; 
	$post = get_post($item_data->id); 
	$thumb = get_the_post_thumbnail($item_data->id, array( 32, 50)); 
	echo '<div id="checkout_thumbnail" style="float: left; padding-right: 8px">' . $thumb . '</div> ' . $post->post_title; } add_filter('woocommerce_cart_item_name', 'isa_woo_cart_attributes', 10, 2);


// CHANGEMENT DE L'EMAIL EN AJOUTANT LES THUMBNAILS OU IMAGE
// Edit order items table template defaults
function sww_add_wc_order_email_images( $table, $order ) {
  
	ob_start();
	
	$template = $plain_text ? 'emails/plain/email-order-items.php' : 'emails/email-order-items.php';
	wc_get_template( $template, array(
		'order'                 => $order,
		'items'                 => $order->get_items(),
		'show_download_links'   => $show_download_links,
		'show_sku'              => true,
		'show_purchase_note'    => $show_purchase_note,
		'show_image'            => true,
		'image_size'            => array( 100, 50 )
	) );
   
	return ob_get_clean();
}
add_filter( 'woocommerce_email_order_items_table', 'sww_add_wc_order_email_images', 10, 2 );

/*** MODIFICATION DES VILLES EN LISTE*/
/**
 * Changement du Checkout page avec les villes déjà défini.
 */
function ace_change_city_to_dropdown( $fields ) {

	$cities = array(
		'Ait Melloul',
		'Al Hoceima',
		'Agadir',
		'Assazag',
		'Azrou',
		'Benguerir',
		'Beni Mellal',
		'Berkane',
		'Berrechid',
		'Bouarfa',
		'Boujdour',
		'Bouskoura',
		'Bouznika',
		'Casablanca',
		'Dakhla',
		'Dar Bouaza',
		'El Jadida',
		'Errachidia',
		'Essaouira',
		'Fes',
		'Fkih Ben Salah',
		'Guelmim',
		'Guercif',
		'Ifrane',
		'Inzegan',
		'Jerrada',
		'Kalaa des sraghna',
		'Kasba Tadla',
		'Khemisset',
		'Kénitra',
		'Khenifra',
		'Khouribga',
		'Larache',
		'Laayoune',
		'Laayoune Charquia',
        'Martil',
		'Marrakech',
		'Midlet',
		'Meknès',
		'Mohammédia',
		'Nador',
		'Nouaceur',
		'Ouarzazate',
		'Ouazzane',
		'Oujda',
		'Rabat',
		'Safi',
		'Salé',
		'Settat',
		'Skhirat',
		'Tanger',
		'Tan-tan',
		'Taourirt',
		'Taroudant',
		'Taza',
		'Témara',
		'Tetouan',
		'Tinghir',
		'Tiznit',
		'Zaio',
		// etc
	);

	$city_args = wp_parse_args( array(
		'type' => 'select',
		'options' => array_combine( $cities, $cities ),
	), $fields['shipping']['shipping_city'] );

	$fields['shipping']['shipping_city'] = $city_args;
	$fields['billing']['billing_city'] = $city_args;

	return $fields;

}
add_filter( 'woocommerce_checkout_fields', 'ace_change_city_to_dropdown' ); 

// AJOUT SUR LES PAGES SHOP DE LA QUANTITE EN STOCK 
function bbloomer_show_stock_shop() {
   global $product;
   if($product->is_in_stock()){
	   echo wc_get_stock_html( $product );
   }
}
add_action( 'woocommerce_after_shop_loop_item', 'bbloomer_show_stock_shop' );

//* Ajout sur la page Single product de produit de l'information article en rupture de stock
add_action( 'woocommerce_before_single_product_summary', function() {
	global $product;

	if ( !$product->is_in_stock() ) {
	echo '<span class="soldout">Rupture de stock</span>';
	}elseif( $product->is_in_stock() and !$product->is_on_backorder() ){ 
		echo '<span class="soldin"> Disponible en stock : '.$product->get_stock_quantity().' </span>'; //.$product->get_stock_quantity().
	}
});

//* Redirection après add to cart à la page précédente au lieu la page SHop
//add_filter( 'woocommerce_return_to_shop_redirect', 'njengah_return_to_shop_to_previous_page' );
//function njengah_redirect_return_to_shop_to_previous_page() {
//	return $_SERVER['HTTP_REFERER'];         
//}