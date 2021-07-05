<?php
namespace App\Http\Controllers\App;

use Validator;
use Mail;
use DB;
use DateTime;
use Hash;
use Auth;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon;
use Log;
use Lang;
use App\Models\AppModels\Orders;

use App\Http\Controllers\App\PaytmController;


class OrderController extends Controller
{

	//hyperpaytoken
	public function hyperpaytoken(Request $request){
    $orderResponse = Orders::hyperpaytoken($request);
		print $orderResponse;
	}


	//hyperpaypaymentstatus
	public function hyperpaypaymentstatus(Request $request){
     $req = array('language_id' => 1);
		$payments_setting = Orders::payments_setting_for_hyperpay($req);

		//check envinment
		if($payments_setting['userid']->environment=='0'){
			$env_url = "https://test.oppwa.com";
		}else{
			$env_url = "https://oppwa.com";
		}

		$url = $env_url.$request->resourcePath;
		$data = "authentication.userId=" .$payments_setting['userid']->value;
			"&authentication.password=" .$payments_setting['password']->value;
			"&authentication.entityId=" .$payments_setting['entityid']->value;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseData = curl_exec($ch);
		if(curl_errno($ch)) {
			return curl_error($ch);
		}
		curl_close($ch);
		//print_r($responseData);
		$data = json_decode($responseData);

		if(preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $data->result->code)){
			$transaction_id = $data->ndc;
			$orders_id = DB::table('orders')->insertGetId(
					[	 'transaction_id' => $transaction_id,
						 'order_information'  => $responseData
					]);
			return redirect('app/paymentsuccess?data='.$responseData);
		}else{
			return redirect('app/paymenterror');
		}

	}

	//paymentsuccess
	public function paymentsuccess(){}

	//paymenterror
	public function paymenterror(){}

	//generate token
	public function generatebraintreetoken(Request $request){
    	$orderResponse = Orders::generatebraintreetoken($request);
		print $orderResponse;
	}

	//instamojoToken
	public function instamojoToken(){
		$req = (object) array('language_id' => 1);
		$payments_setting = Orders::payments_setting_for_instamojo($req);
		$instamojo_client_id 	  = $payments_setting['client_id']->value;
		$instamojo_client_secret  = $payments_setting['client_secret']->value;
		$instamojo = new InstamojoController($instamojo_client_id, $instamojo_client_secret);
		$clientToken = $instamojo->getToken();
		print $clientToken;
	}

	//get default payment method
	public function getpaymentmethods(Request $request){
    $orderResponse = Orders::getpaymentmethods($request);
		print $orderResponse;
	}

	//get shipping / tax Rate
	public function getrate(Request $request){
   $orderResponse = Orders::getrate($request);
		print $orderResponse;
	}

	//get coupons
	public function getcoupon(Request $request){
    $orderResponse = Orders::getcoupon($request);
		print $orderResponse;
	}

	//addtoorder
	public function addtoorder(Request $request){
    $orderResponse = Orders::addtoorder($request);
		print $orderResponse;
	}


	//getorders
	public function getorders(Request $request){
     $orderResponse = Orders::getorders($request);
		print $orderResponse;
	}

	public function get_client_ip_env(){
		$ipaddress = '';
		if (getenv('HTTP_CLIENT_IP'))
			$ipaddress = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('HTTP_X_FORWARDED'))
			$ipaddress = getenv('HTTP_X_FORWARDED');
		else if(getenv('HTTP_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		else if(getenv('HTTP_FORWARDED'))
			$ipaddress = getenv('HTTP_FORWARDED');
		else if(getenv('REMOTE_ADDR'))
			$ipaddress = getenv('REMOTE_ADDR');
		else
			$ipaddress = 'UNKNOWN';

		return $ipaddress;
	}

	//updatestatus
	public function updatestatus(Request $request){
    $orderResponse = Orders::updatestatus($request);
		print $orderResponse;
	}

	public function generatpaytmhashes(Request $request){

		define('PAYTM_MERCHANT_KEY', 'XXXXXXXXXXXXXXX');

		$checkSum = "";

		// below code snippet is mandatory, so that no one can use your checksumgeneration url for other purpose .
		$findme   = 'REFUND';
		$findmepipe = '|';

		$paramList = array();
		
		$obj = new \stdClass;
		$obj->language_id = 1;

		$payments_setting = Orders::payments_setting_for_paytm($obj);

		$paramList["MID"] = $payments_setting['paytm_mid']->value;
		$paramList["ORDER_ID"] = time();
		$paramList["CUST_ID"] = $request->customers_id;
		$paramList["INDUSTRY_TYPE_ID"] = 'Retail';
		$paramList["CHANNEL_ID"] = 'WEB';
		$paramList["TXN_AMOUNT"] = $request->amount;
		$paramList["WEBSITE"] = 'APP_STAGING';

		foreach($_POST as $key=>$value)
		{
		$pos = strpos($value, $findme);
		$pospipe = strpos($value, $findmepipe);
		if ($pos === false || $pospipe === false) 
			{
				$paramList[$key] = $value;
			}
		}


		$paytmClass = new PaytmController();
		//Here checksum string will return by getChecksumFromArray() function.
		$checkSum = $paytmClass->getChecksumFromArray($paramList,PAYTM_MERCHANT_KEY);
		$data = array("CHECKSUMHASH" => $checkSum, "ORDER_ID" => $paramList["ORDER_ID"], "payt_STATUS" => "1");
		$responseData = array('success'=>'1', 'data'=>$data, 'message'=>"Checksum Hashes is returned");
		$orderResponse = json_encode(($responseData),JSON_UNESCAPED_SLASHES);
		print $orderResponse;  
		
	}

}
