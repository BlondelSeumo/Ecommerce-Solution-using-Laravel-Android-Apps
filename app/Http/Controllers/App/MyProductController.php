<?php

namespace App\Http\Controllers\App;

//validator is builtin class in laravel
use Validator;
use DB;
use DateTime;
use Hash;
use Auth;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\AppModels\Product;
use Carbon;

class MyProductController extends Controller
{

	//get allcategories
	public function allcategories(Request $request){
    $categoryResponse = Product::allcategories($request);
		print $categoryResponse;
	}

	//getallproducts
	public function getallproducts(Request $request){
    $categoryResponse = Product::getallproducts($request);
		print $categoryResponse;
	}

	// likeproduct
	public function likeproduct(Request $request){
    $categoryResponse = Product::likeproduct($request);
		print $categoryResponse;
	}

	// likeProduct
	public function unlikeproduct(Request $request){
    $categoryResponse = Product::unlikeproduct($request);
		print $categoryResponse;
	}

	//getfilters
	public function getfilters(Request $request){
    $categoryResponse = Product::getfilters($request);
		print $categoryResponse;
		}

	//getfilterproducts
	public function getfilterproducts(Request $request){
      $categoryResponse = Product::getfilterproducts($request);
			print $categoryResponse;
		}

	//getsearchdata
	public function getsearchdata(Request $request){
    $categoryResponse = Product::getsearchdata($request);
		print $categoryResponse;
	}

	//getquantity
	public function getquantity(Request $request){
    $response = Product::getquantity($request);
		print $response;
	}

	//shippingMethods
	public function shppingbyweight(Request $request){
    $categoryResponse = Product::shppingbyweight($request);
		print $categoryResponse;

	}

}
