<?php
namespace App\Http\Controllers\App;

use Illuminate\Foundation\Auth\ThrottlesLogins;
use Validator;
use Mail;
use DateTime;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon;
use App\Models\AppModels\Reviews;

class ReviewsController extends Controller
{

	public function givereview(Request $request){
    $userResponse = Reviews::givereview($request);
		print $userResponse;
	}


	public function updatereview(Request $request){
    $userResponse = Reviews::updatereview($request);
		print $userResponse;
	}

	public function getreviews(Request $request){
    $userResponse = Reviews::getreviews($request);
		print $userResponse;
	}


}
