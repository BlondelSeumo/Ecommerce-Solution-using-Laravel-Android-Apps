<?php
namespace App\Models\AppModels;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\App\AppSettingController;
use App\Http\Controllers\App\AlertController;
use DB;
use Lang;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Validator;
use Mail;
use DateTime;
use Auth;
use Carbon;


class Banner extends Model
{

 public static function getbanners($request){
  $consumer_data 		 				  =  array();
  $consumer_data['consumer_key'] 	 	  =  request()->header('consumer-key');
  $consumer_data['consumer_secret']	  =  request()->header('consumer-secret');
  $consumer_data['consumer_nonce']	  =  request()->header('consumer-nonce');
  $consumer_data['consumer_device_id']  =  request()->header('consumer-device-id');
  $consumer_data['consumer_ip']  	  = request()->header('consumer-ip');
  $consumer_data['consumer_url']  	  =  __FUNCTION__;


  $authController = new AppSettingController();
  $authenticate = $authController->apiAuthenticate($consumer_data);

  if($authenticate==1){
    //current time
    $currentDate = Carbon\Carbon::now();
    $currentDate = $currentDate->toDateTimeString();

    $banners = DB::table('banners')
          ->LeftJoin('image_categories', function ($join) {
              $join->on('image_categories.image_id', '=', 'banners.banners_image')
                  ->where(function ($query) {
                      $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                          ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                          ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                  });
          })
          ->select('banners_id as id', 'banners_title as title', 'banners_url as url', 'image_categories.image_id', 'image_categories.path as image', 'type', 'banners_title as title')
          ->where('status', '=', '1')
          ->where('expires_date', '>', $currentDate)
          ->where('languages_id', $request->languages_id)
          ->get();

    if(count($banners)>0){
      $responseData = array('success'=>'1', 'data'=>$banners, 'message'=>"Banners are returned successfull.");
    }else{
      $banners = array();
      $responseData = array('success'=>'0', 'data'=>$banners, 'message'=>"Banners are empty.");
    }
  }else{
    $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
  }

  $response = json_encode($responseData);
  print $response;

}

//banners history
public function bannerhistory(Request $request){
  $consumer_data 		 				  =  array();
  $consumer_data['consumer_key'] 	 	  =  request()->header('consumer-key');
  $consumer_data['consumer_secret']	  =  request()->header('consumer-secret');
  $consumer_data['consumer_nonce']	  =  request()->header('consumer-nonce');
  $consumer_data['consumer_device_id']  =  request()->header('consumer-device-id');
  $consumer_data['consumer_ip']  	  = request()->header('consumer-ip');
  $consumer_data['consumer_url']  	  =  __FUNCTION__;
  $authController = new AppSettingController();
  $authenticate = $authController->apiAuthenticate($consumer_data);

  if($authenticate==1){

    $banners_id = $request->banners_id;
    $banners_history_date = date('Y-m-d H:i:s');

    $bannerHistory = DB::table('banners_history')
           ->where('banners_id', '=', $banners_id)
           ->get();

    //if already clicked by other user
    if(count($bannerHistory)){
      $addBanner = DB::table('banners_history')->insert([
                  'banners_clicked' => '1',
                  'banners_history_date' => '$banners_history_date',
                  'banners_id' => '$banners_id'
                ]);
    }else{
      $updateBanner = DB::table('banners_history')->update([
                  'banners_clicked' => '1',
                  'banners_history_date' => '$banners_history_date',
                ])
                ->where('banners_id', '=', '$banners_id');
    }
    $data = array();
    $responseData = array('success'=>'1', 'data'=>$data, 'message'=>"banner history has been added.");

  }else{
    $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
  }

  $response = json_encode($responseData);
  print $response;  
}




}
