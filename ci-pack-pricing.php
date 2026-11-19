<?php
/**
 * Plugin Name:       Pack & Unit Pricing for WooCommerce
 * Plugin URI:        https://github.com/codeignitioneu/ci-pack-pricing
 * Description:       Vânzare la bucată sau la set (pachet) + cantitate minimă de comandă. Prețul WooCommerce = prețul unității de vânzare (setul complet); pe pagina produsului se afișează prețul / bucată, prețul / set și comanda minimă.
 * Version:           2.0.0
 * Author:            Code Ignition SRL
 * Author URI:        https://github.com/codeignitioneu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       ci-pack-pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CI_PACK_PRICING_FILE', __FILE__ );

final class CI_Pack_Pricing {

	const VERSION   = '2.0.0';
	const META_UNIT = '_ci_sale_unit';    // 'piece' | 'set'
	const META_QTY  = '_ci_pack_qty';     // bucăți într-un set
	const META_MIN  = '_ci_min_qty';      // cantitate minimă (în unități de vânzare)
	const OPT_FOCUS = 'ci_pp_focus';      // 'piece' | 'set' – ce preț e afișat mare

	/** Chei meta din versiunea 1.x, citite doar ca rezervă (migrare). Se elimină în 2.1. */
	const LEGACY = array(
		'_ci_sale_unit' => '_domix_unit',
		'_ci_pack_qty'  => '_domix_set_qty',
		'_ci_min_qty'   => '_domix_min_qty',
	);

	/** Citește o cheie meta, cu rezervă pe cheia veche. */
	private static function meta( $product, $key ) {
		$value = $product->get_meta( $key, true );
		if ( '' === $value && isset( self::LEGACY[ $key ] ) ) {
			$value = $product->get_meta( self::LEGACY[ $key ], true );
		}
		return $value;
	}

	public static function init() {
		self::updater();
		// Admin: câmpuri în tab-ul General al produsului.
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'admin_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'admin_save' ) );

		// Admin: coloană în lista de produse.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'admin_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'admin_column_value' ), 10, 2 );

		// Setare: WooCommerce > Setări > Produse.
		add_filter( 'woocommerce_product_settings', array( __CLASS__, 'settings' ) );

		// Front: afișare preț.
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'single_notice' ), 25 );

		// Coș + comandă: informația despre set.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );

		// Cantitate minimă.
		add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'qty_input_args' ), 20, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_add_to_cart_args' ), 20, 2 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'store_api_min' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add' ), 20, 4 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_update' ), 20, 4 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );

		add_action( 'wp_head', array( __CLASS__, 'styles' ) );
	}

	/* ------------------------------------------------------------
	 * Actualizări automate din GitHub (releases / tags)
	 * ------------------------------------------------------------ */

	private static function updater() {
		$lib = __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $lib ) ) {
			return;
		}
		require_once $lib;
		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v6\\PucFactory' ) ) {
			return;
		}
		$checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
			'https://github.com/codeignitioneu/ci-pack-pricing/',
			CI_PACK_PRICING_FILE,
			'ci-pack-pricing'
		);
		$checker->setBranch( 'main' );
		$checker->getVcsApi()->enableReleaseAssets( '/ci-pack-pricing\\.zip($|[?&#])/i' );
	}

	/* ------------------------------------------------------------
	 * Cantitate minimă
	 * ------------------------------------------------------------ */

	public static function min_qty( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 1;
		}
		$source = $product;
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$source = $parent;
			}
		}
		return max( 1, absint( self::meta( $source, self::META_MIN ) ) );
	}

	private static function unit_word( $product, $qty ) {
		$p = self::profile( $product );
		if ( 'set' === $p['unit'] ) {
			return 1 === $qty ? 'set' : 'seturi';
		}
		return 1 === $qty ? 'bucată' : 'bucăți';
	}

	public static function qty_input_args( $args, $product ) {
		$min = self::min_qty( $product );
		if ( $min > 1 ) {
			$args['min_value'] = $min;
			if ( empty( $args['input_value'] ) || (int) $args['input_value'] < $min ) {
				$args['input_value'] = $min;
			}
		}
		return $args;
	}

	public static function loop_add_to_cart_args( $args, $product ) {
		$min = self::min_qty( $product );
		if ( $min > 1 ) {
			$args['quantity'] = $min;
		}
		return $args;
	}

	public static function store_api_min( $value, $product ) {
		$min = self::min_qty( $product );
		return $min > 1 ? max( (int) $value, $min ) : $value;
	}

	private static function qty_in_cart( $product_id ) {
		$total = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === (int) $product_id ) {
					$total += (int) $item['quantity'];
				}
			}
		}
		return $total;
	}

	public static function validate_add( $passed, $product_id, $quantity, $variation_id = 0 ) {
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		$min     = self::min_qty( $product );
		if ( $passed && $min > 1 && ( self::qty_in_cart( $product_id ) + (int) $quantity ) < $min ) {
			wc_add_notice(
				sprintf( 'Comanda minimă pentru „%s” este de %d %s.', $product->get_name(), $min, self::unit_word( $product, $min ) ),
				'error'
			);
			return false;
		}
		return $passed;
	}

	public static function validate_update( $passed, $cart_item_key, $values, $quantity ) {
		$product = isset( $values['data'] ) ? $values['data'] : null;
		$min     = self::min_qty( $product );
		if ( $passed && $min > 1 && (int) $quantity > 0 && (int) $quantity < $min ) {
			wc_add_notice(
				sprintf( 'Comanda minimă pentru „%s” este de %d %s.', $product->get_name(), $min, self::unit_word( $product, $min ) ),
				'error'
			);
			return false;
		}
		return $passed;
	}

	public static function check_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		$totals = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$pid = (int) $item['product_id'];
			$totals[ $pid ] = ( isset( $totals[ $pid ] ) ? $totals[ $pid ] : 0 ) + (int) $item['quantity'];
		}
		foreach ( $totals as $pid => $qty ) {
			$product = wc_get_product( $pid );
			$min     = self::min_qty( $product );
			if ( $min > 1 && $qty < $min ) {
				wc_add_notice(
					sprintf( 'Comanda minimă pentru „%s” este de %d %s. Ai în coș %d.', $product->get_name(), $min, self::unit_word( $product, $min ), $qty ),
					'error'
				);
			}
		}
	}

	/* ------------------------------------------------------------
	 * Profil produs
	 * ------------------------------------------------------------ */

	/**
	 * @return array{unit:string,qty:int}
	 */
	public static function profile( $product ) {
		$profile = array( 'unit' => 'piece', 'qty' => 1 );

		if ( ! $product instanceof WC_Product || $product->is_type( 'variable' ) ) {
			return $profile;
		}

		$source = $product;
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$source = $parent;
			}
		}

		$unit = (string) self::meta( $source, self::META_UNIT );
		$qty  = absint( self::meta( $source, self::META_QTY ) );

		if ( 'set' === $unit && $qty > 1 ) {
			$profile = array( 'unit' => 'set', 'qty' => $qty );
		}

		return $profile;
	}

	private static function money( $amount ) {
		$decimals = ( $amount > 0 && $amount < 0.1 ) ? 3 : wc_get_price_decimals();
		return wc_price( $amount, array( 'decimals' => $decimals ) );
	}

	/* ------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------ */

	public static function admin_fields() {
		global $product_object;
		$unit = $product_object ? (string) self::meta( $product_object, self::META_UNIT ) : '';
		$qty  = $product_object ? absint( self::meta( $product_object, self::META_QTY ) ) : 0;

		echo '<div class="options_group ci-pp-admin">';

		woocommerce_wp_select(
			array(
				'id'          => self::META_UNIT,
				'label'       => 'Unitate de vânzare',
				'value'       => $unit ? $unit : 'piece',
				'options'     => array(
					'piece' => 'Bucată',
					'set'   => 'Set (mai multe bucăți)',
				),
				'desc_tip'    => true,
				'description' => 'La „Set”, prețul de mai sus este prețul SETULUI COMPLET. Clientul vede și prețul / bucată.',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_QTY,
				'label'             => 'Bucăți în set',
				'value'             => $qty ? $qty : '',
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '2', 'step' => '1' ),
				'desc_tip'          => true,
				'description'       => 'Numărul de bucăți dintr-un set. Folosit doar pentru afișarea prețului / bucată.',
			)
		);

		$min = $product_object ? absint( self::meta( $product_object, self::META_MIN ) ) : 0;
		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MIN,
				'label'             => 'Cantitate minimă',
				'value'             => $min > 1 ? $min : '',
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
				'desc_tip'          => true,
				'description'       => 'Numărul minim de unități de vânzare (bucăți sau seturi) pe comandă. Gol sau 1 = fără minim.',
			)
		);

		echo '</div>';
	}

	public static function admin_save( $product ) {
		if ( isset( $_POST[ self::META_UNIT ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$unit = sanitize_key( wp_unslash( $_POST[ self::META_UNIT ] ) ); // phpcs:ignore
			$unit = in_array( $unit, array( 'piece', 'set' ), true ) ? $unit : 'piece';
			$product->update_meta_data( self::META_UNIT, $unit );
		}
		if ( isset( $_POST[ self::META_QTY ] ) ) { // phpcs:ignore
			$product->update_meta_data( self::META_QTY, absint( wp_unslash( $_POST[ self::META_QTY ] ) ) ); // phpcs:ignore
		}
		if ( isset( $_POST[ self::META_MIN ] ) ) { // phpcs:ignore
			$product->update_meta_data( self::META_MIN, absint( wp_unslash( $_POST[ self::META_MIN ] ) ) ); // phpcs:ignore
		}
	}

	public static function admin_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'price' === $key ) {
				$new['ci_pp_unit'] = 'Unitate';
			}
		}
		if ( ! isset( $new['ci_pp_unit'] ) ) {
			$new['ci_pp_unit'] = 'Unitate';
		}
		return $new;
	}

	public static function admin_column_value( $column, $post_id ) {
		if ( 'ci_pp_unit' !== $column ) {
			return;
		}
		$product = wc_get_product( $post_id );
		$p       = self::profile( $product );
		echo 'set' === $p['unit'] ? esc_html( 'Set ' . $p['qty'] . ' buc' ) : 'Bucată';
		$min = self::min_qty( $product );
		if ( $min > 1 ) {
			echo '<br><small>' . esc_html( 'Min. ' . $min ) . '</small>';
		}
	}

	public static function settings( $settings ) {
		$settings[] = array(
			'title' => 'Pack & Unit Pricing – afișare preț',
			'type'  => 'title',
			'id'    => 'ci_pp_section',
		);
		$settings[] = array(
			'title'   => 'Preț afișat mare',
			'id'      => self::OPT_FOCUS,
			'type'    => 'select',
			'default' => 'piece',
			'options' => array(
				'piece' => 'Prețul / bucată (setul dedesubt)',
				'set'   => 'Prețul / set (bucata dedesubt)',
			),
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'ci_pp_section',
		);
		return $settings;
	}

	/* ------------------------------------------------------------
	 * Front
	 * ------------------------------------------------------------ */

	public static function price_html( $html, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}

		$p = self::profile( $product );
		if ( 'set' !== $p['unit'] ) {
			return $html;
		}

		$set_price = (float) wc_get_price_to_display( $product );
		if ( $set_price <= 0 ) {
			return $html;
		}

		$qty       = $p['qty'];
		$piece     = $set_price / $qty;
		$suffix    = $product->get_price_suffix();
		$on_sale   = $product->is_on_sale();
		$regular   = $on_sale ? (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) : 0;
		$focus     = get_option( self::OPT_FOCUS, 'piece' );

		$set_html = $on_sale && $regular > $set_price
			? wc_format_sale_price( wc_price( $regular ), wc_price( $set_price ) )
			: wc_price( $set_price );

		$piece_html = $on_sale && $regular > $set_price
			? wc_format_sale_price( self::money( $regular / $qty ), self::money( $piece ) )
			: self::money( $piece );

		if ( 'set' === $focus ) {
			$out  = '<span class="ci-pp ci-pp--set">';
			$out .= '<span class="ci-pp__main">' . $set_html . ' <small>/ set ' . esc_html( $qty ) . ' buc</small></span>';
			$out .= '<span class="ci-pp__sub">' . $piece_html . ' <small>/ buc</small></span>';
		} else {
			$out  = '<span class="ci-pp ci-pp--piece">';
			$out .= '<span class="ci-pp__main">' . $piece_html . ' <small>/ buc</small></span>';
			$out .= '<span class="ci-pp__sub">Set ' . esc_html( $qty ) . ' buc: <strong>' . $set_html . '</strong></span>';
		}
		$out .= $suffix ? '<span class="ci-pp__suffix">' . $suffix . '</span>' : '';
		$out .= '</span>';

		return $out;
	}

	public static function single_notice() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$p     = self::profile( $product );
		$min   = self::min_qty( $product );
		$lines = array();

		if ( 'set' === $p['unit'] ) {
			$lines[] = 'Se vinde la set: <strong>1 = ' . esc_html( $p['qty'] ) . ' bucăți</strong>. Cantitatea din coș reprezintă numărul de seturi.';
		}
		if ( $min > 1 ) {
			$word = self::unit_word( $product, $min );
			$line = 'Comandă minimă: <strong>' . esc_html( $min ) . ' ' . esc_html( $word ) . '</strong>';
			if ( 'set' === $p['unit'] ) {
				$line .= ' (' . esc_html( $min * $p['qty'] ) . ' bucăți)';
			}
			$price = (float) wc_get_price_to_display( $product );
			if ( $price > 0 ) {
				$line .= ' · total minim ' . wp_kses_post( wc_price( $price * $min ) );
			}
			$lines[] = $line . '.';
		}
		if ( $lines ) {
			echo '<div class="ci-pp-notice">' . implode( '<br>', $lines ) . '</div>';
		}
	}

	public static function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['data'] ) ) {
			return $item_data;
		}
		$product = $cart_item['data'];
		$min     = self::min_qty( $product );
		if ( $min > 1 ) {
			$item_data[] = array(
				'key'   => 'Comandă minimă',
				'value' => $min . ' ' . self::unit_word( $product, $min ),
			);
		}
		$p = self::profile( $product );
		if ( 'set' !== $p['unit'] ) {
			return $item_data;
		}
		$piece = (float) wc_get_price_to_display( $product ) / $p['qty'];
		$item_data[] = array(
			'key'   => 'Set',
			'value' => $p['qty'] . ' bucăți / set (' . wp_strip_all_tags( self::money( $piece ) ) . ' / buc)',
		);
		return $item_data;
	}

	public static function order_item_meta( $item, $cart_item_key, $values ) {
		if ( empty( $values['data'] ) ) {
			return;
		}
		$p = self::profile( $values['data'] );
		if ( 'set' === $p['unit'] ) {
			$item->add_meta_data( 'Set', $p['qty'] . ' bucăți / set', true );
		}
	}

	public static function styles() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		?>
<style id="ci-pack-pricing">
.ci-pp{display:inline-flex;flex-direction:column;gap:2px;line-height:1.3}
.ci-pp__main{font-weight:700}
.ci-pp__main small,.ci-pp__sub small{font-weight:400;opacity:.8}
.ci-pp__sub{font-size:.8em;font-weight:400;color:#425f66}
.ci-pp__suffix{font-size:.7em;font-weight:400;opacity:.75}
.ci-pp-notice{margin:10px 0 16px;padding:10px 14px;border:1px solid #c8e6ed;border-left:4px solid #0191b1;border-radius:10px;background:#eff9fb;color:#174650;font-size:14px}
</style>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'CI_Pack_Pricing', 'init' ) );
