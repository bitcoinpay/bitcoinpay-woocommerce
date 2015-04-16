<?php
/*
Plugin Name: WooCommerce BitcoinPay Payment Gateway
Plugin URI: http://www.bitcoinpay.com
Description: BitcoinPay Payment gateway for woocommerce
Version: 0.1
Author: Digito.cz
Author URI: http://www.digito.cz
Copyright (C) Digito.cz, Digito Proprietary License
*/
add_action('plugins_loaded', 'woocommerce_bcp_payment_init', 0);
function woocommerce_bcp_payment_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_bcp_payment extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id            = 'bitcoinpay';
            $this->icon_path     = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/img/01_32p.png';
            $this->medthod_title = 'Bitcoin';
            $this->has_fields    = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->icon_enable = $this->settings['icon'];
            $this->apikey   = $this->settings['apikey'];
            $this->callback = $this->settings['callback'];
            $this->email    = $this->settings['email'];
            $this->payout   = $this->settings['payout'];

            $this->liveurl = 'https://bitcoinpaycom.apiary-mock.com/api/v1/payment/btc';

            $this->msg['message'] = "";
            $this->msg['class']   = "";

            //add_action('init', array(&$this, 'check_payu_response'));
            add_action('woocommerce_thankyou', function()
            {

                $returnStatus = $_GET["bitcoinpay-status"];
                $doit         = true;

                if (strcmp($returnStatus, "true") == 0) {
                    $doit = false;
                } elseif (strcmp($returnStatus, "received") == 0) {
                    $bcp_thanks_title = "Your Order Has Not Been Processed Yet!";
                    $bcp_thanks_msg   = "Your order has not been successfully processed yet! We received your payment, but we are waiting for confirmation. You will be notified by email.";
                } elseif (strcmp($returnStatus, "cancel") == 0) {
                    $bcp_thanks_title = "Your Order Has Been Canceled!";
                    $bcp_thanks_msg   = "Your order has been canceled at Bitcoinpay payment gate! You may place new one.";
                } else {
                    $bcp_thanks_title = "Your Order Has Not Been Processed!";
                    $bcp_thanks_msg   = "Your order has not been successfully processed!";
                }




                if ($doit) {
                    echo "<script>
            var eltitle = document.querySelector(\".entry-header .entry-title\");
            eltitle.innerHTML = \"$bcp_thanks_title\";
            var eltext = document.querySelector(\".entry-content .woocommerce p:first-child\");
            eltext.innerHTML = \"$bcp_thanks_msg\";
            </script>";
                }
            });
            add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                &$this,
                'handle_callback'
            ));


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    &$this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(
                    &$this,
                    'process_admin_options'
                ));
            }

            if (strlen($this->apikey) == 0 || strlen($this->payout) == 0) {
                static $count = 0;
                $count++;

                $this->enabled             = 'no';
                $this->settings['enabled'] = 'no';

                if ($count > 1)
                    $this->errors[] = "Payment gateway has been disabled!";
            }

        }
        //custom link and icons
        public function get_icon()
        {
            if(strcmp($this->icon_enable,'yes') == 0)
                $icon_html = "<img src=\"{$this->icon_path}\" alt=\"BitcoinPay\">";
            else
                $icon_html = '';
            //$icon      = (array) $this->get_icon_image(WC()->countries->get_base_country());

            /*foreach ($icon as $i) {
            $icon_html .= '<img src="' . esc_attr($i) . '" alt="' . __('PayPal Acceptance Mark', 'woocommerce') . '" />';
            }*/

            $icon_html .= '<a heref="#" onclick="window.open(\'https://bitcoinpay.com\')" style="float: right;line-height: 52px;font-size: .83em;" title="What is Bitcoinpay" target="_blank">What is Bitcoinpay?</a>';

            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }

        //validation functions
        function validate_apikey_field($key)
        {
            static $count = 0;
            $count++;

            // get the posted value
            $value = $_POST[$this->plugin_id . $this->id . '_' . $key];
            /*if($count > 1)
            return "* please insert valid API key"; */
            if (isset($value) && 24 != strlen($value)) {
                if ($count > 1)
                    return "";


                else
                    $this->errors[] = "Your API key is not VALID!";



            }
            return $value;
        }
        function validate_payout_field($key)
        {
            static $count = 0;
            $count++;

            // get the posted value
            $value = $_POST[$this->plugin_id . $this->id . '_' . $key];

            if (isset($value) && (strlen($value) != 3)) {
                if ($count > 1)
                    return "";

                else
                    $this->errors[] = "Your Payout currency is not VALID! Use 3 letter currency code.";
            } elseif (isset($value) && strlen($valid_curr = $this->check_currency($value)) != 0) {
                if ($count > 1)
                    return "";

                else {
                    strlen($valid_curr) == 1 ? $curr_list = "You must select your payout currency in BitcoinPay.com administration first" : $curr_list = $valid_curr;


                    $this->errors[] = "Your Payout currency is not VALID! Select form: {$valid_curr}";
                }



            }
            return $value;
        }

        public function check_currency($user_curr)
        {
            static $count;
            $count++;

            $isValid        = false;
            $settlement_url = 'https://www.bitcoinpay.com/api/v1/settlement/';
            $apiID          = $this->apikey;

            $curlheaders = array(
                "Content-type: application/json",
                "Authorization: Token {$apiID}"
            );

            $curl = curl_init($settlement_url);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curlheaders);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //bypassing ssl verification, because of bad compatibility

            $response = curl_exec($curl);

            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $jHeader     = substr($response, 0, $header_size);
            $jBody       = substr($response, $header_size);

            //http response code
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status != 200) {


                if ($status == 401) {
                    if ($count > 1)
                        $this->errors[] = "API key is not VALID! Cannot connect to gate to check payout currency!";
                } else {
                    if ($count > 1)
                        $this->errors[] = "API key is not VALID! Cannot connect to gate to check payout currency!";
                }
                curl_close($curl);
                return "";


            }


            $answer            = json_decode($jBody);
            $active_currencies = $answer->data->active_settlement_currencies;

            if (count($active_currencies) == 0) {
                curl_close($curl);
                return "1";
            }

            foreach ($active_currencies as $value) {
                if (strcmp($value, $user_curr) == 0) {
                    $isValid = true;
                    break;
                }
            }
            if (!$isValid) {
                $valid_currencies = '';
                foreach ($active_currencies as $value) {
                    $valid_currencies .= '<br/ >' . $value;
                }

                return $valid_currencies;
            }


            curl_close($curl);
        }

        public function display_errors()
        {
            // loop through each error and display it
            foreach ($this->errors as $key => $value) {
?>
        		<div class="error">
        			<p><?php
                _e($value, 'bcp-error');
?></p>
        		</div>

        		<?php

            }
            unset($this->errors);
        }
        //callback function
        function handle_callback()
        {
            //callback
            //error_log("Zavolan callback...");

            $inputData   = file_get_contents('php://input');
            $payResponse = json_decode($inputData);

            //callback password
            if (($callbackPass = $this->callback) != NULL) {
                $paymentHeaders = getallheaders();
                $digest         = $paymentHeaders["Bpsignature"];

                $hashMsg     = $inputData . $callbackPass;
                $checkDigest = hash('sha256', $hashMsg);

                if (strcmp($digest, $checkDigest) == 0) {
                    $security = 1;
                } else {
                    $security = 0;
                }
            } else {
                $security = 1;
            }

            //payment status
            $paymentStatus = $payResponse->status;

            //order id
            $preOrderId = json_decode($payResponse->reference);
            $orderId    = $preOrderId->order_number;

            //confirmation process
            $order = new WC_Order($orderId);

            if ($security) {


                if ($paymentStatus != NULL) {

                    error_log($paymentStatus);
                    switch ($paymentStatus) {
                        case 'confirmed':
                            $order->update_status('processing', __('BCP Payment processing', 'bcp'));
                            break;
                        case 'pending':
                            $order->update_status('pending', __('BCP Payment pending', 'bcp'));
                            break;
                        case 'received':
                            $order->update_status('pending', __('BCP Payment received but still pending', 'bcp'));
                            break;
                        case 'insufficient_amount':
                            $order->update_status('failed', __('BCP Payment failed. Insufficient amount', 'bcp'));
                            break;
                        case 'invalid':
                            $order->update_status('cancelled', __('BCP Payment failed. Invalid', 'bcp'));
                            break;
                        case 'timeout':
                            $order->update_status('cancelled', __('BCP Payment failed. Timeout', 'bcp'));
                            break;
                        case 'refund':
                            $order->update_status('refunded', __('BCP Payment refunded', 'bcp'));
                            break;
                        case 'paid_after_timeout':
                            $order->update_status('failed', __('BCP Payment failed. Paid after timeout', 'bcp'));
                            break;

                    }

                }
            }


        }

        function add_content()
        {
            echo '<h2 id="h2thanks">Get 20% off</h2><p id="pthanks">Thank you for making this purchase! Come back and use the code "<strong>Back4More</strong>" to receive a 20% discount on your next purchase! Click here to continue shopping.</p>';
        }


        function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'bcp'),
                    'type' => 'checkbox',
                    'label' => __('Enable BitcoinPay Payment Module.', 'bcp'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title:', 'bcp'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'bcp'),
                    'default' => __('BitcoinPay', 'bcp')
                ),
                'description' => array(
                    'title' => __('Description:', 'bcp'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'bcp'),
                    'default' => __('Pay securely with Bitcoins through BitcoinPay Secure Servers.', 'bcp')
                ),
                'icon' => array(
                    'title' => __('Frontend icon', 'bcp'),
                    'type' => 'checkbox',
                    'label' => __('Display Bitcoin icon in frontend.', 'bcp'),
                    'default' => 'no'
                ),
                'apikey' => array(
                    'title' => __('* API key:', 'bcp'),
                    'type' => 'text',
                    'description' => __('API key is used for backed authentication and you should keep it private. You will find your API key in your account under settings > API', 'bcp'),
                    'desc_tip' => true
                ),
                'callback' => array(
                    'title' => __('Callback password:', 'bcp'),
                    'type' => 'text',
                    'description' => __('We recommend using a callback password. It is used as a data validation for stronger security. Callback password can be set under Settings > API in your account at BitcoinPay.com', 'bcp'),
                    'desc_tip' => true
                ),
                'email' => array(
                    'title' => __('E-Mail:', 'bcp'),
                    'type' => 'text',
                    'description' => __('Email where notifications about Payment changes are sent.', 'bcp'),
                    'desc_tip' => true
                ),
                'payout' => array(
                    'title' => __('* Payout currency:', 'bcp'),
                    'type' => 'text',
                    'description' => __('Currency of settlement. You must first set a payout for currency in your account Settings > Payout in your account at BitcoinPay.com. If the currency is not set in payout, the request will return an error.', 'bcp'),
                    'desc_tip' => true
                )
            );
        }

        public function admin_options()
        {
            echo '<h3>' . __('BitcoinPay Payment Gateway', 'bcp') . '</h3>';
            echo '<p>' . __('BitcoinPay is secure payment gateway for Bitcoin transactions') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';

        }

        /**
         *  There are no payment fields for payu, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            // gate logic start
            //Getting API-ID from config
            $apiID = $this->settings['apikey'];

            //test mode check
            $testMode = 0; //if set to 1, test mode will be set
            if (!$testMode) {
                $payurl = 'https://www.bitcoinpay.com/api/v1/payment/btc';
            } else {
                $payurl = 'https://bitcoinpaycom.apiary-mock.com/api/v1/payment/btc';
            }

            //data preparation
            $bcp_order_id = $order_id;
            $bcp_price    = $order->get_total();
            $bcp_fname    = $order->billing_first_name;
            $bcp_lname    = $order->billing_last_name;
            $bcp_name     = "{$bcp_fname} {$bcp_lname}";
            $bcp_email    = $order->billing_email;
            $bcp_currency = get_woocommerce_currency();

            //data finalize
            $customData  = array(
                'customer_name' => $bcp_name,
                'order_number' => intval($bcp_order_id),
                'customer_email' => $bcp_email
            );
            $jCustomData = json_encode($customData);

            $notiEmail = $this->settings['email'];
            $lang      = "";
            $settCurr  = $this->settings['payout'];

            if (strlen($settCurr) != 3) {
                $settCurr = "BTC";
            }

            $bcp_callback_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'wc_bcp_payment', home_url('/')));
            $bcp_return_url   = $this->get_return_url($order);

            $postData = array(
                'settled_currency' => $settCurr,
                'return_url' => $bcp_return_url,
                'notify_url' => $bcp_callback_url,
                'price' => floatval($bcp_price),
                'currency' => $bcp_currency,
                'reference' => json_decode($jCustomData)
            );

            if (($notiEmail !== NULL) && (strlen($notiEmail) > 5)) {
                $postData['notify_email'] = $notiEmail;
            }
            if ((strcmp($lang, "cs") !== 0) || (strcmp($lang, "en") !== 0) || (strcmp($lang, "de") !== 0)) {
                $postData['lang'] = "en";
            } else {
                $postData['lang'] = $lang;
            }

            $content = json_encode($postData);

            //sending data via cURL
            $curlheaders = array(
                "Content-type: application/json",
                "Authorization: Token {$apiID}"
            );
            $curl        = curl_init($payurl);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curlheaders);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //bypassing ssl verification, because of bad compatibility
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);


            //sending to server, and waiting for response
            $response = curl_exec($curl);

            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $jHeader     = substr($response, 0, $header_size);
            $jBody       = substr($response, $header_size);

            $jHeaderArr = $this->get_headers_from_curl_response($jHeader);

            //http response code
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            //callback password check
            if (($callbackPass = $this->settings['callback']) != NULL) {
                $digest = $jHeaderArr[0]["BPSignature"];


                $hashMsg     = $jBody . $callbackPass;
                $checkDigest = hash('sha256', $hashMsg);

                if (strcmp($digest, $checkDigest) == 0) {
                    $security = 1;
                } else {
                    $security = 0;
                }
            } else {
                $security = 1;
            }

            if ($status != 200) {
                die("Error: call to URL {$payurl} failed with status {$status}, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl) . "<br /> Please contact shop administrator...");
                curl_close($curl);
            } elseif (!$security) {
                die("Error: Callback password does not match! <br />Please contact shop administrator...");
                curl_close($curl);
            } else {
                curl_close($curl);

                $response         = json_decode($jBody);
                //adding paymentID to payment method
                $BCPPaymentId     = $response->data->payment_id;
                $bcp_pre_inv      = "https://bitcoinpay.com/en/sci/invoice/btc/" . $BCPPaymentId;
                $BCPInvoiceUrl    = "<br><strong>BitcoinPay Invoice: </strong><a href=\"" . $bcp_pre_inv . "\" target=\"_blnak\">" . $bcp_pre_inv . "</a>";
                //$prePaymentMethod = html_entity_decode($order_info['payment_method'], ENT_QUOTES, 'UTF-8');
                $finPaymentMethod = "<strong>PaymentID: </strong>" . $BCPPaymentId . $BCPInvoiceUrl;

                //redirect to pay gate
                $paymentUrl = $response->data->payment_url;
                $order->add_order_note(__($finPaymentMethod, 'bcp'));


                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('pending', __('BCP Payment pending', 'bcp'));
                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                $woocommerce->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $paymentUrl
                );
            }
        }

        private function get_headers_from_curl_response($headerContent)
        {
            $headers = array();

            // Split the string on every "double" new line.
            $arrRequests = explode("\r\n\r\n", $headerContent);

            // Loop of response headers. The "count() -1" is to
            //avoid an empty row for the extra line break before the body of the response.
            for ($index = 0; $index < count($arrRequests) - 1; $index++) {

                foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
                    if ($i === 0)
                        $headers[$index]['http_code'] = $line;
                    else {
                        list($key, $value) = explode(': ', $line);
                        $headers[$index][$key] = $value;
                    }
                }
            }

            return $headers;
        }
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }
        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages  = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page  = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }
    class bcp_handle_callback
    {
        public function __construct()
        {
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_bcp_payment_gateway($methods)
    {
        $methods[] = 'WC_bcp_payment';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_bcp_payment_gateway');
}