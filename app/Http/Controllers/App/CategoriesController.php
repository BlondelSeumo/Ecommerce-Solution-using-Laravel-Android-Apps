<?php
namespace App\Http\Controllers\App;

use Validator;
use DB;
use Hash;
use App\Administrator;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\AppModels\Category;


class CategoriesController extends Controller
{
	public function getMainCategories($language_id){
		$getCategories = Category::getMainCategories($language_id);
		return($getCategories) ;
	}

	public function getCategories(Request $request){
    $getCategories = Category::getCategories($request);
		return($getCategories) ;
	}

}
