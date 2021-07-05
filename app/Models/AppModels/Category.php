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

class Category extends Model
{

 public static function getMainCategories($language_id)
{
  $getCategories = DB::table('categories')
  ->leftJoin('categories_description','categories_description.categories_id', '=', 'categories.categories_id')
  ->select('categories.categories_id as id', 'categories.categories_image as image',  'categories.date_added as date_added', 'categories.last_modified as last_modified', 'categories_description.categories_name as name')
  ->where('parent_id', '0')->where('categories_description.language_id', $language_id)->get();
  return($getCategories) ;
}

public static function getCategories($request)
{
  $language_id 	 = '1';

  if(empty($request->category_id)){
    $category_id	= '0';
  }else{
    $category_id	=   $request->category_id;
  }

  $getCategories = DB::table('categories')
  ->leftJoin('categories_description','categories_description.categories_id', '=', 'categories.categories_id')
  ->select('categories.categories_id as id', 'categories.categories_image as image',  'categories.date_added as date_added', 'categories.last_modified as last_modified', 'categories_description.categories_name as name')
  ->where('parent_id', $category_id)->where('categories_description.language_id', $language_id)->get();
  return($getCategories) ;
}


}
