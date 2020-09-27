<?php
/**
 * Plugin Name: Hamrahpay Paid Memberships Pro
 * Description: درگاه پرداخت همراه پی برای افزونه Paid Memberships Pro
 * Author: Hamrahpay
 * Version: 1.0.0
 * License: GPL v2.0.
 */
//load classes init method
add_action('plugins_loaded', 'load_hamrahpay_pmpro_class', 11);
add_action('plugins_loaded', ['PMProGateway_Hamrahpay', 'init'], 12);


add_filter('pmpro_currencies', 'pmpro_add_iranian_currencies');
function pmpro_add_iranian_currencies($currencies) {
	$currencies['IRT'] =  array(
		'name' =>'تومان',
		'symbol' => ' تومان ',
		'position' => 'left'
	);
	$currencies['IRR'] = array(
		'name' => 'ریال',
		'symbol' => ' ریال ',
		'position' => 'left'
	);
	return $currencies;
}


function load_hamrahpay_pmpro_class()
{
    if (class_exists('PMProGateway')) {
        class PMProGateway_Hamrahpay extends PMProGateway
        {
			private static $api_version    = "v1";
			private static $api_url        = 'https://api.hamrahpay.com/api/v1';
			private static $second_api_url = 'https://api.hamrahpay.ir/api/v1';
			
            public function PMProGateway_Hamrahpay($gateway = null)
            {
                $this->gateway = $gateway;
                $this->gateway_environment = pmpro_getOption('gateway_environment');
                return $this->gateway;
            }

            public static function init()
            {
                //make sure Stripe is a gateway option
                add_filter('pmpro_gateways', ['PMProGateway_Hamrahpay', 'pmpro_gateways']);

                //add fields to payment settings
                add_filter('pmpro_payment_options', ['PMProGateway_Hamrahpay', 'pmpro_payment_options']);
                add_filter('pmpro_payment_option_fields', ['PMProGateway_Hamrahpay', 'pmpro_payment_option_fields'], 10, 2);
                $gateway = pmpro_getOption('gateway');

                if ($gateway == 'hamrahpay') {
                    add_filter('pmpro_checkout_before_change_membership_level', ['PMProGateway_Hamrahpay', 'start_payment'], 10, 2);
                    add_filter('pmpro_include_billing_address_fields', '__return_false');
                    add_filter('pmpro_include_payment_information_fields', '__return_false');
                    add_filter('pmpro_required_billing_fields', ['PMProGateway_Hamrahpay', 'pmpro_required_billing_fields']);
                }

                add_action('wp_ajax_nopriv_hamrahpay-ins', ['PMProGateway_Hamrahpay', 'callback_from_hamrahpay']);
                add_action('wp_ajax_hamrahpay-ins', ['PMProGateway_Hamrahpay', 'callback_from_hamrahpay']);
            }
			
			// This method sends the data to api
			private static function post_data($url,$params)
			{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-Type: application/json',
				]);
				$result = curl_exec($ch);
				//echo curl_error($ch);
				curl_close($ch);

				return $result;
			}

			// This method returns the api url
			private static function getApiUrl($end_point,$use_emergency_url=false)
			{
				if (!$use_emergency_url)
					return self::$api_url.$end_point;
				else
				{
					return self::$second_api_url.$end_point;
				}
			}
			
			public static function error_reason( $error_id ) {
				$message = 'خطای ناشناخته';

				switch ( $error_id ) {
					case '-100':
					$message = 'خطای ناشناخته ای رخ داده است.';
						break;
					case '-1':
						$message = 'اطلاعات ارسال شده ناقص است';
						break;
					case '-2':
						$message = 'IP و يا API Key کسب و کار صحيح نيست.';
						break;
					case '-3':
						$message = 'با توجه به محدوديت هاي شاپرك امكان پرداخت با رقم درخواست شده ميسر نمي باشد';
						break;
					case '-4':
						$message = 'کسب و کار پذیرنده فعال نیست.';
						break;
					case '-5':
						$message = 'درخواست مورد نظر يافت نشد.';
						break;
					case '-6':
						$message = 'پرداخت موفقیت آمیز نبوده است.';
						break;
					case '-7':
						$message = 'فرمت خروجی لینک پرداخت صحیح نیست.';
						break;
					case '-14':
						$message = 'هیچ ترمینالی تعریف نشده است.';
						break;
					case '-15':
						$message = 'توکن پرداخت صحیح نیست.';
						break;
				}

				return $message;

			}
	

            /**
             * Make sure Hamrahpay is in the gateways list.
             *
             * @since 1.0
             */
            public static function pmpro_gateways($gateways)
            {
                if (empty($gateways['hamrahpay'])) {
                    $gateways['hamrahpay'] = 'همراه پی';
                }

                return $gateways;
            }

            /**
             * Get a list of payment options that the Hamrahpay gateway needs/supports.
             *
             * @since 1.0
             */
            public static function getGatewayOptions()
            {
                $options = [
                    'hamrahpay_api_key',
					'currency',
                ];

                return $options;
            }

            /**
             * Set payment options for payment settings page.
             *
             * @since 1.0
             */
            public static function pmpro_payment_options($options)
            {
                //get hamrahpay options
                $hamrahpay_options = self::getGatewayOptions();

                //merge with others.
                $options = array_merge($hamrahpay_options, $options);

                return $options;
            }

            /**
             * Remove required billing fields.
             *
             * @since 1.8
             */
            public static function pmpro_required_billing_fields($fields)
            {
                unset($fields['bfirstname']);
                unset($fields['blastname']);
                unset($fields['baddress1']);
                unset($fields['bcity']);
                unset($fields['bstate']);
                unset($fields['bzipcode']);
                unset($fields['bphone']);
                unset($fields['bemail']);
                unset($fields['bcountry']);
                unset($fields['CardType']);
                unset($fields['AccountNumber']);
                unset($fields['ExpirationMonth']);
                unset($fields['ExpirationYear']);
                unset($fields['CVV']);

                return $fields;
            }

            /**
             * Display fields for Hamrahpay options.
             *
             * @since 1.0
             */
            public static function pmpro_payment_option_fields($values, $gateway)
            {
                ?>
                <tr class="pmpro_settings_divider gateway gateway_hamrahpay" <?php if ($gateway != 'hamrahpay') {
                    ?>style="display: none;"<?php 
                }
                ?>>
                <td colspan="2">
                    <?php echo 'تنظیمات همراه پی';
                ?>
                </td>
                </tr>
                <tr class="gateway gateway_hamrahpay" <?php if ($gateway != 'hamrahpay') {
                    ?>style="display: none;"<?php 
                }
                ?>>
                <th scope="row" valign="top">
                <label for="hamrahpay_api_key">API Key جهت اتصال به همراه پی:</label>
                </th>
                <td>
                    <input type="text" id="hamrahpay_api_key" name="hamrahpay_api_key" size="60" value="<?php echo esc_attr($values['hamrahpay_api_key']);
                ?>" />
                </td>
                </tr>

                <?php

            }

            public static function start_payment($user_id, $morder)
            {
                global $wpdb, $discount_code_id;

                //if no order, no need to pay
                if (empty($morder)) {
                    return;
                }

                $morder->user_id = $user_id;
                $morder->saveOrder();

                //save discount code use
                if (!empty($discount_code_id)) {
                    $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('".$discount_code_id."', '".$user_id."', '".$morder->id."', now())");
                }

                //$morder->Gateway->sendToTwocheckout($morder);
                global $pmpro_currency;

                $api_key = pmpro_getOption('hamrahpay_api_key');
                $url = self::getApiUrl('/rest/pg/pay-request');

                $order_id = $morder->code;
                $redirect = admin_url('admin-ajax.php')."?action=hamrahpay-ins&oid=$order_id";


                global $pmpro_currency;

                $amount = intval($morder->subtotal);
                if( $pmpro_currency == 'IRT' )
                    $amount *= 10;

                $result = json_decode(self::post_data($url,
                        [
                            'api_key'  => $api_key,
                            'amount'      => $amount,
                            'description' => 'سفارش عضویت : '.$order_id,
                            'callback_url' => $redirect,
                        ]
                ),true);
				
				
				if (!empty($result['status']) && $result['status']==1)
				{
					header("Location: ".$result['pay_url']);
                    die();
				}
				else
				{
					$Err = 'خطا در ارسال اطلاعات به وب سرویس همراه پی. کد خطا :  '.self::error_reason($result['error_code']);
                    $morder->status = 'cancelled';
                    $morder->notes = $Err;
                    $morder->saveOrder();
                    die($Err);
				}

            }

            public static function callback_from_hamrahpay()
            {
                global $gateway_environment;
                if (!isset($_GET['oid']) || is_null($_GET['oid'])) {
                    die('مقدار oid ست نشده است.');
                }

                $oid = $_GET['oid'];
				
				
                $morder = null;
                try {
                    $morder = new MemberOrder($oid);
                    $morder->getMembershipLevel();
                    $morder->getUser();
                } catch (Exception $exception) {
                    die('کد سفارش نامعتبر است.');
                }

                $current_user_id = get_current_user_id();

                if ($current_user_id !== intval($morder->user_id)) {
                    die('این خرید متعلق به شما نیست.');
                }

                
				$api = pmpro_getOption('hamrahpay_api_key');
                $payment_token = $_GET['payment_token'];
                $status= $_GET['status'];
				
				if ($status=='OK')
				{
					
					$url = self::getApiUrl('/rest/pg/verify');
					$result = json_decode(self::post_data($url,
							[
								'api_key' => $api,
								'payment_token'  => $payment_token,
							]
					),true);

					if ($result['status'] == 100) {
						
						if (self::upgrade_user_level($morder, $result['reserve_number'])) {
							wp_redirect(pmpro_url('confirmation', '?level=' . $morder->membership_level->id));
									exit;
						}
					} else {
						$Err = 'خطا در ارسال اطلاعات به وب سرویس همراه پی. کد خطا :  '.$result['error_code'];
						$morder->status = 'cancelled';
						$morder->notes = $Err;
						$morder->saveOrder();
						wp_redirect( pmpro_url());
						die($Err);
					}
				}
				else
				{
					$Err = 'پرداخت موفق نبوده است.';
					$morder->status = 'cancelled';
					$morder->notes = $Err;
					$morder->saveOrder();
					wp_redirect( pmpro_url());
					die($Err);
				}

            }

            public static function upgrade_user_level(&$morder, $txn_id)
            {
                global $wpdb;
                //filter for level
                $morder->membership_level = apply_filters('pmpro_inshandler_level', $morder->membership_level, $morder->user_id);

                //fix expiration date
                if (!empty($morder->membership_level->expiration_number)) {
                    $enddate = "'".date('Y-m-d', strtotime('+ '.$morder->membership_level->expiration_number.' '.$morder->membership_level->expiration_period, current_time('timestamp')))."'";
                } else {
                    $enddate = 'NULL';
                }

                //get discount code
                $morder->getDiscountCode();
                if (!empty($morder->discount_code)) {
                    //update membership level
                    $morder->getMembershipLevel(true);
                    $discount_code_id = $morder->discount_code->id;
                } else {
                    $discount_code_id = '';
                }

                //set the start date to current_time('mysql') but allow filters
                $startdate = apply_filters('pmpro_checkout_start_date', "'".current_time('mysql')."'", $morder->user_id, $morder->membership_level);

                //custom level to change user to
                $custom_level = [
                    'user_id'         => $morder->user_id,
                    'membership_id'   => $morder->membership_level->id,
                    'code_id'         => $discount_code_id,
                    'initial_payment' => $morder->membership_level->initial_payment,
                    'billing_amount'  => $morder->membership_level->billing_amount,
                    'cycle_number'    => $morder->membership_level->cycle_number,
                    'cycle_period'    => $morder->membership_level->cycle_period,
                    'billing_limit'   => $morder->membership_level->billing_limit,
                    'trial_amount'    => $morder->membership_level->trial_amount,
                    'trial_limit'     => $morder->membership_level->trial_limit,
                    'startdate'       => $startdate,
                    'enddate'         => $enddate, ];

                global $pmpro_error;
                if (!empty($pmpro_error)) {
                    echo $pmpro_error;
                    inslog($pmpro_error);
                }

                if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
                    //update order status and transaction ids
                    $morder->status = 'success';
                    $morder->payment_transaction_id = $txn_id;
                    //if( $recurring )
                    //    $morder->subscription_transaction_id = $txn_id;
                    //else
                    $morder->subscription_transaction_id = '';
                    $morder->saveOrder();

                    //add discount code use
                    if (!empty($discount_code) && !empty($use_discount_code)) {
                        $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('".$discount_code_id."', '".$morder->user_id."', '".$morder->id."', '".current_time('mysql')."')");
                    }

                    //save first and last name fields
                    if (!empty($_POST['first_name'])) {
                        $old_firstname = get_user_meta($morder->user_id, 'first_name', true);
                        if (!empty($old_firstname)) {
                            update_user_meta($morder->user_id, 'first_name', $_POST['first_name']);
                        }
                    }
                    if (!empty($_POST['last_name'])) {
                        $old_lastname = get_user_meta($morder->user_id, 'last_name', true);
                        if (!empty($old_lastname)) {
                            update_user_meta($morder->user_id, 'last_name', $_POST['last_name']);
                        }
                    }

                    //hook
                    do_action('pmpro_after_checkout', $morder->user_id,$morder);

                    //setup some values for the emails
                    if (!empty($morder)) {
                        $invoice = new MemberOrder($morder->id);
                    } else {
                        $invoice = null;
                    }

                    //inslog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");

                    $user = get_userdata(intval($morder->user_id));
                    if (empty($user)) {
                        return false;
                    }

                    $user->membership_level = $morder->membership_level;  //make sure they have the right level info
                    //send email to member
                    $pmproemail = new PMProEmail();
                    $pmproemail->sendCheckoutEmail($user, $invoice);

                    //send email to admin
                    $pmproemail = new PMProEmail();
                    $pmproemail->sendCheckoutAdminEmail($user, $invoice);

                    return true;
                } else {
                    return false;
                }
            }
        }
    }
}
