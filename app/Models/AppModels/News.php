<?php

namespace App\Models\AppModels;

use Illuminate\Database\Eloquent\Model;
// use App\Http\Controllers\Admin\AdminSiteSettingController;
// use App\Http\Controllers\Admin\AdminCategoriesController;
// use App\Http\Controllers\Admin\AdminProductsController;
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


class News extends Model
{

 public static function allnewscategories($request)
{
  $language_id            				=   $request->language_id;
  $skip									=   $request->page_number.'0';
  $result 	= 	array();
  $data 		=	array();
  $consumer_data 		 				  =  array();
  $consumer_data['consumer_key'] 	 	  =  request()->header('consumer-key');
  $consumer_data['consumer_secret']	  =  request()->header('consumer-secret');
  $consumer_data['consumer_nonce']	  =  request()->header('consumer-nonce');
  $consumer_data['consumer_device_id']  =  request()->header('consumer-device-id');
  $consumer_data['consumer_ip']  =  request()->header('consumer-ip');
  $consumer_data['consumer_url']  	  =  __FUNCTION__;
  $authController = new AppSettingController();
  $authenticate = $authController->apiAuthenticate($consumer_data);

  if($authenticate==1){

  $categories = DB::table('news_categories')
    ->LeftJoin('news_categories_description', 'news_categories_description.categories_id', '=', 'news_categories.categories_id')
    ->LeftJoin('image_categories', function ($join) {
        $join->on('image_categories.image_id', '=', 'news_categories.categories_image')
            ->where(function ($query) {
                $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                    ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                    ->orWhere('image_categories.image_type', '=', 'ACTUAL');
            });
    })
    ->select('news_categories.categories_id as id',
      'image_categories.path as image',
      'image_categories.image_id',
      'news_categories_description.categories_name as name'
    )
    ->where('news_categories_description.language_id','=', $language_id)
    ->where('news_categories.categories_status','=', 1)->skip($skip)->take(10)
    ->get();

  if(count($categories)>0){

    foreach($categories as $categories_data){

      $categories_id = $categories_data->id;
      $news = DB::table('news_categories')
          ->LeftJoin('news_to_news_categories', 'news_to_news_categories.categories_id', '=', 'news_categories.categories_id')
          ->LeftJoin('news', 'news.news_id', '=', 'news_to_news_categories.news_id')
          ->select('news_categories.categories_id', DB::raw('COUNT(DISTINCT news.news_id) as total_news'))
          ->where('news_categories.categories_id','=', $categories_id)
          ->where('news_categories.categories_status','=', 1)
          ->where('news_status',1)
          ->get();

      $categories_data->total_news = $news[0]->total_news;
      array_push($result,$categories_data);
    }

    $responseData = array('success'=>'1', 'data'=>$result, 'message'=>"Returned all categories.", 'categories'=>count($categories));
  }
  else{
    $responseData = array('success'=>'0', 'data'=>array(), 'message'=>"No category found.", 'categories'=>array());
  }
  }else{
    $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
  }
  $categoryResponse = json_encode($responseData);

      return $categoryResponse;
}

public static function getallnews($request)
{
  $language_id            				=   $request->language_id;
  $skip									=   $request->page_number.'0';
  $currentDate 							=   time();
  $type									=	$request->type;
  $consumer_data 		 				  =  array();
  $consumer_data['consumer_key'] 	 	  =  request()->header('consumer-key');
  $consumer_data['consumer_secret']	  =  request()->header('consumer-secret');
  $consumer_data['consumer_nonce']	  =  request()->header('consumer-nonce');
  $consumer_data['consumer_device_id']  =  request()->header('consumer-device-id');
  $consumer_data['consumer_ip']  =  request()->header('consumer-ip');
  $consumer_data['consumer_url']  	  =  __FUNCTION__;
  $authController = new AppSettingController();
  $authenticate = $authController->apiAuthenticate($consumer_data);

  if($authenticate==1){

    if($type=="a to z"){
      $sortby								=	"products_name";
      $order								=	"ASC";
    }elseif($type=="z to a"){
      $sortby								=	"products_name";
      $order								=	"DESC";
    }else{
      $sortby = "news.news_id";
      $order = "desc";
    }


    $categories = DB::table('news_to_news_categories')
      ->leftJoin('news_categories','news_categories.categories_id','=','news_to_news_categories.categories_id')
      ->LeftJoin('news', 'news.news_id', '=', 'news_to_news_categories.news_id');

    $categories->leftJoin('news_description','news_description.news_id','=','news.news_id')
    ->LeftJoin('image_categories', function ($join) {
      $join->on('image_categories.image_id', '=', 'news.news_image')
          ->where(function ($query) {
              $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                  ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                  ->orWhere('image_categories.image_type', '=', 'ACTUAL');
          });
    });
    
    $categories->select('news.*','news_description.*', 'news_to_news_categories.categories_id', 'image_categories.path as news_image',
    'image_categories.image_id');


    //get single category products
    if(!empty($request->categories_id)){
      $categories->where('news_to_news_categories.categories_id','=', $request->categories_id);
    }

    //get single news
    if(!empty($request->news_id) && $request->news_id!=""){
      $categories->where('news.news_id','=', $request->news_id);
    }

    //get featured news
    if($request->is_feature==1){
      $categories->where('news.is_feature','=', 1);
    }


    $categories->where('news_description.language_id','=',$language_id)->where('news_status',1)->where('news_categories.categories_status','=', 1)->orderBy($sortby, $order);

    //count
    $total_record = $categories->get();

    $data  = $categories->skip($skip)->take(10)->get();
    $result = array();
    $index = 0;
    foreach($data as $news_data){
      array_push($result,$news_data);

      $news_description =  $news_data->news_description;
      $result[$index]->news_description = stripslashes($news_description);
      $index++;
    }
    if(count($data)>0){
        $responseData = array('success'=>'1', 'news_data'=>$result,  'message'=>"Returned all products.", 'total_record'=>count($total_record));
      }else{
        $responseData = array('success'=>'0', 'news_data'=>array(),  'message'=>"Empty record.", 'total_record'=>count($total_record));
      }
  }else{
    $responseData = array('success'=>'0', 'news_data'=>array(),  'message'=>"Unauthenticated call.");
  }
  $categoryResponse = json_encode($responseData);

  return $categoryResponse;
}




}
