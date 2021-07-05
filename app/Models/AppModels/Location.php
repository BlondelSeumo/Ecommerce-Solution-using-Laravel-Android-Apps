<?php

namespace App\Models\AppModels;

use App\Http\Controllers\App\AppSettingController;
use DB;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{

    public static function addshippingaddress($request)
    {

        $user_id = $request->customers_id;
        $entry_firstname = $request->entry_firstname;
        $entry_lastname = $request->entry_lastname;
        $entry_street_address = $request->entry_street_address;
        $entry_suburb = $request->entry_suburb;
        $entry_postcode = $request->entry_postcode;
        $entry_city = $request->entry_city;
        $entry_state = $request->entry_zone_id;
        $entry_country_id = $request->entry_country_id;
        $entry_zone_id = $request->entry_zone_id;
        $entry_gender = 1;
        $entry_company = $request->entry_company;
        $is_default = $request->is_default;
        $entry_latitude = $request->entry_latitude;
        $entry_longitude = $request->entry_longitude;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {

            if (!empty($user_id)) {

                $address_book_data = array(
                    'entry_firstname' => $entry_firstname,
                    'entry_lastname' => $entry_lastname,
                    'entry_street_address' => $entry_street_address,
                    'entry_suburb' => $entry_suburb,
                    'entry_postcode' => $entry_postcode,
                    'entry_city' => $entry_city,
                    'entry_state' => $entry_state,
                    'entry_country_id' => $entry_country_id,
                    'entry_zone_id' => $entry_zone_id,
                    'user_id' =>   $user_id,
                    'entry_gender' => $entry_gender,
                    'entry_company' => $entry_company,
                    'entry_latitude' => $entry_latitude,
                    'entry_longitude' => $entry_longitude,
                );

                $address_book_id = DB::table('address_book')->insertGetId($address_book_data);

                if ($is_default == '1') {
                    DB::table('user_to_address')->where('user_id', $user_id)
                        ->update(['is_default' => 0]);
                }

                DB::table('user_to_address')->insertGetId(['user_id' => $user_id, 'address_book_id' => $address_book_id, 'is_default' => $is_default]);

            }
            $responseData = array('success' => '1', 'data' => array(), 'message' => "Shipping address has been added successfully!");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $shippingResponse = json_encode($responseData);

        return $shippingResponse;
    }

    public static function updateshippingaddress($request)
    {

        $user_id = $request->customers_id;
        $address_book_id = $request->address_id;
        $entry_firstname = $request->entry_firstname;
        $entry_lastname = $request->entry_lastname;
        $entry_street_address = $request->entry_street_address;
        $entry_suburb = $request->entry_suburb;
        $entry_postcode = $request->entry_postcode;
        $entry_city = $request->entry_city;
        $entry_state = $request->entry_zone_id;
        $entry_country_id = $request->entry_country_id;
        $entry_zone_id = $request->entry_zone_id;
        $entry_gender = 1;
        $entry_company = $request->entry_company;
        $is_default = $request->is_default;
        $entry_latitude = $request->entry_latitude;
        $entry_longitude = $request->entry_longitude;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            if (!empty($user_id)) {

                $address_book_data = array(
                    'entry_firstname' => $entry_firstname,
                    'entry_lastname' => $entry_lastname,
                    'entry_street_address' => $entry_street_address,
                    'entry_suburb' => $entry_suburb,
                    'entry_postcode' => $entry_postcode,
                    'entry_city' => $entry_city,
                    'entry_state' => $entry_state,
                    'entry_country_id' => $entry_country_id,
                    'entry_zone_id' => $entry_zone_id,

                    'entry_gender' => $entry_gender,
                    'entry_company' => $entry_company,
                    'entry_latitude' => $entry_latitude,
                    'entry_longitude' => $entry_longitude,
                );

                //add address into address book
                DB::table('address_book')->where('address_book_id', $address_book_id)->update($address_book_data);

                if ($is_default == '1') {
                    DB::table('user_to_address')->where('user_id', $user_id)
                        ->update(['is_default' => 0]);

                    DB::table('user_to_address')->where('user_id', $user_id)->where('address_book_id', $address_book_id)->update(['is_default' => $is_default]);
                }
            }
            $responseData = array('success' => '1', 'data' => array(), 'message' => "Shipping address has been updated successfully!");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $shippingResponse = json_encode($responseData);

        return $shippingResponse;
    }

    public static function deleteshippingaddress($request)
    {
        $user_id = $request->customers_id;
        $address_book_id = $request->address_book_id;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {

            if (!empty($user_id)) {
                DB::table('address_book')->where('address_book_id', $address_book_id)->delete();
                DB::table('user_to_address')->where('address_book_id', $address_book_id)->delete();
            }

            $responseData = array('success' => '1', 'data' => array(), 'message' => "Shipping address has been deleted successfully!");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $shippingResponse = json_encode($responseData);

        return $shippingResponse;
    }

    public static function getalladdress($request)
    {
        $user_id = $request->customers_id;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {

            $addresses = DB::table('user_to_address')
                ->leftjoin('address_book', 'address_book.address_book_id', '=', 'user_to_address.address_book_id')
                ->leftJoin('countries', 'countries.countries_id', '=', 'address_book.entry_country_id')
                ->leftJoin('zones', 'zones.zone_id', '=', 'address_book.entry_zone_id')
                ->select(
                    'user_to_address.is_default as default_address',
                    'address_book.address_book_id as address_id',
                    'address_book.entry_gender as gender',
                    'address_book.entry_company as company',
                    'address_book.entry_firstname as firstname',
                    'address_book.entry_lastname as lastname',
                    'address_book.entry_street_address as street',
                    'address_book.entry_suburb as suburb',
                    'address_book.entry_postcode as postcode',
                    'address_book.entry_city as city',
                    'address_book.entry_state as state',
                    'address_book.entry_latitude as latitude',
                    'address_book.entry_longitude as longitude',

                    'countries.countries_id as countries_id',
                    'countries.countries_name as country_name',

                    'zones.zone_id as zone_id',
                    'zones.zone_code as zone_code',
                    'zones.zone_name as zone_name'
                )
                ->where('user_to_address.user_id', $user_id)->get();

            if (count($addresses) > 0) {
                $addresses_data = $addresses;
                $responseData = array('success' => '1', 'data' => $addresses_data, 'message' => "Return shipping addresses successfully");
            } else {
                $addresses_data = array();
                $responseData = array('success' => '0', 'data' => $addresses_data, 'message' => "Addresses are not added yet.");
            }
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $shippingResponse = json_encode($responseData);

        return $shippingResponse;
    }

    public static function updatedefaultaddress($request)
    {
        $user_id = $request->customers_id;
        $address_book_id = $request->address_book_id;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            DB::table('user_to_address')->where('user_id', $user_id)
                ->update(['is_default' => 0]);

			DB::table('user_to_address')
				->where('user_id', $user_id)
				->where('address_book_id', $address_book_id)
                ->update(['is_default' => 1]);

            $responseData = array('success' => '1', 'data' => array(), 'message' => "Default address has been changed successfully!");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $response = json_encode($responseData);
        return $response;
    }

    public static function getTaxRate($request)
    {

        $tax_zone_id = $request->tax_zone_id;
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {

            $index = '0';
            $total_tax = '0';
            foreach ($request->products as $products_data) {
                $final_price = $request->products[$index]['final_price'];
                $products = DB::table('products')
                    ->LeftJoin('tax_rates', 'tax_rates.tax_class_id', '=', 'products.products_tax_class_id')
                    ->where('tax_rates.tax_zone_id', $tax_zone_id)
                    ->where('products_id', $products_data['products_id'])->get();

                $tax_value = $products[0]->tax_rate / 100 * $final_price;
                $total_tax = $total_tax + $tax_value;
                $index++;
            }
            if ($total_tax > 0) {
                $rate = $total_tax;
            } else {
                $rate = '0';
            }

            $responseData = array('success' => '1', 'rate' => $rate, 'message' => "Tax rate is returned!");
        } else {
            $responseData = array('success' => '0', 'rate' => array(), 'message' => "Unauthenticated call.");
        }

        $response = json_encode($responseData);
        return $response;
    }

    public static function countries($request)
    {
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {

            $countries = DB::table('countries')->get();

            $responseData = array('success' => '1', 'data' => $countries, 'message' => "Returned all countries.");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $response = json_encode($responseData);
        return $response;
    }

    public static function zones($request)
    {
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer-device-id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;
        $authController = new AppSettingController();
        $authenticate = $authController->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $zone_country_id = $request->zone_country_id;
            $zones = DB::table('zones')->where('zone_country_id', $zone_country_id)->get();

            $responseData = array('success' => '1', 'data' => $zones, 'message' => "Returned all states.");
        } else {
            $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
        }
        $response = json_encode($responseData);
        return $response;
    }
}
