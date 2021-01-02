<?php

defined('_JEXEC') or die('Restricted access');

jimport('joomla.event.plugin');

$lang = JFactory::getLanguage();

$lang->load('plg_djclassifiedspayment_djcfpayping',JPATH_ADMINISTRATOR);

require_once(JPATH_BASE.DS.'administrator/components/com_djclassifieds/lib/djseo.php');

require_once(JPATH_BASE.DS.'administrator/components/com_djclassifieds/lib/djnotify.php');

class plgdjclassifiedspaymentdjcfpayping extends JPlugin
{

	// constructor
	function __construct ( &$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->loadLanguage('plg_djcfpayping');

		$params["plugin_name"] = "djcfPayping";
		$params["icon"] = "payping_icon.png";
		$params["logo"] = "payping_overview.png";
		$params["title"] = $this->params->get("title");
		$params["description"] = $this->params->get("description");
		$params["payment_method"] = 'Payping';
		$params["currency"] = $this->params->get("currency");
		$params["token_code"] = $this->params->get("token_code");

		$this->params = $params;
	}

	function onProcessPayment()
	{

		$ptype = JRequest::getVar('ptype','');
		$id = JRequest::getInt('id','0');

		$html="";

		if($ptype == $this->params["plugin_name"])
		{

			$action = JRequest::getVar('pactiontype','');
			switch ($action)
			{
				case "notify" :
					$html = $this->_notify_url();
					break;
				case 'process':
				default :
					$html =  $this->process($id);
					break;
			}
		}

		return $html;
	}

	function process($id)
	{

		JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');		
		jimport( 'joomla.database.table' );

		$db 	= JFactory::getDBO();
		$app 	= JFactory::getApplication();
		$par 	= JComponentHelper::getParams( 'com_djclassifieds' );
		$user 	= JFactory::getUser();
		$ptype	= JRequest::getVar('ptype'); // payment plaugin type
		$type	= JRequest::getVar('type','');
		$row 	= JTable::getInstance('Payments', 'DJClassifiedsTable');
		$remote_addr =  $_SERVER['REMOTE_ADDR'];
		$pdetails = DJClassifiedsPayment::processPayment($id, $type,$ptype);
    $payment_id = $pdetails['item_id'];
		
		/*if($type=='prom_top'){

			$query ="SELECT i.* FROM #__djcf_items i WHERE i.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();

			if(!isset($item)){
				$message = JText::_('کالا یافت نشد!');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $par->get('promotion_move_top_price',0);
			$row->type=2;
			$row->store();

			$amount = $par->get('promotion_move_top_price',0);
			$itemname = $item->name;
			$payment_id = $row->id;
			$item_cid = '&cid='.$item->cat_id;

		}else if($type=='points'){

			$query ="SELECT p.* FROM #__djcf_points p WHERE p.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$points = $db->loadObject();

			if(!isset($points)){
				$message = JText::_('پکیج امتیاز پیدا نشد!');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $points->price;
			$row->type=1;
			$row->store();

			$amount = $points->price;
			$itemname = $points->name;
			$payment_id = $row->id;
			$item_cid = '';

		}else{

			$query ="SELECT i.*, c.price as c_price FROM #__djcf_items i LEFT JOIN #__djcf_categories c ON c.id=i.cat_id WHERE i.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();

			if(!isset($item)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}

			$amount = 0;
			if(strstr($item->pay_type, 'cat')){
				$amount += $item->c_price/100;
			}
			if(strstr($item->pay_type, 'type,')){
				$itype = DJClassifiedsPayment::getTypePrice($item->user_id,$item->type_id);
				$amount += $itype->price;
			}


			$query = "SELECT * FROM #__djcf_days d WHERE d.days=".$item->exp_days." LIMIT 1";
			$db->setQuery($query);
			$day = $db->loadObject();
			
			if(strstr($item->pay_type, 'duration_renew')){
				$amount += $day->price_renew;
			}else if(strstr($item->pay_type, 'duration')){
				$amount += $day->price;
			}
			
			if(strstr($item->pay_type, 'extra_img_renew')){
				if($day->img_price_default){
					$amount += $par->get('img_price_renew','0')*$item->extra_images_to_pay;
				}else{
					$amount += $day->img_price_renew*$item->extra_images_to_pay;
				}
			}else if(strstr($item->pay_type, 'extra_img')){
				if($day->img_price_default){
					$amount += $par->get('img_price','0')*$item->extra_images_to_pay;
				}else{
					$amount += $day->img_price*$item->extra_images_to_pay;
				}
			}
			
			if(strstr($item->pay_type, 'extra_chars_renew')){
				if($day->char_price_default){
					$amount += $par->get('desc_char_price_renew','0')*$item->extra_chars_to_pay;
				}else{
					$amount += $day->char_price_renew*$item->extra_chars_to_pay;
				}
			}else if(strstr($item->pay_type, 'extra_chars')){
				if($day->char_price_default){
					$amount += $par->get('desc_char_price','0')*$item->extra_chars_to_pay;
				}else{
					$amount += $day->char_price*$item->extra_chars_to_pay;
				}
			}
			
			
			$query = "SELECT p.* FROM #__djcf_promotions p WHERE p.published=1 ORDER BY p.id ";
			$db->setQuery($query);
			$promotions=$db->loadObjectList();
			foreach($promotions as $prom){
				if(strstr($item->pay_type, $prom->name)){
					$amount += $prom->price;
				}
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $amount;
			$row->type=0;
			$row->store();
			$itemname = $item->name;
			$payment_id = $row->id;
			$item_cid = '&cid='.$item->cat_id;

		}*/

		//TODO: request to payping
		$TokenCode = $this->params['token_code'];
		$Amount = $this->payPingAmount($pdetails['amount']);
		$payment_title = 'ItemID:'.$id.' ('.$pdetails['itemname'].')';
		$payment_reason = $type ? $type : $item->pay_type;
		$Description = $payment_title.' - '.$payment_reason;
		$Mobile = '';
		$Email = empty($user->email) ? '' : $user->email;

		$CallbackURL = JRoute::_(JURI::base() . 'index.php?option=com_djclassifieds&task=processPayment&ptype=djcfPayping&pactiontype=notify&id='.$payment_id. '&amount=' . $pdetails['amount']);

		$params = array(
			'amount'        => $Amount,
			'payerIdentity' => $Email,
			'payerName'     => $Email,
			'description'   => $Description,
			'returnUrl'     => $CallbackURL,
			'clientRefId'   => $payment_id
		);

		try{
			$curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => 'https://api.payping.ir/v2/pay',
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => '',
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => 'POST',
			  CURLOPT_POSTFIELDS => json_encode($params),
			  CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Content-Type: application/json',
				'Authorization: Bearer '.$TokenCode
			  ),
			));

			$response = curl_exec($curl);
			$header = curl_getinfo($curl);
			curl_close($curl);
			
			if ($header['http_code'] == 200) {
				$response = json_decode($response, true);
				if (isset($response["code"]) and $response["code"] != '') {
					$app->redirect("https://api.payping.ir/v2/pay/gotoipg/".$response['code']);
					exit;
				} else {
					$error = "عدم ایجاد کد پرداخت";
					throw new Exception($error);
				}
			} elseif ($header['http_code'] == 400) {
				$error = "خطا در افزونه";
				throw new Exception($error);
			} else {
				$error = "خطا در پاسخ دهی به درخواست با";
				throw new Exception($error);
			}

		} catch (Exception $e) {

			$return = JRoute::_('index.php/component/djclassifieds/?view=payment&id=' . $id, false);
			$message = JText::_("خطا در پرداخت ، ") . $e->getMessage();
			$app->redirect($return, $message, 'error');
			exit;

		}
	}

	/*

 * called when back from payping

 */

	function _notify_url()
	{

		$db = JFactory::getDBO();
		$par = &JComponentHelper::getParams( 'com_djclassifieds' );

		// $user	= JFactory::getUser();
		$payment_id	= JRequest::getInt('id', 0);
		$app = JFactory::getApplication();
		$input = $app->input;
		$messageUrl = JRoute::_(DJClassifiedsSEO::getCategoryRoute('0:all'));

		if( isset( $_POST['refid'] ) && $_POST['refid'] != '' ){
			$refId = $_POST['refid'];
		}else{
			$refId = $_GET['refid'];
		}

		$Amount = $input->getInt('amount', 0);
		$TokenCode = $this->params['token_code'];

		try{
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.payping.ir/v2/pay/verify",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => json_encode( array( 'refId' => $refId, 'amount' => $Amount ) ),
				CURLOPT_HTTPHEADER => array(
					"accept: application/json",
					"authorization: Bearer ".$TokenCode,
					"cache-control: no-cache",
					"content-type: application/json",
				),
			));
			$response = curl_exec($curl);
			$err = curl_error($curl);
			$header = curl_getinfo($curl);
			curl_close($curl);

			if ($header['http_code'] == 200) {
				$query = "UPDATE #__djcf_payments SET status='Completed', transaction_id='".$refId."' "
					."WHERE id=".$payment_id." AND method='".$this->params['plugin_name']."'";
				$db->setQuery($query);
				$db->query();

				$this->_setPaymentCompleted((int)$payment_id);

				$message = JText::_("PLG_DJCFPAYPING_PAYMENT_SUCCEED") . '<br>' .  JText::_("PLG_DJCFPAYPING_PAYMENT_REF_ID") . $trans_id;
				$app->redirect($messageUrl, $message, 'message');

				exit;
				} else {
					$error = "پرداخت ناموفق ، کد خطا : " . $header['http_code'];
					throw new Exception($error);
				}

		} catch (Exception $e) {
			$message = JText::_("خطا") . $e->getMessage();
			$app->redirect($messageUrl, $message, 'warning');
			exit;
		}
	}

	private function payPingAmount($amount)
	{

		$currency = $this->params['currency'];
		if($currency == "toman"){
			$amount = intval($amount) * 1;
		}else{
		    $amount = intval($amount) * 10;
		}

		return (int)$amount;
	}

	function onPaymentMethodList($val)
	{

		$type='';
		if($val['type']){
			$type='&type='.$val['type'];	
		}		

		$html ='';

		if(!empty($this->params["token_code"])){

			$payText =  'تایید نهایی';
			$paymentLogoPath = JURI::root()."plugins/djclassifiedspayment/djcfpayping/assets/".$this->params["logo"];

			$form_action = JURI::root()."index.php?option=com_djclassifieds&task=processPayment&ptype=".$this->params["plugin_name"]."&pactiontype=process&id=".$val["id"].$type;

			$html ='<table cellpadding="5" cellspacing="0" width="100%" border="0">

				<tr>';

			if(!empty($this->params["logo"])){
				$html .='<td class="td1" width="160" align="center"><img src="'.$paymentLogoPath.'" title="'. $this->params["payment_method"].'" /></td>';
			}

			$html .='<td class="td2">
						<h2 style="font-family: tahoma;font-size: 12px;">'.$this->params["title"].'</h2>
						<p style="text-align:justify;font-family: tahoma;font-size: 12px;">'.$this->params["description"].'</p>
					</td>

					<td class="td3" width="130" align="center">
						<a class="button" style="text-decoration:none;" href="'.$form_action.'">تایید نهایی</a>
					</td>
				</tr>
			</table>';

		}

		return $html;
	}
}