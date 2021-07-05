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
use Carbon;
use App\Models\AppModels\News;


class NewsController extends Controller
{

	//allnewscategories
	public function allnewscategories(Request $request){
    $categoryResponse = News::allnewscategories($request);
		print $categoryResponse;
	}


	//getallnews
	public function getallnews(Request $request){
    $categoryResponse = News::getallnews($request);
		print $categoryResponse;
	}

}
