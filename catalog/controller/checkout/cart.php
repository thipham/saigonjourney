<?php
class ControllerCheckoutCart extends Controller {
	private $error = array();
	public function index() {
		$this->load->language('checkout/cart');

		// Add custom checkout
		if(isset($this->request->post['submitorder']) && $this->cart->hasProducts()) {
			
			$result = $this->confirmOrder();
			if($result){
				$this->response->redirect($this->url->link('checkout/success'));
			}
		}

		$this->cacheValues();




		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('common/home'),
			'text' => $this->language->get('text_home')
		);

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('checkout/cart'),
			'text' => $this->language->get('heading_title')
		);

		if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {

			// Add custom checkout
			$data['txt_order_last_name'] = $this->language->get('text_order_last_name');
      		$data['txt_order_first_name'] = $this->language->get('text_order_first_name');
      		$data['txt_order_phone'] = $this->language->get('text_order_phone');
      		$data['txt_order_email'] = $this->language->get('text_order_email');
      		$data['txt_order_address'] = $this->language->get('text_order_address');
      		$data['txt_order_note'] = $this->language->get('text_order_note');
      		$data['txt_confirm_clear'] = $this->language->get('text_confirm_clear');
      		$data['txt_order_message'] = $this->language->get('text_order_message');


			if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
				$data['error_warning'] = $this->language->get('error_stock');
			} elseif (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];

				unset($this->session->data['error']);
			} else {
				$data['error_warning'] = '';
			}

			if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
				$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
			} else {
				$data['attention'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}


			// Add custom checkout
			if (isset($this->error['cus-first-name'])) {
				$data['firstNameError'] = $this->error['cus-first-name'];
				$data['errorMessage'] = $this->language->get('error_message');
			}else {
				$data['firstNameError'] = '';
			}
			if (isset($this->error['cus-last-name'])) {
				$data['lastNameError'] = $this->error['cus-last-name'];
				$data['errorMessage'] = $this->language->get('error_message');
			}else {
				$data['lastNameError'] = '';
			}
			if (isset($this->error['cus-fone'])) {
				$data['foneError'] = $this->error['cus-fone'];
				$data['errorMessage'] = $this->language->get('error_message');
			}else {
				$data['foneError'] = '';
			}
			if (isset($this->error['cus-email'])) {
				$data['emailError'] = $this->error['cus-email'];
				$data['errorMessage'] = $this->language->get('error_message');
			}else {
				$data['emailError'] = '';
			}
			if (isset($this->error['cus-address'])) {
				$data['addressError'] = $this->error['cus-address'];
				$data['errorMessage'] = $this->language->get('error_message');
			}else {
				$data['addressError'] = '';
			}

			//Set values:
			$this->setValues();



			$data['action'] = $this->url->link('checkout/cart/edit', '', true);

			if ($this->config->get('config_cart_weight')) {
				$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
			} else {
				$data['weight'] = '';
			}

			$this->load->model('tool/image');
			$this->load->model('tool/upload');

			$data['products'] = array();

			$products = $this->cart->getProducts();

			foreach ($products as $product) {
				$product_total = 0;

				foreach ($products as $product_2) {
					if ($product_2['product_id'] == $product['product_id']) {
						$product_total += $product_2['quantity'];
					}
				}

				if ($product['minimum'] > $product_total) {
					$data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
				}

				if ($product['image']) {
					$image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
				} else {
					$image = '';
				}

				$option_data = array();

				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
					
					$price = $this->currency->format($unit_price, $this->session->data['currency']);
					$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
				} else {
					$price = false;
					$total = false;
				}

				$recurring = '';

				if ($product['recurring']) {
					$frequencies = array(
						'day'        => $this->language->get('text_day'),
						'week'       => $this->language->get('text_week'),
						'semi_month' => $this->language->get('text_semi_month'),
						'month'      => $this->language->get('text_month'),
						'year'       => $this->language->get('text_year')
					);

					if ($product['recurring']['trial']) {
						$recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
					}

					if ($product['recurring']['duration']) {
						$recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					} else {
						$recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					}
				}

				$data['products'][] = array(
					'cart_id'   => $product['cart_id'],
					'thumb'     => $image,
					'name'      => $product['name'],
					'model'     => $product['model'],
					'option'    => $option_data,
					'recurring' => $recurring,
					'quantity'  => $product['quantity'],
					'stock'     => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
					'reward'    => ($product['reward'] ? sprintf($this->language->get('text_points'), $product['reward']) : ''),
					'price'     => $price,
					'total'     => $total,
					'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
				);
			}

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $key => $voucher) {
					$data['vouchers'][] = array(
						'key'         => $key,
						'description' => $voucher['description'],
						'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency']),
						'remove'      => $this->url->link('checkout/cart', 'remove=' . $key)
					);
				}
			}

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;
			
			// Because __call can not keep var references so we put them into an array. 			
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);
			
			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);
						
						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$data['totals'] = array();

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $this->session->data['currency'])
				);
			}

			$data['continue'] = $this->url->link('common/home');

			$data['checkout'] = $this->url->link('checkout/checkout', '', true);

			// Add custom checkout

			$data['order'] = $this->url->link('checkout/confirmOrder');
			$data['action'] = $this->url->link('checkout/cart'); 

			$this->load->model('setting/extension');

			$data['modules'] = array();
			
			$files = glob(DIR_APPLICATION . '/controller/extension/total/*.php');

			if ($files) {
				foreach ($files as $file) {
					$result = $this->load->controller('extension/total/' . basename($file, '.php'));
					
					if ($result) {
						$data['modules'][] = $result;
					}
				}
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('checkout/cart', $data));
		} else {
			$data['text_error'] = $this->language->get('text_empty');
			
			$data['continue'] = $this->url->link('common/home');

			unset($this->session->data['success']);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	public function confirmOrder() {

		$hasError = false;

		//Check user's inputs:
		$firstname = $this->request->post['cus-first-name']; 
		$lastname = $this->request->post['cus-last-name'];
		$fone = $this->request->post['cus-phone'];
		$email = $this->request->post['cus-email'];
		$address = $this->request->post['cus-address'];
		$note = $this->request->post['cus-note'];

		if ((utf8_strlen($firstname) < 1) || (utf8_strlen($firstname) > 32)) {
      		$this->error['cus-first-name'] = $this->language->get('error_order_first_name');
      		$hasError = true;
    	}

    	if ((utf8_strlen($lastname) < 1) || (utf8_strlen($lastname) > 32)) {
      		$this->error['cus-last-name'] = $this->language->get('error_order_last_name');
      		$hasError = true;
    	}

    	if ((utf8_strlen($fone) < 8) || (utf8_strlen($fone) > 15)) {
      		$this->error['cus-fone'] = $this->language->get('error_order_fone');
      		$hasError = true;
    	}

    	if (utf8_strlen($email) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      		$this->error['cus-email'] = $this->language->get('error_order_email');
      		$hasError = true;
    	}

    	if (utf8_strlen($address) < 1) {
      		$this->error['cus-address'] = $this->language->get('error_order_address');
      		$hasError = true;
    	}

    	if($hasError)
    		return false;

		$total_data = array();
		$total = 0;
		$taxes = $this->cart->getTaxes();
		 
		$this->load->model('setting/extension');
		
		$sort_order = array();		
		$results = $this->model_setting_extension->getExtensions('total');			
		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
		}			
		array_multisort($sort_order, SORT_ASC, $results);
		foreach ($results as $result) {
			if ($this->config->get($result['code'] . '_status')) {
				$this->load->model('total/' . $result['code']);
	
				$this->{'model_total_' . $result['code']}->getTotal($total_data, $total, $taxes);
			}
		}
		
		$sort_order = array(); 		  
		foreach ($total_data as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}	
		array_multisort($sort_order, SORT_ASC, $total_data);			
		
		//Data to save:
		$data = array();		
		$data['totals'] = $total_data;
		$data['comment'] = $note;
		$data['total'] = $total;
		$data['affiliate_id'] = 0;
		$data['commission'] = 0;
		$data['order_status_id'] = $this->config->get('config_order_status_id');
		$data['language_id'] = $this->config->get('config_language_id');
		$data['currency_id'] = $this->currency->getId($this->session->data['currency']);
		$data['currency_code'] = $this->session->data['currency'];
		$data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
		$data['ip'] = $this->request->server['REMOTE_ADDR'];

		$data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
		$data['store_id'] = $this->config->get('config_store_id');
		$data['store_name'] = $this->config->get('config_name');
		
		if ($data['store_id']) {
			$data['store_url'] = $this->config->get('config_url');		
		} else {
			$data['store_url'] = HTTP_SERVER;	
		}
		
		$data['customer_id'] = 0;
		$data['customer_group_id'] = 1;
		$data['firstname'] = $firstname;
		$data['lastname'] = $lastname;
		$data['email'] = $email;
		$data['telephone'] = $fone;
		$data['fax'] = '';
		$data['payment_firstname'] = $firstname;
		$data['payment_lastname'] =  $lastname;	
		$data['payment_address_1'] = $address;
		$data['payment_address_2'] = '';
		$data['payment_company'] = '';
		$data['payment_company_id'] = '';		
		$data['payment_city'] = '0';
		$data['payment_postcode'] = '0';
		$data['payment_country'] = 'Viet Nam';
		$data['payment_country_id'] = '320';
		$data['payment_zone'] = '0';
		$data['payment_zone_id'] = '0';
		$data['payment_address_format'] = '';
		$data['payment_tax_id'] = '0';
		$data['payment_method'] = 'COD';
		$data['payment_code'] = 'cod';	

		$data['affiliate_id'] = 0;
		$data['commission'] = 0;
		$data['marketing_id'] = 0;
		$data['tracking'] = '';		
					
		if ($this->cart->hasShipping()) {
			$data['shipping_firstname'] = $firstname;
			$data['shipping_lastname'] = $lastname;	
			$data['shipping_address_1'] = $address;
			$data['shipping_address_2'] = '';
			$data['shipping_company'] = '';	
			$data['shipping_postcode'] = '0';
			$data['shipping_country'] = 'Viet Nam';
			$data['shipping_country_id'] = '230';
			$data['shipping_zone'] = '0';
			$data['shipping_zone_id'] = '0';
			$data['shipping_address_format'] = '';
			$data['shipping_city'] = '';
			$data['shipping_method'] = 'Free shipping';
			$data['shipping_code'] = 'free.free';
		} else {
			$data['shipping_firstname'] = $firstname;
			$data['shipping_lastname'] = $lastname;	
			$data['shipping_address_1'] = $address;
			$data['shipping_address_2'] = '';
			$data['shipping_company'] = '';	
			$data['shipping_postcode'] = '0';
			$data['shipping_country'] = 'Viet Nam';
			$data['shipping_country_id'] = '230';
			$data['shipping_zone'] = '0';
			$data['shipping_zone_id'] = '0';
			$data['shipping_address_format'] = '';
			$data['shipping_city'] = '';
			$data['shipping_method'] = 'Free shipping';
			$data['shipping_code'] = 'free.free';
		}
		
		$product_data = array();		
		foreach ($this->cart->getProducts() as $product) {
			$product_data[] = array(
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'download'   => $product['download'],
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $product['price'],
				'total'      => $product['total'],
				'tax'        => 0,
				'reward'     => $product['reward'],
				'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id'])
			);
		}
		$data['products'] = $product_data;

		if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];	
		} elseif(!empty($this->request->server['HTTP_CLIENT_IP'])) {
			$data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];	
		} else {
			$data['forwarded_ip'] = '';
		}
		
		if (isset($this->request->server['HTTP_USER_AGENT'])) {
			$data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];	
		} else {
			$data['user_agent'] = '';
		}
		
		if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
			$data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];	
		} else {
			$data['accept_language'] = '';
		}
					
		$this->load->model('checkout/order');			
		$this->session->data['order_id'] = $this->model_checkout_order->addOrderAsConfirmed($data);
		
		//Send mail:
		if(isset($this->session->data['order_id']) && $this->session->data['order_id'] != null){
			//$this->sendMail($email, $data);
		}

		return true;
  	}
	
	private function sendMail($cusEmail, $orderDetail){
		try {
		
			$htmlContent = "Kính chào quý khách " . $orderDetail["lastname"] . " " . $orderDetail["firstname"] . "! <br><br>" ;
			$htmlContent .= "Cám ơn quý khách đã chọn sản phẩm của chúng tôi. " . "<b>ChiThu.vn</b> vừa nhận được đơn hàng số " . $this->session->data['order_id'] . " của quý khách.  <br>";
			$htmlContent .= "Bằng email này, chúng tôi xác nhận đơn hàng của quý khách đã được đặt thành công. Nhân viên của chúng tôi sẽ liên lạc với quý khách để xác nhận thời gian giao hàng cụ thể.  <br> <br>";
			$htmlContent .= "Thông tin đơn hàng như sau:  <br> <br>";
			$htmlContent .= "<table>";
			$htmlContent .= "<thead>";
			$htmlContent .= "<tr>";
			$htmlContent .= "<td>STT</td><td >Sản Phẩm</td><td>Số Lượng</td><td>Đơn Giá</td><td>Thành tiền</td>";
			$htmlContent .= "</tr>";
			$htmlContent .= "</thead>";
			$htmlContent .= "<tbody>";
			$index = 1;
			foreach ($orderDetail["products"] as $product) {				
				$htmlContent .= "<tr>";
				$htmlContent .= "<td>". $index . "</td>";
				$htmlContent .= "<td>". $product["name"] . "</td>";
				$htmlContent .= "<td>". $product["quantity"] . "</td>";
				$htmlContent .= "<td>". number_format($product["price"]) . "</td>";
				$htmlContent .= "<td>". number_format($product["total"]) . "</td>";
				$htmlContent .= "</tr>";
			}
			$htmlContent .= "<tr><td colspan='4' style='text-align: right; padding-right: 15px;'>Tổng cộng</td><td>".  $orderDetail["totals"][count($orderDetail["totals"]) - 1]['text'] ."</td></tr>";
			$htmlContent .= "</tbody>";
			$htmlContent .= "</table>";
			$htmlContent .= "<p>Trân trọng, <br> <strong>Quản lý ChiThu.vn</strong></p>";

			$message  = '<html dir="ltr" lang="vi">';
			$message .= '  <head>';
			$message .= '    <title>Xác nhận đơn hàng - ChiThu.vn</title>';
			$message .= '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
			$message .= '    <style type="text/css">';
			$message .= '    body { font-size: 14px; } table{ border-collapse:collapse; width: 100%; } table td { text-align: center; border: 1px solid #DDD; padding: 10px; }';
			$message .= '    table thead td { background-color: #F7A52F; color: #FFF;}';
			$message .= '    </style>';
			$message .= '  </head>';
			$message .= '  <body><p>' . $htmlContent. '</p><p><img src="http://chithu.vn/image/data/logo.png" /></p></body>';
			$message .= '</html>';
			
			$mail = new Mail();	
			$mail->protocol = $this->config->get('config_mail_protocol');
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->hostname = $this->config->get('config_smtp_host');
			$mail->username = $this->config->get('config_smtp_username');
			$mail->password = $this->config->get('config_smtp_password');
			$mail->port = $this->config->get('config_smtp_port');
			$mail->timeout = $this->config->get('config_smtp_timeout');				
			$mail->setTo($cusEmail);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender($this->config->get('config_name'));
			$mail->setSubject(html_entity_decode('Thông báo đơn hàng - ChiThu.vn', ENT_QUOTES, 'UTF-8'));				
			$mail->setHtml($message);
			$mail->send();
		}
		catch(Exception  $e){
			echo $e->getMessage();
		}
	}

  	protected function cacheValues(){
  		//Check and set values:
		if (isset($this->request->post['cus-first-name'])) {
			$this->session->data['customerFirstName'] = $this->request->post['cus-first-name'];		
			echo $this->session->data['customerFirstName'];	
		}
		if (isset($this->request->post['cus-last-name'])) {
			$this->session->data['customerLastName'] = $this->request->post['cus-last-name'];
		}
		if (isset($this->request->post['cus-phone'])) {
			$this->session->data['customerPhone'] = $this->request->post['cus-phone'];
		}
		if (isset($this->request->post['cus-email'])) {
			$this->session->data['customerEmail'] = $this->request->post['cus-email'];
		}
		if (isset($this->request->post['cus-address'])) {
			$this->session->data['customerAddress'] = $this->request->post['cus-address'];
		}
		if (isset($this->request->post['cus-note'])) {
			$this->session->data['customerNote'] = $this->request->post['cus-note'];
		}
  	}

  	protected function setValues() {
  		if (isset($this->session->data['customerFirstName'])) {
			$data['customerFirstName'] = $this->session->data['customerFirstName'];		
		}else {
			$data['customerFirstName'] = '';
		}
		if (isset($this->session->data['customerLastName'])) {
			$data['customerLastName'] = $this->session->data['customerLastName'];				
		}else {
			$data['customerLastName'] = '';
		}
		if (isset($this->session->data['customerFirstName'])) {
			$data['customerFirstName'] = $this->session->data['customerFirstName'];				
		}else {
			$data['customerFirstName'] = '';
		}
		if (isset($this->session->data['customerPhone'])) {
			$data['customerPhone'] = $this->session->data['customerPhone'];				
		}else {
			$data['customerPhone'] = '';
		}
		if (isset($this->session->data['customerEmail'])) {
			$data['customerEmail'] = $this->session->data['customerEmail'];				
		}else {
			$data['customerEmail'] = '';
		}
		if (isset($this->session->data['customerAddress'])) {
			$data['customerAddress'] = $this->session->data['customerAddress'];				
		}else {
			$data['customerAddress'] = '';
		}
		if (isset($this->session->data['customerNote'])) {
			$data['customerNote'] = $this->session->data['customerNote'];				
		}else {
			$data['customerNote'] = '';
		}
  	}

	public function add() {
		$this->load->language('checkout/cart');

		$json = array();

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			if (isset($this->request->post['quantity'])) {
				$quantity = (int)$this->request->post['quantity'];
			} else {
				$quantity = 1;
			}

			if (isset($this->request->post['option'])) {
				$option = array_filter($this->request->post['option']);
			} else {
				$option = array();
			}

			$product_options = $this->model_catalog_product->getProductOptions($this->request->post['product_id']);

			foreach ($product_options as $product_option) {
				if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
					$json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
				}
			}

			if (isset($this->request->post['recurring_id'])) {
				$recurring_id = $this->request->post['recurring_id'];
			} else {
				$recurring_id = 0;
			}

			$recurrings = $this->model_catalog_product->getProfiles($product_info['product_id']);

			if ($recurrings) {
				$recurring_ids = array();

				foreach ($recurrings as $recurring) {
					$recurring_ids[] = $recurring['recurring_id'];
				}

				if (!in_array($recurring_id, $recurring_ids)) {
					$json['error']['recurring'] = $this->language->get('error_recurring_required');
				}
			}

			if (!$json) {
				$this->cart->add($this->request->post['product_id'], $quantity, $option, $recurring_id);

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']), $product_info['name'], $this->url->link('checkout/cart'));

				// Unset all shipping and payment methods
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);

				// Totals
				$this->load->model('setting/extension');

				$totals = array();
				$taxes = $this->cart->getTaxes();
				$total = 0;
		
				// Because __call can not keep var references so we put them into an array. 			
				$total_data = array(
					'totals' => &$totals,
					'taxes'  => &$taxes,
					'total'  => &$total
				);

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$sort_order = array();

					$results = $this->model_setting_extension->getExtensions('total');

					foreach ($results as $key => $value) {
						$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
					}

					array_multisort($sort_order, SORT_ASC, $results);

					foreach ($results as $result) {
						if ($this->config->get('total_' . $result['code'] . '_status')) {
							$this->load->model('extension/total/' . $result['code']);

							// We have to put the totals in an array so that they pass by reference.
							$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
						}
					}

					$sort_order = array();

					foreach ($totals as $key => $value) {
						$sort_order[$key] = $value['sort_order'];
					}

					array_multisort($sort_order, SORT_ASC, $totals);
				}

				//$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
				$json['total'] = sprintf($this->cart->countProducts());
			} else {
				$json['redirect'] = str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function edit() {
		$this->load->language('checkout/cart');

		$json = array();

		// Update
		if (!empty($this->request->post['quantity'])) {
			foreach ($this->request->post['quantity'] as $key => $value) {
				$this->cart->update($key, $value);
			}

			$this->session->data['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			$this->response->redirect($this->url->link('checkout/cart'));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('checkout/cart');

		$json = array();

		// Remove
		if (isset($this->request->post['key'])) {
			$this->cart->remove($this->request->post['key']);

			unset($this->session->data['vouchers'][$this->request->post['key']]);

			$json['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array. 			
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);

						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			//$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
			$json['total'] = sprintf($this->cart->countProducts());
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
