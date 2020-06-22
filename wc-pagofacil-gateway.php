<?php

namespace wcpagofacilgateway;

/*
  Plugin Name: Pago Fácil
  Plugin URI:  http://www.pagofacil.cl
  Description: Vende con distintos medios de pago en tu tienda de manera instantánea con Pago Fácil.
  Version:     2.0.1
  Author:      Cristian Tala Sánchez
  Author URI:  http://www.cristiantala.cl
  License:     MIT
  License URI: http://opensource.org/licenses/MIT
  Domain Path: /languages
  Text Domain: ctala-text_domain
 */

include_once 'vendor/autoload.php';
use WC_Order;

use wcpagofacilgateway\classes\WC_Pagofacil_Gateway;

use PagoFacilCore\EnvironmentEnum;

//registra parametros de configuración como: ambiente, titulo, y tokens (service - secret) por defecto al activar plugin, solo si existen registros de pago facil -> woocommerce_tbkaas_settings (plugin no customizado)
register_activation_hook( __FILE__, function() {
    // buscamos si existen opciones de plugin pago facil -> woocommerce_tbkaas_settings (plugin no customizado)
    $options = get_option('woocommerce_tbkaas_settings');
    if (!empty( $options )) { 
        // homologación de parametros de ambientes entre plugins -> woocommerce_tbkaas_settings (plugin no customizado) - woocommerce_pagofacil_settings (plugin customizado)
        switch ($options['ambiente']) {
            case 'PRODUCCION':
                $ambiente = EnvironmentEnum::PRODUCTION;
                break;
            case 'DESARROLLO':
                $ambiente = EnvironmentEnum::DEVELOPMENT;
                break;
            case 'BETA':
                $ambiente = EnvironmentEnum::BETA;
                break;
        }
        $setting_options = array(

            'enabled'        => 'yes',
            'ambiente'       => $ambiente,
            'title'          => __('Pago Fácil', 'woocommerce'),
            'description'    => __('Sistema de pago con tarjetas de crédito y débito chilenas.'),
            'token_service'  =>  $options['token_service'],
            'token_secret'   => $options['token_secret'],
            'redirect'       => 'yes'

        );
        add_option('woocommerce_pagofacil_settings', $setting_options, '', 'yes');
    }
});

//VARIABLES
//Funciones
add_action('plugins_loaded', 'wcpagofacilgateway\init_module');

function init_module()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Pagofacil_Main_Gateway extends WC_Pagofacil_Gateway
    {
    }
}

function add_your_gateway_class($methods)
{
    $methods[] = 'wcpagofacilgateway\WC_Pagofacil_Main_Gateway';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'wcpagofacilgateway\add_your_gateway_class');

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
    add_meta_box("pagofacil-meta-box", "PagoFácil Meta Data", "wcpagofacilgateway\custom_meta_box_markup", "shop_order", "side", "high", null);
}

add_action("add_meta_boxes", "wcpagofacilgateway\add_custom_meta_box");


//permite sobreescribir template de woocommerce.
function woo_adon_plugin_template( $template, $template_name, $template_path ) {
    global $woocommerce;
    $_template = $template;
    if ( ! $template_path )  {
        $template_path = $woocommerce->template_url;
    }
    

    $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) )  . '/templates/woocommerce/';

    // Look within passed path within the theme - this is priority
    $template = locate_template(
    array(
        $template_path . $template_name,
        $template_name
    )
    );
    if( ! $template && file_exists( $plugin_path . $template_name ) ){
        $template = $plugin_path . $template_name;
    }
    if ( ! $template ) {
        $template = $_template;
    }
    return $template;
}

add_filter( 'woocommerce_locate_template', 'wcpagofacilgateway\woo_adon_plugin_template', 1, 3 );

/**
 * uso de jS en pagina de checkout para entregar opcion de pago junto al metodo de pago
 */
function add_checkout_pagofacil_js() {
    wp_enqueue_script( 'add-checkout-pagofacil-js', plugin_dir_url( __FILE__ ) . 'js/frontend/checkout_pagofacil.js', array('jquery'),'', false);
}

add_action( 'wp_enqueue_scripts', 'wcpagofacilgateway\add_checkout_pagofacil_js' );