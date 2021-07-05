<?php

namespace App\Models\AppModels;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Admin\AdminSiteSettingController;
use App\Http\Controllers\Admin\AdminCategoriesController;
use App\Http\Controllers\Admin\AdminProductsController;
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


class Reviews extends Model
{

 public static function givereview($request){

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
      $products_id = $request->products_id;
      $customers_id = $request->customers_id;
      $customers_name = $request->customers_name;
      $reviews_rating = $request->reviews_rating;

      $languages_id = $request->languages_id;

      if($request->reviews_text){
        $reviews_text = $request->reviews_text;
      }else{
        $reviews_text = '';
      }

      //check already reviewed by this customer for this product
      $reviews = DB::table('reviews')->where(
                      ['products_id'=>$products_id,
                      'customers_id'=>$customers_id,]
                    )->get();

      if(count($reviews)==0){

        $reviews_id = DB::table('reviews')->insertGetId([
                        'products_id'=>$products_id,
                        'customers_id'=>$customers_id,
                        'customers_name'=>$customers_name,
                        'reviews_rating'=>$reviews_rating,
                        'created_at'=>date('Y-m-d H:i:s'),
                        'reviews_status'=>0,
                        'reviews_read'=>0
                      ]);

          DB::table('reviews_description')->insertGetId([
                          'reviews_text'=>$reviews_text,
                          'language_id'=>$languages_id,
                          'review_id'=>$reviews_id
                        ]);

          $responseData = array('success'=>'1', 'data'=>array(), 'message'=>'Product is reviewed successfully!');

        }else{
          $responseData = array('success'=>'1', 'data'=>array(), 'message'=>'You have already given the review for this product.');
        }

  }else{
    $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
  }
  $userResponse = json_encode($responseData);

  return $userResponse;
  }


  public static function updatereview($request){

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

       $products_id = $request->products_id;
       $customers_id = $request->customers_id;
       $customers_name = $request->customers_name;
       $reviews_rating = $request->reviews_rating;

       $languages_id = $request->languages_id;

       if($request->reviews_text){
         $reviews_text = $request->reviews_text;
       }else{
         $reviews_text = '';
       }

       //check already reviewed by this customer for this product
       $reviews = DB::table('reviews')->where(
                       ['products_id'=>$products_id,
                       'customers_id'=>$customers_id,]
                     )->first();

       if($reviews){

         $reviews_id = DB::table('reviews')
                        ->where('products_id', $products_id)
                        ->where('customers_id', $customers_id)
                        ->update([
                           'reviews_rating'=>$reviews_rating,
                           'updated_at'=>date('Y-m-d H:i:s'),
                           'reviews_read'=>0
                         ]);

           $reviews_id = DB::table('reviews_description')
                         ->where('review_id',$request->reviews_id)
                         ->where('language_id',$languages_id)
                         ->update([
                           'reviews_text'=>$reviews_text
                         ]);

           $responseData = array('success'=>'1', 'data'=>array(), 'message'=>'Your review has been updated successfully!');

         }else{
           $responseData = array('success'=>'1', 'data'=>array(), 'message'=>'You dont have any review for this product.');
         }

   }else{
     $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
   }
   $userResponse = json_encode($responseData);

   return $userResponse;
   }


   public static function getreviews($request){

    $consumer_data 		 				          =  array();
    $consumer_data['consumer_key'] 	 	  =  request()->header('consumer-key');
    $consumer_data['consumer_secret']	  =  request()->header('consumer-secret');
    $consumer_data['consumer_nonce']	  =  request()->header('consumer-nonce');
    $consumer_data['consumer_device_id']  =  request()->header('consumer-device-id');
    $consumer_data['consumer_ip']  =  request()->header('consumer-ip');
    $consumer_data['consumer_url']  	  =  __FUNCTION__;
    $authController = new AppSettingController();
    $authenticate = $authController->apiAuthenticate($consumer_data);

    $results		 				          =  array();

    if($authenticate==1){

        $products_id = $request->products_id;
        $languages_id = $request->languages_id;

        $reviews = DB::table('reviews')
                  ->join('reviews_description','reviews_description.review_id','=','reviews.reviews_id')
                  ->join('users','users.id','=','reviews.customers_id')
                  ->select('reviews.reviews_id', 'reviews.products_id', 'reviews.reviews_rating as rating', 'reviews.created_at', 'reviews_description.reviews_text as comments', 'users.first_name',
                  'users.last_name', 'users.email')
                  ->where('reviews.products_id', $products_id)
                  ->where('reviews.reviews_status', 1)
                  ->where('reviews_description.language_id', $languages_id)->get();

        if($reviews){
            $responseData = array('success'=>'1', 'data'=>$reviews, 'message'=>'Product rating has been returned!');
        }else{
            $responseData = array('success'=>'1', 'data'=>array(), 'message'=>'Product is not rated yet.');
        }

    }else{
      $responseData = array('success'=>'0', 'data'=>array(),  'message'=>"Unauthenticated call.");
    }
    $userResponse = json_encode($responseData);

    return $userResponse;
    }


}
