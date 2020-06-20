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

namespace wcpagofacilgateway\classes;

use PagoFacilCore\PagoFacilSdk;
use PagoFacilCore\Transaction;
use PagoFacilCore\EnvironmentEnum;

use wcpagofacilgateway\classes\Logger;
use WC_Order;



/**
 * Pagofacil payment gateway
 *
 * @author ctala
 */
class WC_Pagofacil_Gateway extends \WC_Payment_Gateway
{
    public $token_service;
    public $token_secret;
    public $environment;
    public $callback_url;
    public $complete_url;
    public $cancel_url;

    public function __construct()
    {
        $this->id = 'pagofacil';
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/../assets/images/logo.png';
        $this->has_fields = false;
        $this->method_title = 'Pago Fácil';
        //obtiene urls de callaback
        $callback_arg = 'callback_' . $this->id;
        $complete_arg = 'complete_' . $this->id;
        $cancel_arg = 'cancel_' . $this->id;
        $this->callback_url = WC()->api_request_url($callback_arg);
        $this->complete_url = WC()->api_request_url($complete_arg);
        $this->cancel_url = WC()->api_request_url($cancel_arg);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->environment = $this->get_option('ambiente');

        $this->token_service = $this->get_option('token_service');
        $this->token_secret = $this->get_option('token_secret');
        $this->chosen = false;

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        //Payment listener/API hook
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'pagofacil_thankyou_page'));
        add_action('woocommerce_api_' . $complete_arg, array($this, 'pagofacil_api_handler_complete'));
        add_action('woocommerce_api_' . $callback_arg, array($this, 'pagofacil_api_handler_callback'));
        add_action('woocommerce_api_' . $cancel_arg, array($this, 'pagofacil_api_handler_cancel'));

        //completa informacion de opciones de pago (pago facil)
        try {
            $this->paymentOptions = array();
            $pagoFacil = PagoFacilSdk::create()
                ->setTokenService($this->token_service)
                ->setEnvironment($this->environment);

            $paymentOptions = $pagoFacil->getPaymentMethods();
            if (property_exists((object) $paymentOptions, 'types')) {
                $paymentTypes = $paymentOptions['types'];
                foreach ($paymentTypes as &$paymentType) {
                    if (property_exists((object) $paymentType, 'nombre')) {
                        $this->paymentOptions[] = array(
                            'codigo' => $paymentType['codigo'],
                            'nombre' => $paymentType['nombre'],
                            'descripcion' => $paymentType['descripcion'],
                            'url_imagen' => $paymentType['url_imagen'],
                        );
                    }
                }
            }
            //luego de seleccionar opcion de pago en el checkout se obtiene metodo de pago y se guarda en la session.
            if (isset($_POST['payment_option'])) {
                $payment_option = wc_clean(wp_unslash($_POST['payment_option']));
                WC()->session->set('chosen_payment_option', $payment_option);
            }
        } catch (\Exception $e) {
            Logger::log_me_wp($e->getMessage());
        }
    }

    /**
     * Descripcion de pago usando dato de opcion de pago
     */
    public function payment_fields_opt($opt)
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($opt['descripcion']));
        }
    }

    /**
     * Icono de pago usando dato de opcion de pago
     */
    public function get_icon_opt($opt)
    {
        $icon = $opt['url_imagen'] ? '<img src="' . $opt['url_imagen'] . '" width="24" alt="' . esc_attr($opt['nombre']) . '" />' : '';
        return apply_filters('woocommerce_gateway_icon', $icon, $opt['codigo']);
    }

    /**
     * form_field metodo de pago
     */
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
                'default' => EnvironmentEnum::PRODUCTION,
                'options' => array(
                    EnvironmentEnum::PRODUCTION => 'Producción',
                    EnvironmentEnum::DEVELOPMENT => 'Desarrollo'
                )
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('', 'woocommerce'),
                'default' => __('Pago Fácil', 'woocommerce')
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
     * Genera los pagos
     */
    public function process_payment($order_id)
    {
        $sufijo = "[Pago Facíl - PROCESS - PAYMENT]";
        Logger::log_me_wp("Iniciando el proceso de pago para $order_id", $sufijo);

        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /*
     * Función Woocommerce Gateway para redireccionar a la página de pago.
     * Si la doble validación está activa se revisará en este punto también para no iniciar el proceso
     * de no ser necesario
     */

    public function receipt_page($order_id)
    {
        $order = new WC_Order($order_id);
        $sufijo = "[RECEIPT]";
        $payment_option = WC()->session->get('chosen_payment_option');
        if (is_null($payment_option)) {
            $errorMessage =   "Opcion de pago no identificada";
            Logger::log_me_wp($errorMessage);
            $order->update_status('cancelled', $errorMessage);
            $this->redirectCheckoutAfterError($errorMessage);
            return false;
        }
        $DOBLEVALIDACION = $this->get_option('doblevalidacion');
        if ($DOBLEVALIDACION === "yes") {
            Logger::log_me_wp("Doble Validación Activada / " . $order->status, $sufijo);
            if ($order->status === 'processing' || $order->status === 'completed') {
                Logger::log_me_wp("ORDEN YA PAGADA (" . $order->get_status() . ") EXISTENTE " . $order_id, "\t" . $sufijo);
                return false;
            }
        } else {
            Logger::log_me_wp("Doble Validación Desactivada / " . $order->get_status(), $sufijo);
        }
        if(round($order->get_total()) < 1000) {
            $errorMessage = "El monto total no debe ser menor a: $". wc_add_number_precision_deep(1000);
            Logger::log_me_wp($errorMessage);
            $order->update_status('cancelled', $errorMessage);
            $this->redirectCheckoutAfterError($errorMessage);
            return false;
        }
        try {
            $urlTrx = $this->generate_pagofacil_form($order_id, $payment_option);
        } catch (\Exception $e) {
            $errorMessage = "Error generar transaccion: ";
            Logger::log_me_wp($e->getMessage());
            $order->update_status('cancelled', $errorMessage);
            $this->redirectCheckoutAfterError($errorMessage);
            return false;
        }
        $redirect_template = "" .
            "<p>" . __('Gracias! - Tu orden ahora está pendiente de pago. Deberías ser redirigido automáticamente a Pago Fácil.') . "</p>" .
            "<input type=\"hidden\" id=\"url_trx\" name=\"url_trx\" value=\"" . $urlTrx . "\">" .
            "";
        echo $redirect_template;

        //limpia dato de session relacionado a opcion de pago (pago facil)
        WC()->session->set('chosen_payment_option', null);
    }

    /**
     * Genera transaccion luego de pedir orden
     * 
     * @param $order_id          Id de la orden
     * @param $payment_option    Opcion de pago (PagoFacil)
     */
    public function generate_pagofacil_form($order_id, $payment_option)
    {
        $order = new WC_Order($order_id);
        $shop_country = $order->get_billing_country();
        $reference = $order_id . "_" . time();

        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($this->token_secret)
            ->setTokenService($this->token_service)
            ->setEnvironment($this->environment);
        Logger::log_me_wp("env: ". print_r( $this->environment, TRUE), "INFO");
        $transaction = new Transaction();
        $transaction->setUrlCallback($this->callback_url);
        $transaction->setUrlCancel($this->cancel_url);
        $transaction->setUrlComplete($this->complete_url);
        $transaction->setCustomerEmail($order->get_billing_email());
        $transaction->setReference($reference);
        $transaction->setAmount(round($order->get_total()));
        $transaction->setCurrency(get_woocommerce_currency());
        $transaction->setShopCountry(!empty($shop_country) ? $shop_country : 'CL');
        $transaction->setSessionId(date('Ymdhis') . rand(0, 9) . rand(0, 9) . rand(0, 9));
        $transaction->setAccountId($this->token_service);
        Logger::log_me_wp("transaction: ". print_r( $transaction, TRUE), "INFO");
        Logger::log_me_wp("payment option: ". print_r( $payment_option, TRUE), "INFO");

        $data = $pagoFacil->initPayment($transaction, $payment_option);
        return $data['urlTrx'];
    }

    /*
     *
     * Proceso el post desde Pago Facil, endpoint complete
     */
    public function pagofacil_api_handler_complete()
    {
        Logger::log_me_wp("api handler - COMPLETE", "INFO");
        $httpHelper = $this->is_HTTP_POST();
        if (is_null($httpHelper)) {
            $this->procesoCompletado($_POST);
        }
    }

    /*
     * Proceso el post desde Pago Facil, endpoint callback
     */
    public function pagofacil_api_handler_callback()
    {
        Logger::log_me_wp("api handler - CALLBACK", "INFO");
        //Solo permitido metodo POST
        $httpHelper = $this->is_HTTP_POST();
        if (is_null($httpHelper)) {
            $this->procesarCallback($_POST);
        }
    }

    /*
     * Proceso el post desde Pago Facil, endpoint cancel
     */
    public function pagofacil_api_handler_cancel()
    {
        Logger::log_me_wp("api handler - CANCEL", "INFO");
        //Solo permitido metodo POST
        $httpHelper = $this->is_HTTP_POST();
        if (is_null($httpHelper)) {
            $this->procesarCancel($_POST);
        }
    }

    /**
     * 
     */
    public function pagofacil_thankyou_page($order_id)
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

    /**
     * Valida que metodo de la peticion sea correcto.
     * 
     * @return httpHelper con response code 405 si metodo es distinto a post.
     */
    private function is_HTTP_POST()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $helper = new HTTPHelper();
            $helper->my_http_response_code(405);
            return $helper;
        }
        return null;
    }

    /**
     * Metodo que procesa llamado a cancelar
     * 
     * @param $response HTTP response
     */
    private function procesarCancel($response)
    {
        Logger::log_me_wp("api handler - CANCEL", "INFO");
        $response = $_POST;
        $http_helper = new HTTPHelper();
        $reference = $response["x_reference"];
        $reference = explode('_', $reference);
        $order_id = $reference[0];
        //Verificamos que la orden exista
        $order = new WC_Order($order_id);
        if (!($order)) {
            $http_helper->my_http_response_code(404);
            return;
        }
        $error_message = "Orden $order_id cancelada";
        error_log($error_message);
        Logger::log_me_wp($error_message);
        $order->update_status('failed', $error_message);
        add_post_meta($order_id, '_reference', $response->reference, true);
        add_post_meta($order_id, '_gateway_reference', $response->gateway_reference, true);
        $http_helper->my_http_response_code(200);
    }

    /**
     * Metodo que procesa llamado a completar
     * 
     * @param $response HTTP response
     * @param $return si es true, asigna codigo metadata a la respuesta
     */
    private function procesoCompletado($response, $return = true)
    {
        Logger::log_me_wp("Iniciando el proceso completado");
        //obtiene orden
        $http_helper = new HTTPHelper();
        $order = $this->getOrder($response, $http_helper);
        if (is_null($order)) {
            return;
        }
        //valida signature
        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($this->token_secret)
            ->setEnvironment($this->environment);

        if (!$pagoFacil->validateSignature($response)) {
            Logger::log_me_wp("Firmas NO Corresponden");
            $order->update_status('failed', "El pago del pedido no fue exitoso.");
            if ($return) {
                $http_helper->my_http_response_code(400);
            }
            return;
        }
        Logger::log_me_wp("Firmas Corresponden");
        //Valida monto
        if (!$this->validateAmount($order, $response, $return, $http_helper)) {
            return;
        }

        $reference = $response["x_reference"];
        $reference = explode('_', $reference);
        $order_id = $reference[0];
        $order_id_mall = $response["x_gateway_reference"];
        $order_estado = $response["x_result"];

        Logger::log_me_wp("ORDER _id = $order_id");
        Logger::log_me_wp("ORDER _estado = $order_estado");
        Logger::log_me_wp($response);

        //Revisamos si ya está completada, si lo está no hacemos nada.
        if ($order->get_status() != "completed") {
            $this->procesarCallback($response, false);
        }

        //Si no aparece completada y el resultado es COMPLETADA cambiamos el estado y agregamos datos.

        //Redireccionamos.
        $order_received = $order->get_checkout_order_received_url();
        wp_redirect($order_received);
        exit;
    }

    /**
     * Metodo que procesa llamado a callback
     * 
     * @param $response HTTP response
     * @param $return si es true, asigna codigo metadata a la respuesta
     */
    private function procesarCallback($response, $return = true)
    {
        Logger::log_me_wp("Iniciando el proceso Callback");
        //obtiene orden
        $http_helper = new HTTPHelper();
        $order = $this->getOrder($response, $http_helper);
        if (is_null($order)) {
            return;
        }
        //valida signature
        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($this->token_secret)
            ->setEnvironment($this->environment);

        if (!$pagoFacil->validateSignature($response)) {
            Logger::log_me_wp("Firmas NO Corresponden");
            $order->update_status('failed', "El pago del pedido no fue exitoso.");
            if ($return) {
                $http_helper->my_http_response_code(400);
            }
            return;
        }
        Logger::log_me_wp("Firmas Corresponden");
        //Valida monto
        if (!$this->validateAmount($order, $response, $return, $http_helper)) {
            return;
        }
        //Si la orden está completada no hago nada.
        if ($order->get_status() === 'completed') {
            if ($return) {
                $http_helper->my_http_response_code(400);
            }
            return;
        }
        //revisa estado.
        $ct_estado = $response["x_result"];
        $reference = $response["x_reference"];
        $reference = explode('_', $reference);
        $order_id = $reference[0];
        Logger::log_me_wp("ESTADO DE LA ORDEN : $ct_estado");
        error_log("Estado de compra $ct_estado");
        Logger::log_me_wp("Estado de compra $ct_estado");
        if ($ct_estado == "completed") {
            //Marcar Completa
            $order->payment_complete();
            //Agregar Meta
            $this->addMetaFromResponse($response, $order_id);
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
    }

    /**
     * Obtiene orden segun referencia de la respuesta
     * 
     * @param $response Respuesta del comercio
     * @param $http_header se asigna valor 404 si order no existe
     * @return retorna orden o null si no la existe.
     */
    private function getOrder($response, $http_helper = null)
    {
        try {
            $reference = $response["x_reference"];
            $reference = explode('_', $reference);
            $order_id = $reference[0];
            //Verificamos que la orden exista
            $order = new WC_Order($order_id);
            if (!($order)) {
                if (!is_null($http_helper)) {
                    $http_helper->my_http_response_code(404);
                }
                return null;
            }
            return $order;
        } catch (\Exception $e) {
            if (!is_null($http_helper)) {
                $http_helper->my_http_response_code(404);
            }
            error_log("error al obtener orden: " . $e->getMessage());
        }
        return null;
    }

    /**
     * revisa que monto de la respuesta sea igual a monto de la orden
     */
    private function validateAmount($order, $response, $return, $http_helper)
    {
        $reference = $response["x_reference"];
        $reference = explode('_', $reference);
        $order_id = $reference[0];
        $orderAmount = round($order->get_total());
        $responseAmount = $response['x_amount'];
        if ($responseAmount != $orderAmount) {
            Logger::log_me_wp("Montos NO Corresponden");
            Logger::log_me_wp("Monto $responseAmount recibido es distinto a monto orden $orderAmount");
            $order->update_status('failed', "El pago del pedido no fue exitoso debido a montos distintos");
            add_post_meta($order_id, '_reference', $response->reference, true);
            add_post_meta($order_id, '_gateway_reference', $response->gateway_reference, true);
            if ($return) {
                $http_helper->my_http_response_code(200);
            }
            return false;
        }
        Logger::log_me_wp("Montos SI Corresponden");
        return true;
    }


    /**
     * Guarda como metadata datos del response proveniente del servicio de pago.
     */
    private function addMetaFromResponse($data, $order_id)
    {
        add_post_meta($order_id, '_amount', $data['x_amount'], true);
        add_post_meta($order_id, '_currency', $data['x_currency'], true);
        add_post_meta($order_id, '_gateway_reference', $data['x_gateway_reference'], true);
        add_post_meta($order_id, '_reference', $data['x_reference'], true);
        add_post_meta($order_id, '_result', $data['x_result'], true);
        add_post_meta($order_id, '_test', $data['x_test'], true);
        add_post_meta($order_id, '_timestamp', $data['x_signature'], true);
    }

    /**
     * Redirecciona a la pagina de checkout si hay algun error (muestra error en la vista)
     */
    private function redirectCheckoutAfterError($error_message)
    {
        wc_add_notice($error_message, 'error');
        wp_redirect(wc_get_checkout_url());
    }
}
