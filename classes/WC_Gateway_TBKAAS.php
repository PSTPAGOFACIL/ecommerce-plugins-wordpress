<?php

/*
 * The MIT License
 *
 * Copyright 2016 ctala.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace tbkaaswoogateway\classes;

use tbkaaswoogateway\classes\Logger;
use WC_Order;

use PagoFacil\lib\Request;
use PagoFacil\lib\Response;
use PagoFacil\lib\Transaction;

/**
 * Description of WC_Gateway_TBKAAS
 *
 * @author ctala
 */
class WC_Gateway_TBKAAS extends \WC_Payment_Gateway
{
    public $notify_url;
    public $tbkaas_base_url;
    public $token_service;
    public $token_secret;
    public $environment;

    public function __construct()
    {
        $this->id = 'tbkaas';
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/../assets/images/logo.png';
        $this->has_fields = false;
        $this->method_title = 'Pago Fácil';
        $this->notify_url = WC()->api_request_url('WC_Gateway_TBKAAS');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        //$modo_desarrollo = $this->get_option('desarrollo');
        //$ambiente = $this->get_option('ambiente');
        $this->environment = $this->get_option('ambiente');

        $this->token_service = $this->get_option('token_service');
        $this->token_secret = $this->get_option('token_secret');

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        //Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'tbkaas_api_handler'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'tbkaas_thankyou_page'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Habilita PagoFácil', 'woocommerce'),
                'default' => 'yes'
            ),
            'ambiente' => array(
                'title' => __('Ambiente', 'woocommerce'),
                'type' => 'select',
                'label' => __('Habilita el modo de pruebas', 'woocommerce'),
                'default' => 'PRODUCCION',
                'options' => array(
                  'PRODUCCION' => 'Producción',
                  'DESARROLLO' => 'Desarrollo',
                  'BETA' => 'Beta'
                )
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('', 'woocommerce'),
                'default' => __('Pago Fácil ( Transbank + Khipu + MultiCaja + Pago46)', 'woocommerce')
            ),
            'description' => array(
                'title' => __('Customer Message', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Mensaje que recibirán los clientes al seleccionar el medio de pago'),
                'default' => __('Sistema de pago con tarjetas de crédito y débito chilenas.'),
            ),
            'token_service' => array(
                'title' => "Token Servicio",
                'type' => 'text',
                'description' => "El token asignado al servicio creado en PagoFacil.cl.",
                'default' => "",
            ),
            'token_secret' => array(
                'title' => "Token Secret",
                'type' => 'text',
                'description' => "Con esto codificaremos la información a enviar.",
                'default' => "",
            ),
            'redirect' => array(
                'title' => __('Redirección Automática'),
                'type' => 'checkbox',
                'label' => __('Si / No'),
                'default' => 'yes'
            )
        );
    }

    /*
     * Esta función es necesaria para poder generar el pago.
     */

    public function process_payment($order_id)
    {
        $sufijo = "[TBKAAS - PROCESS - PAYMENT]";
        Logger::log_me_wp("Iniciando el proceso de pago para $order_id", $sufijo);

        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /*
     * Esta función es necesaria por parte de la herencia del Gateway para redireccionar a la página de pago.
     * Si la doble validación está activa se revisará en este punto también para no iniciar el proceso
     * de no ser necesario
     */

    public function receipt_page($order_id)
    {
        $sufijo = "[RECEIPT]";
        $DOBLEVALIDACION = $this->get_option('doblevalidacion');
        $order = new WC_Order($order_id);
        if ($DOBLEVALIDACION === "yes") {
            log_me("Doble Validación Activada / " . $order->status, $sufijo);
            if ($order->status === 'processing' || $order->status === 'completed') {
                Logger::log_me_wp("ORDEN YA PAGADA (" . $order->get_status() . ") EXISTENTE " . $order_id, "\t" . $sufijo);
                // Por solicitud muestro página de fracaso.
//                $this->paginaError($order_id);
                return false;
            }
        } else {
            Logger::log_me_wp("Doble Validación Desactivada / " . $order->get_status(), $sufijo);
        }

        echo '<p>' . __('Gracias! - Tu orden ahora está pendiente de pago. Deberías ser redirigido automáticamente a Pago Fácil.') . '</p>';
        echo $this->generate_TBKAAS_form($order_id);
    }

    public function generate_TBKAAS_form($order_id)
    {
        $SUFIJO = "[WEBPAY - FORM]";

        $order = new WC_Order($order_id);

        /*
         * Este es el token que representará la transaccion.
         */
        $token_tienda = (bin2hex(random_bytes(30)));

        /*
         * Agregamos el id de sesion la OC.
         * Esto permitira que validemos el pago mas tarde
         * Este valor no cambiara para la OC si est que ya está Creado
         *
         */
        $token_tienda_db = get_post_meta($order_id, "_token_tienda", true);
        Logger::log_me_wp($token_tienda_db);
        if (is_null($token_tienda_db) || $token_tienda_db == "") {
            Logger::log_me_wp("No existe TOKEN, lo agrego");
            add_post_meta($order_id, '_token_tienda', $token_tienda, true);
        } else {
            Logger::log_me_wp("Existe session");
            $token_tienda = $token_tienda_db;
        }

        $monto = round($order->get_total());
        $email = $order->get_billing_email();
        $shop_country = $order->get_billing_country();

        $request = new Request();

        $request->account_id = $this->token_service;
        $request->amount = round($monto);
        $request->currency = get_woocommerce_currency();
        $request->reference = $order_id;
        $request->customer_email =  $email;
        $request->url_complete = $this->notify_url.'?complete';
        $request->url_cancel = $this->notify_url;
        $request->url_callback =  $this->notify_url.'?callback';
        $request->shop_country = !empty($shop_country) ? $shop_country : 'CL';
        $request->session_id = date('Ymdhis').rand(0, 9).rand(0, 9).rand(0, 9);

        $transaction = new Transaction($request);
        $transaction->environment =  $this->environment;
        $transaction->setToken($this->token_secret);
        $transaction->initTransaction($request);
    }

    /*
     * Proceso el post desde TBKAAS
     * Obtenemos order_id
     * Obtenemos el session_id
     */

    public function tbkaas_api_handler()
    {

        /*
         * Si llegamos por post verificamos, si no redireccionamos a error.
         */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            /*
             * Revisamos si es callback
             */


            $esCallback = !empty($_GET["callback"]);
            if ($esCallback) {
                $this->procesarCallback($_POST);
            } else {
                $this->procesoCompletado($_POST);
            }
        } else {
            $helper = new HTTPHelper();
            $helper->my_http_response_code(405);
        }
    }

    public function tbkaas_thankyou_page($order_id)
    {
        Logger::log_me_wp("Entrando a Pedido Recibido de $order_id");
        $order = new WC_Order($order_id);

        if ($order->get_status() === 'processing' || $order->get_status() === 'completed') {
            include(plugin_dir_path(__FILE__) . '../templates/order_recibida.php');
        } else {
            $order_id_mall = get_post_meta($order_id, "_reference", true);
            include(plugin_dir_path(__FILE__) . '../templates/orden_fallida.php');
        }
    }

    private function procesoCompletado($response)
    {
        Logger::log_me_wp("Iniciando el proceso completado ");

        $order_id = $response["x_reference"];
        $order_id_mall = $response["x_gateway_reference"];
        $order_estado = $response["x_result"];

        Logger::log_me_wp("ORDER _id = $order_id");
        Logger::log_me_wp("ORDER _estado = $order_estado");

        Logger::log_me_wp($response);

        //Verificamos que la orden exista
        $order = new WC_Order($order_id);
        if (!($order)) {
            return;
        }

        //Revisamos si ya está completada, si lo está no hacemos nada.

        if ($order->get_status() != "completed") {
            $this->procesarCallback($response, false);
        }

        //Si no aparece completada y el resultado es COMPLETADA cambiamos el estado y agregamos datos.

        /*
         * Redireccionamos.
         */
        $order_received = $order->get_checkout_order_received_url();
        wp_redirect($order_received);
        exit;
    }

    private function procesarCallback($response, $return = true)
    {
        Logger::log_me_wp("Inicia Callback");

        $http_helper = new HTTPHelper();
        $order_id = $response["x_reference"];
        //Verificamos que la orden exista
        $order = new WC_Order($order_id);
        if (!($order)) {
            if ($return) {
                $http_helper->my_http_response_code(404);
            }
            return;
        }

        //Si la orden está completada no hago nada.
        if ($order->get_status() === 'completed') {
            if ($return) {
                $http_helper->my_http_response_code(400);
            }
            return;
        }

        $ct_firma = $response["x_signature"];
        $ct_estado = $response["x_result"];

        $transaction = new Transaction();
        $transaction->setToken($this->token_secret);

        if ($transaction->validate($response)) {
            Logger::log_me_wp("Firmas Corresponden");
            $ct_monto = $response['x_amount'];
            $monto = round($order->get_total());

            if ($ct_monto != $monto) {
                Logger::log_me_wp("Montos NO Corresponden");
                Logger::log_me_wp("Monto $ct_monto recibido es distinto a monto orden $monto");
                $order->update_status('failed', "El pago del pedido no fue exitoso debido a montos distintos");
                add_post_meta($order_id, '_reference', $response->reference, true);
                add_post_meta($order_id, '_gateway_reference', $response->gateway_reference, true);
                if ($return) {
                    $http_helper->my_http_response_code(200);
                }
            } else {
                Logger::log_me_wp("Montos SI Corresponden");
            }

            $ct_estado = $response['x_result'];
            Logger::log_me_wp("ESTADO DE LA ORDEN : $ct_estado");

            error_log("Estado de compra $ct_estado");
            Logger::log_me_wp("Estado de compra $ct_estado");
            if ($ct_estado == "completed") {
                //Marcar Completa

                 $order->payment_complete();
                //$order->update_status('completed');

                $response_data = $this->generateResponse($response);
                //Agregar Meta
                $this->addMetaFromResponse($response_data, $order_id);
                Logger::log_me_wp("Orden $order_id marcada completa");
                error_log("Orden $order_id marcada completa");
                if ($return) {
                    $http_helper->my_http_response_code(200);
                }
            } else {
                error_log("Orden $order_id no completa");
                Logger::log_me_wp("Orden no completa");
                $order->update_status('failed', "El pago del pedido no fue exitoso.");
                add_post_meta($order_id, '_reference', $response->reference, true);
                add_post_meta($order_id, '_gateway_reference', $response->gateway_reference, true);
                if ($return) {
                    $http_helper->my_http_response_code(200);
                }
            }
        } else {
            Logger::log_me_wp("Firmas NO Corresponden");
            $order->update_status('failed', "El pago del pedido no fue exitoso.");
            if ($return) {
                $http_helper->my_http_response_code(400);
            }
        }
    }

    private function addMetaFromResponse(Response $response, $order_id)
    {
        add_post_meta($order_id, '_amount', $response->amount, true);
        add_post_meta($order_id, '_currency', $response->currency, true);
        add_post_meta($order_id, '_gateway_reference', $response->gateway_reference, true);
        add_post_meta($order_id, '_reference', $response->reference, true);
        add_post_meta($order_id, '_result', $response->result, true);
        add_post_meta($order_id, '_test', $response->test, true);
        add_post_meta($order_id, '_timestamp', $response->timestamp, true);
    }

    private function generateResponse($data)
    {
        $response = new Response();
        $response->account_id = $data['x_account_id'];
        $response->amount = $data['x_amount'];
        $response->currency = $data['x_currency'];
        $response->gateway_reference = $data['x_gateway_reference'];
        $response->reference = $data['x_reference'];
        $response->result = $data['x_result'];
        $response->test = $data['x_test'];
        $response->timestamp = $data['x_timestamp'];
        $response->signature = $data['x_signature'];

        return $response;
    }
}
