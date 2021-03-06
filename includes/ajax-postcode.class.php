<?php 

class Correios_Shipping_Ajax_Postcode {

	public function __construct() {

		add_action( 'wp_ajax_wscp_ajax_postcode', array($this,'wscp_ajax_postcode') );
		add_action( 'wp_ajax_nopriv_wscp_ajax_postcode', array($this,'wscp_ajax_postcode') );
	}

	public function wscp_ajax_postcode() {

		check_ajax_referer( 'wscp-nonce', 'nonce' );

		$data = $_POST;

		$shipping_response = $this->get_product_shipping_estimate( $data );

		if( !is_array($shipping_response) ){

			echo "<div class='woocommerce-message woocommerce-error'>".( $shipping_response ? $shipping_response : 'Nenhuma forma de entrega disponível.' )."</div>";
			
		} else {

			echo
			'<table cellspacing="0"  class="shop_table shop_table_responsive">
				<tbody>
					<tr class="shipping">
				 		<th>
							Entrega						
						</th>
						<th>
							Valor	 						
						</th>
				  	</tr>';

			foreach ($shipping_response as $key => $shipping) {
				
				echo
					'<tr class="shipping">	
						<td>
							'.$shipping->label.'  						
						</td>
						<td>
							'.wc_price( $shipping->cost ).'  						
						</td>
					</tr>';
				}

			if( get_option('wscip_obs','*Este resultado é apenas uma estimativa para este produto. O valor final considerado, deverá ser o total do carrinho.') ):
				echo "<tr><td colspan='2'>";
						echo get_option('wscip_obs');
				echo "</td></tr>";
			endif;

			echo
				'</tbody>
			</table>';
		}

		wp_die();
	}

	public function get_product_shipping_estimate( array $request ) {

	    $product = wc_get_product( sanitize_text_field( $request['product'] ) );
	    
	    if (!$product->needs_shipping() || get_option('woocommerce_calc_shipping') === 'no' )
	        return 'Não foi possível calcular a entrega deste produto';
	    
	    if( !$product->is_in_stock() )
	    	return 'Não foi possível calcular a entrega deste produto, pois o mesmo não está disponível.';

	    if( !WC_Validation::is_postcode( $request['postcode'], WC()->customer->get_shipping_country() ) )
	    	return 'Por favor, insira um CEP válido.';

	    $products = [$product];

	    if (WC()->customer->get_shipping_country()) {

	        $destination = [
	            'country' => WC()->customer->get_shipping_country(),
	            'state' => WC()->customer->get_shipping_state(),
	            'postcode' => sanitize_text_field( $request['postcode'] ),
	            'city' => WC()->customer->get_shipping_city(),
	            'address' => WC()->customer->get_shipping_address(),
	            'address_2' => WC()->customer->get_shipping_address_2(),
	        ];

	    } else {

	        $destination = wc_get_customer_default_location();
	    }

	    $package = [
	        'destination' => $destination,
	        'applied_coupons' => WC()->cart->applied_coupons,
	        'user' => ['ID' => get_current_user_id()],
	    ];

	    foreach ($products as $data) {

	        $cartId = WC()->cart->generate_cart_id($data->id, $product->is_type('variable') ? $data->variation_id : 0);

	        $price = $data->get_price_excluding_tax();

	        $tax = $data->get_price_including_tax() - $price;

	        $package['contents'] = [
	            $cartId => [
	                'product_id' => $data->id,
	                'data' => $data,
	                'quantity' => sanitize_text_field( $request['qty'] ),
	                'line_total' => $price,
	                'line_tax' => $tax,
	                'line_subtotal' => $price,
	                'line_subtotal_tax' => $tax,
	                'contents_cost' => $price,
	            ]
	        ];

	        $packageRates = WC_Shipping::instance()->calculate_shipping_for_package($package);

	        foreach ($packageRates['rates'] as $rate) {
	            
	            $rate->product = $data->get_name();
	            $rate->availability = $data->get_name();
	            $rates[] = $rate;

	        }
	    }
	    return $rates;
	}
}

new Correios_Shipping_Ajax_Postcode();