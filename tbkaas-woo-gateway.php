<?php

namespace tbkaaswoogateway;

/*
  Plugin Name: Pago Fácil
  Plugin URI:  http://www.pagofacil.cl
  Description: Vende con distintos medios de pago en tu tienda de manera instantánea con Pago Fácil.
  Version:     1.4.2
  Author:      Cristian Tala Sánchez
  Author URI:  http://www.cristiantala.cl
  License:     MIT
  License URI: http://opensource.org/licenses/MIT
  Domain Path: /languages
  Text Domain: ctala-text_domain
 */

include_once 'vendor/autoload.php';
use WC_Order;

use tbkaaswoogateway\classes\WC_Gateway_TBKAAS;

//VARIABLES
//Funciones
add_action('plugins_loaded', 'tbkaaswoogateway\init_TBKAAS');

function init_TBKAAS()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_TBKAAS_Chile extends WC_Gateway_TBKAAS
    {
    }
}

function add_your_gateway_class($methods)
{
    $methods[] = 'tbkaaswoogateway\WC_Gateway_TBKAAS_Chile';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'tbkaaswoogateway\add_your_gateway_class');

function custom_meta_box_markup($post)
{
    $order_id = $post->ID;
  
    $codigoAuth = get_post_meta($order_id, "_gateway_reference", true);
    if ($codigoAuth!="") {
        include(plugin_dir_path(__FILE__) . '/templates/order_recibida.php');
    } else {
        echo "<p>";
        echo "No existe información relacionada al pedidoa.";
        echo "</p>";
    }
}

function add_custom_meta_box()
{
    add_meta_box("pagofacil-meta-box", "PagoFácil Meta Data", "tbkaaswoogateway\custom_meta_box_markup", "shop_order", "side", "high", null);
}

add_action("add_meta_boxes", "tbkaaswoogateway\add_custom_meta_box");
