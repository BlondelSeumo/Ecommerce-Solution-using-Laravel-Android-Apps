<?php

namespace App\Models\AppModels;

use App\Http\Controllers\Admin\AdminSiteSettingController;
use App\Http\Controllers\App\AppSettingController;
use DB;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

  public static function convertprice($current_price, $requested_currency)
  {
    $required_currency = DB::table('currencies')->where('is_current',1)->where('code', $requested_currency)->first();
    $products_price = $current_price * $required_currency->value;
    return $products_price;
  }

  public static function allcategories($request)
  {
      $language_id = $request->language_id;
      $categories_id = $request->categories_id;
      $result = array();
      $data = array();
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

          $item = DB::table('categories')
              ->leftJoin('categories_description', 'categories_description.categories_id', '=', 'categories.categories_id')
              ->LeftJoin('image_categories', function ($join) {
                  $join->on('image_categories.image_id', '=', 'categories.categories_image')
                      ->where(function ($query) {
                          $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                              ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                              ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                      });
              })
              ->LeftJoin('image_categories as icon_categories', function ($join) {
                  $join->on('icon_categories.image_id', '=', 'categories.categories_icon')
                      ->where(function ($query) {
                          $query->where('icon_categories.image_type', '=', 'THUMBNAIL')
                              ->where('icon_categories.image_type', '!=', 'THUMBNAIL')
                              ->orWhere('icon_categories.image_type', '=', 'ACTUAL');
                      });
              })

              ->select('categories.categories_id', 'categories_description.categories_name', 'categories.parent_id',
                  'image_categories.path as image', 'icon_categories.path as icon')
              ->where('categories_description.language_id', '=', $language_id);

          $categories = $item->where('categories_status', '1')
              ->orderby('categories_id', 'ASC')
              ->groupby('categories_id')
              ->get();

          if (count($categories) > 0) {

              $items = array();
              $index = 0;
              foreach ($categories as $category) {
                  array_push($items, $category);
                  $content = DB::table('products_to_categories')
                      ->LeftJoin('products', 'products.products_id', '=', 'products_to_categories.products_id')
                      ->LeftJoin('flash_sale', 'flash_sale.products_id', '=', 'products.products_id')
                      ->whereNotIn('products.products_id', function ($query) {
                          $query->select('flash_sale.products_id')->from('flash_sale');
                      })
                      ->where('products_to_categories.categories_id', $category->categories_id)
                      ->select(DB::raw('COUNT(DISTINCT products.products_id) as total_products'))->first();
                  $items[$index++]->total_products = $content->total_products;
              }

              $responseData = array('success' => '1', 'data' => $items, 'message' => "Returned all categories.", 'categories' => count($categories));
          } else {
              $responseData = array('success' => '0', 'data' => array(), 'message' => "No category found.", 'categories' => array());
          }
      } else {
          $responseData = array('success' => '0', 'data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);
      return $categoryResponse;
  }

  public static function getallproducts($request)
  {
      $language_id = $request->language_id;
      $skip = $request->page_number . '0';
      $currentDate = time();
      $type = $request->type;

      //filter
    //   $minPrice = $request->price['minPrice'];
    //   $maxPrice = $request->price['maxPrice'];
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

          if ($type == "a to z") {
              $sortby = "products_name";
              $order = "ASC";
          } elseif ($type == "z to a") {
              $sortby = "products_name";
              $order = "DESC";
          } elseif ($type == "high to low") {
              $sortby = "products_price";
              $order = "DESC";
          } elseif ($type == "low to high") {
              $sortby = "products_price";
              $order = "ASC";
          } elseif ($type == "top seller") {
              $sortby = "products_ordered";
              $order = "DESC";
          } elseif ($type == "most liked") {
              $sortby = "products_liked";
              $order = "DESC";
          } elseif ($type == "special") { //deals special products
              $sortby = "specials.products_id";
              $order = "desc";
          } elseif ($type == "flashsale") { //flashsale products
              $sortby = "flash_sale.flash_start_date";
              $order = "asc";
          } else {
              $sortby = "products.products_id";
              $order = "desc";
          }

          $filterProducts = array();
          $eliminateRecord = array();
          if (!empty($request->filters)) {

              foreach ($request->filters as $filters_attribute) {

                  $data = DB::table('products_to_categories')
                      ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                      ->join('products', 'products.products_id', '=', 'products_to_categories.products_id')
                      ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                      ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                      ->LeftJoin('specials', function ($join) use ($currentDate) {
                          $join->on('specials.products_id', '=', 'products_to_categories.products_id')
                              ->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                      })

                      ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                      ->leftJoin('products_attributes', 'products_attributes.products_id', '=', 'products.products_id')
                      ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_attributes.options_id')
                      ->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_attributes.options_values_id')

                      ->select('products.*')
                      ->where('products_description.language_id', '=', $language_id)
                      ->whereBetween('products.products_price', [$minPrice, $maxPrice])
                      ->where('categories.parent_id', '!=', '0');

                  if (!empty($request->categories_id)) {
                      $data->where('products_to_categories.categories_id', '=', $request->categories_id);
                  }

                  $getProducts = $data->where('products_options_descriptions.options_name', '=', $filters_attribute['name'])
                      ->where('products_options_values_descriptions.options_values_name', '=', $filters_attribute['value'])
                      ->where('products.products_status', '=', '1')
                      ->skip($skip)->take(10)
                      ->groupBy('products.products_id')
                      ->get();

                  $foundRecord[] = $getProducts;

                  if (count($foundRecord) > 0) {
                      foreach ($getProducts as $getProduct) {
                          if (!in_array($getProduct->products_id, $eliminateRecord)) {
                              $eliminateRecord[] = $getProduct->products_id;

                              $products = DB::table('products_to_categories')
                                  ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                                  ->leftJoin('categories_description', 'categories_description.categories_id', '=', 'products_to_categories.categories_id')
                                  ->leftJoin('products', 'products.products_id', '=', 'products_to_categories.products_id')
                                  ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                                  ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                                  ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                                  ->leftJoin('specials', function ($join) use ($currentDate) {
                                      $join->on('specials.products_id', '=', 'products_to_categories.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                                  })
                                  ->LeftJoin('image_categories', function ($join) {
                                      $join->on('image_categories.image_id', '=', 'products.products_image')
                                          ->where(function ($query) {
                                              $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                                  ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                                  ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                                          });
                                  })

                                  ->select('products_to_categories.*', 'products.*', 'image_categories.path as products_image', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price', 'products_to_categories.categories_id', 'categories_description.*')
                                  ->where('categories_description.language_id', '=', $language_id)
                                  ->where('products_description.language_id', '=', $language_id)
                                  ->where('products.products_id', '=', $getProduct->products_id)
                                  ->where('categories.parent_id', '!=', '0')
                                  ->get();
                              $result = array();
                              $index = 0;
                              foreach ($products as $products_data) {
                                //check currency start
                                $requested_currency = $request->currency_code;
                                $current_price = $products_data->products_price;


                                  $products_price = Product::convertprice($current_price, $requested_currency);

                                  ////////// for discount price    /////////
                                  if(!empty($products_data->discount_price)){
                                    $discount_price = Product::convertprice($products_data->discount_price, $requested_currency);
                                    $products_data->discount_price = $discount_price;
                                  }


                                $products_data->products_price = $products_price;
                                $products_data->currency = $requested_currency;
                                //check currency end
                                  $products_id = $products_data->products_id;

                                  //multiple images
                                  $products_images = DB::table('products_images')
                                      ->LeftJoin('image_categories', function ($join) {
                                          $join->on('image_categories.image_id', '=', 'products_images.image')
                                              ->where(function ($query) {
                                                  $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                                      ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                                      ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                                              });
                                      })
                                      ->select('products_images.*', 'image_categories.path as image')
                                      ->where('products_id', '=', $products_id)->orderBy('sort_order', 'ASC')->get();

                                  $categories = DB::table('products_to_categories')
                                      ->leftjoin('categories', 'categories.categories_id', 'products_to_categories.categories_id')
                                      ->leftjoin('categories_description', 'categories_description.categories_id', 'products_to_categories.categories_id')
                                      ->select('categories.categories_id', 'categories_description.categories_name', 'categories.categories_image', 'categories.categories_icon', 'categories.parent_id')
                                      ->where('products_id', '=', $products_id)
                                      ->where('categories_description.language_id', '=', $language_id)->get();

                                  $products_data->categories = $categories;

                                  $reviews = DB::table('reviews')
                                      ->join('users', 'users.id', '=', 'reviews.customers_id')
                                      ->select('reviews.*', 'users.avatar as image')
                                      ->where('products_id', $products_data->products_id)
                                      ->where('reviews_status', '1')
                                      ->get();

                                  $avarage_rate = 0;
                                  $total_user_rated = 0;

                                  if (count($reviews) > 0) {

                                      $five_star = 0;
                                      $five_count = 0;

                                      $four_star = 0;
                                      $four_count = 0;

                                      $three_star = 0;
                                      $three_count = 0;

                                      $two_star = 0;
                                      $two_count = 0;

                                      $one_star = 0;
                                      $one_count = 0;

                                      foreach ($reviews as $review) {

                                          //five star ratting
                                          if ($review->reviews_rating == '5') {
                                              $five_star += $review->reviews_rating;
                                              $five_count++;
                                          }

                                          //four star ratting
                                          if ($review->reviews_rating == '4') {
                                              $four_star += $review->reviews_rating;
                                              $four_count++;
                                          }
                                          //three star ratting
                                          if ($review->reviews_rating == '3') {
                                              $three_star += $review->reviews_rating;
                                              $three_count++;
                                          }
                                          //two star ratting
                                          if ($review->reviews_rating == '2') {
                                              $two_star += $review->reviews_rating;
                                              $two_count++;
                                          }

                                          //one star ratting
                                          if ($review->reviews_rating == '1') {
                                              $one_star += $review->reviews_rating;
                                              $one_count++;
                                          }

                                      }

                                      $five_ratio = round($five_count / count($reviews) * 100);
                                      $four_ratio = round($four_count / count($reviews) * 100);
                                      $three_ratio = round($three_count / count($reviews) * 100);
                                      $two_ratio = round($two_count / count($reviews) * 100);
                                      $one_ratio = round($one_count / count($reviews) * 100);

                                      $avarage_rate = (5 * $five_star + 4 * $four_star + 3 * $three_star + 2 * $two_star + 1 * $one_star) / ($five_star + $four_star + $three_star + $two_star + $one_star);
                                      $total_user_rated = count($reviews);
                                      $reviewed_customers = $reviews;
                                  } else {
                                      $reviewed_customers = array();
                                      $avarage_rate = 0;
                                      $total_user_rated = 0;

                                      $five_ratio = 0;
                                      $four_ratio = 0;
                                      $three_ratio = 0;
                                      $two_ratio = 0;
                                      $one_ratio = 0;
                                  }

                                  $products_data->rating = number_format($avarage_rate, 2);
                                  $products_data->total_user_rated = $total_user_rated;

                                  $products_data->five_ratio = $five_ratio;
                                  $products_data->four_ratio = $four_ratio;
                                  $products_data->three_ratio = $three_ratio;
                                  $products_data->two_ratio = $two_ratio;
                                  $products_data->one_ratio = $one_ratio;

                                  //review by users
                                  $products_data->reviewed_customers = $reviewed_customers;

                                  array_push($result, $products_data);

                                  $options = array();
                                  $attr = array();

                                  //like product
                                  if (!empty($request->customers_id)) {
                                      $liked_customers_id = $request->customers_id;
                                      $categories = DB::table('liked_products')->where('liked_products_id', '=', $products_id)->where('liked_customers_id', '=', $liked_customers_id)->get();
                                      if (count($categories) > 0) {
                                          $result[$index]->isLiked = '1';
                                      } else {
                                          $result[$index]->isLiked = '0';
                                      }
                                  } else {
                                      $result[$index]->isLiked = '0';
                                  }

                                  $stocks = 0;
                                  $stockOut = 0;
                                  $defaultStock = 0;
                                  if ($products_data->products_type == '0') {
                                      $stocks = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'in')->sum('stock');
                                      $stockOut = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'out')->sum('stock');
                                      $defaultStock = $stocks - $stockOut;
                                  }

                                  if ($products_data->products_max_stock < $defaultStock && $products_data->products_max_stock > 0) {
                                      $result[$index]->defaultStock = $products_data->products_max_stock;
                                  } else {
                                      $result[$index]->defaultStock = $defaultStock;
                                  }

                                  // fetch all options add join from products_options table for option name
                                  $products_attribute = DB::table('products_attributes')->where('products_id', '=', $products_id)->groupBy('options_id')->get();
                                  if (count($products_attribute) > 0) {
                                      $index2 = 0;
                                      foreach ($products_attribute as $attribute_data) {
                                          $option_name = DB::table('products_options')
                                              ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $attribute_data->options_id)->get();
                                          if (count($option_name) > 0) {
                                              $temp = array();
                                              $temp_option['id'] = $attribute_data->options_id;
                                              $temp_option['name'] = $option_name[0]->products_options_name;
                                              $attr[$index2]['option'] = $temp_option;

                                              // fetch all attributes add join from products_options_values table for option value name
                                              $attributes_value_query = DB::table('products_attributes')->where('products_id', '=', $products_id)->where('options_id', '=', $attribute_data->options_id)->get();
                                              foreach ($attributes_value_query as $products_option_value) {
                                                  $option_value = DB::table('products_options_values')->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name')->where('products_options_values_descriptions.language_id', '=', $language_id)->where('products_options_values.products_options_values_id', '=', $products_option_value->options_values_id)->get();
                                                  $attributes = DB::table('products_attributes')->where([['products_id', '=', $products_id], ['options_id', '=', $attribute_data->options_id], ['options_values_id', '=', $products_option_value->options_values_id]])->get();
                                                  $temp_i['products_attributes_id'] = $attributes[0]->products_attributes_id;
                                                  $temp_i['id'] = $products_option_value->options_values_id;
                                                  $temp_i['value'] = $option_value[0]->products_options_values_name;
                                                  //check currency start
                                                  $current_price = $products_option_value->options_values_price;


                                                  $attribute_price = Product::convertprice($current_price, $requested_currency);


                                                  //check currency end

                                                  $temp_i['price'] = $attribute_price;
                                                  $temp_i['price_prefix'] = $products_option_value->price_prefix;
                                                  array_push($temp, $temp_i);

                                              }
                                              $attr[$index2]['values'] = $temp;
                                              $result[$index]->attributes = $attr;
                                              $index2++;
                                          }
                                      }
                                  } else {
                                      $result[$index]->attributes = array();
                                  }
                                  array_push($filterProducts, $result[$index]);
                                  $index++;
                              }
                          }
                      }
                      $responseData = array('success' => '1', 'product_data' => $filterProducts, 'message' => "Returned all products.", 'total_record' => count($filterProducts));
                  } else {
                      $total_record = array();
                      $responseData = array('success' => '0', 'product_data' => $filterProducts, 'message' => "Search results empty.", 'total_record' => count($total_record));
                  }
              }
          } else {

              $categories = DB::table('products')
                  ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                  ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                  ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id');

              $categories->LeftJoin('image_categories', function ($join) {
                  $join->on('image_categories.image_id', '=', 'products.products_image')
                      ->where(function ($query) {
                          $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                              ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                              ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                      });
              });

              if (!empty($request->categories_id)) {

                  $categories->LeftJoin('products_to_categories', 'products.products_id', '=', 'products_to_categories.products_id')
                      ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                      ->LeftJoin('categories_description', 'categories_description.categories_id', '=', 'products_to_categories.categories_id');
              }

              //wishlist customer id
              if ($type == "wishlist") {
                  $categories->LeftJoin('liked_products', 'liked_products.liked_products_id', '=', 'products.products_id')
                  ->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'image_categories.path as products_image');;
              }

              //parameter special
              elseif ($type == "special") {
                  $categories->LeftJoin('specials', 'specials.products_id', '=', 'products.products_id')
                      ->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price', 'specials.specials_new_products_price as discount_price', 'image_categories.path as products_image');
              } elseif ($type == "flashsale") {
                  //flash sale
                  $categories->
                      LeftJoin('flash_sale', 'flash_sale.products_id', '=', 'products.products_id')
                      ->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'flash_sale.flash_start_date', 'flash_sale.flash_expires_date', 'flash_sale.flash_sale_products_price as flash_price', 'image_categories.path as products_image');

              } else {
                  $categories->LeftJoin('specials', function ($join) use ($currentDate) {
                      $join->on('specials.products_id', '=', 'products.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                  })->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price', 'image_categories.path as products_image');
              }

              if ($type == "special") { //deals special products
                  $categories->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
              }

              if ($type == "flashsale") { //flashsale
                  $categories->where('flash_sale.flash_status', '=', '1')->where('flash_expires_date', '>', $currentDate);
              } 
              else {
                if (!isset($request->products_id)) {
                    $categories->whereNotIn('products.products_id', function ($query) {
                        $query->select('flash_sale.products_id')->from('flash_sale');
                    });
                }else{
                    $isFlash = DB::table('flash_sale')->where('flash_sale.products_id', '=', $request->products_id)->where('flash_sale.flash_status', '=', '1')->where('flash_expires_date', '>', $currentDate)->first();
                    //dd($isFlash);
                    if($isFlash){
                        $categories->LeftJoin('flash_sale', function ($join) use ($currentDate) {
                            $join->on('flash_sale.products_id', '=', 'products.products_id')->where('flash_sale.flash_status', '=', '1')->where('flash_expires_date', '>', $currentDate);
                        })->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'flash_sale.flash_sale_products_price as flash_price', 'image_categories.path as products_image');
                        
                        $type = "flashsale";

                    }
                    
                }
                  
              }

              //get single category products
              if (!empty($request->categories_id)) {
                  $categories->where('products_to_categories.categories_id', '=', $request->categories_id)
                      ->where('categories_description.language_id', '=', $language_id);
              }

              //get single products
              if (!empty($request->products_id) && $request->products_id != "") {
                  $categories->where('products.products_id', '=', $request->products_id);
              }
              

              //for min and maximum price
              if (!empty($maxPrice)) {
                  $categories->whereBetween('products.products_price', [$minPrice, $maxPrice]);
              }

              if ($type == "top seller") {
                $categories->where('products.products_ordered', '>', 0);
              }
                
              if ($type == "most liked") {
                $categories->where('products.products_liked', '>', 0);
              }

              //wishlist customer id
              if ($type == "wishlist") {
                  $categories->where('liked_customers_id', '=', $request->customers_id);
              }

              $categories->where('products_description.language_id', '=', $language_id)
                  ->where('products.products_status', '=', '1')
                  ->orderBy($sortby, $order);

              if ($type == "special") { //deals special products
                  $categories->groupBy('products.products_id');
              }
              //count
              $total_record = $categories->get();

              $data = $categories->skip($skip)->take(10)->get();

              $result = array();
              $result2 = array();
              //check if record exist
              if (count($data) > 0) {
                  $index = 0;
                  foreach ($data as $products_data) {

                        //check currency start
                        $requested_currency = $request->currency_code;
                        $current_price = $products_data->products_price;
                        //dd($current_price, $requested_currency);

                        $products_price = Product::convertprice($current_price, $requested_currency);
                        ////////// for discount price    /////////
                        if(!empty($products_data->discount_price)){
                            $discount_price = Product::convertprice($products_data->discount_price, $requested_currency);
                            $products_data->discount_price = $discount_price;
                        }

                      $products_data->products_price = $products_price;
                      $products_data->currency = $requested_currency;
                      //check currency end



                      //for flashsale currency price start
                      if ($type == "flashsale"){
                        $current_price = $products_data->flash_price;
                        $flash_price = Product::convertprice($current_price, $requested_currency);
                        $products_data->flash_price = $flash_price;
                      }

                      //for flashsale currency price end
                      $products_id = $products_data->products_id;

                      //multiple images
                      $products_images = DB::table('products_images')
                          ->LeftJoin('image_categories', function ($join) {
                              $join->on('image_categories.image_id', '=', 'products_images.image')
                                  ->where(function ($query) {
                                      $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                          ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                          ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                                  });
                          })
                          ->select('products_images.*', 'image_categories.path as image')
                          ->where('products_id', '=', $products_id)->orderBy('sort_order', 'ASC')->get();
                      $products_data->images = $products_images;

                      $categories = DB::table('products_to_categories')
                          ->leftjoin('categories', 'categories.categories_id', 'products_to_categories.categories_id')
                          ->leftjoin('categories_description', 'categories_description.categories_id', 'products_to_categories.categories_id')
                          ->select('categories.categories_id', 'categories_description.categories_name', 'categories.categories_image', 'categories.categories_icon', 'categories.parent_id')
                          ->where('products_id', '=', $products_id)
                          ->where('categories_description.language_id', '=', $language_id)->get();

                      $products_data->categories = $categories;

                      $reviews = DB::table('reviews')
                          ->join('users', 'users.id', '=', 'reviews.customers_id')
                          ->select('reviews.*', 'users.avatar as image')
                          ->where('products_id', $products_data->products_id)
                          ->where('reviews_status', '1')
                          ->get();

                      $avarage_rate = 0;
                      $total_user_rated = 0;

                      if (count($reviews) > 0) {

                          $five_star = 0;
                          $five_count = 0;

                          $four_star = 0;
                          $four_count = 0;

                          $three_star = 0;
                          $three_count = 0;

                          $two_star = 0;
                          $two_count = 0;

                          $one_star = 0;
                          $one_count = 0;

                          foreach ($reviews as $review) {

                              //five star ratting
                              if ($review->reviews_rating == '5') {
                                  $five_star += $review->reviews_rating;
                                  $five_count++;
                              }

                              //four star ratting
                              if ($review->reviews_rating == '4') {
                                  $four_star += $review->reviews_rating;
                                  $four_count++;
                              }
                              //three star ratting
                              if ($review->reviews_rating == '3') {
                                  $three_star += $review->reviews_rating;
                                  $three_count++;
                              }
                              //two star ratting
                              if ($review->reviews_rating == '2') {
                                  $two_star += $review->reviews_rating;
                                  $two_count++;
                              }

                              //one star ratting
                              if ($review->reviews_rating == '1') {
                                  $one_star += $review->reviews_rating;
                                  $one_count++;
                              }

                          }

                          $five_ratio = round($five_count / count($reviews) * 100);
                          $four_ratio = round($four_count / count($reviews) * 100);
                          $three_ratio = round($three_count / count($reviews) * 100);
                          $two_ratio = round($two_count / count($reviews) * 100);
                          $one_ratio = round($one_count / count($reviews) * 100);
                          if(($five_star + $four_star + $three_star + $two_star + $one_star) > 0){
                            $avarage_rate = (5 * $five_star + 4 * $four_star + 3 * $three_star + 2 * $two_star + 1 * $one_star) / ($five_star + $four_star + $three_star + $two_star + $one_star);
                          }else{
                            $avarage_rate = 0;
                          }
                          $total_user_rated = count($reviews);
                          $reviewed_customers = $reviews;
                      } else {
                          $reviewed_customers = array();
                          $avarage_rate = 0;
                          $total_user_rated = 0;

                          $five_ratio = 0;
                          $four_ratio = 0;
                          $three_ratio = 0;
                          $two_ratio = 0;
                          $one_ratio = 0;
                      }

                      $products_data->rating = number_format($avarage_rate, 2);
                      $products_data->total_user_rated = $total_user_rated;

                      $products_data->five_ratio = $five_ratio;
                      $products_data->four_ratio = $four_ratio;
                      $products_data->three_ratio = $three_ratio;
                      $products_data->two_ratio = $two_ratio;
                      $products_data->one_ratio = $one_ratio;

                      //review by users
                      $products_data->reviewed_customers = $reviewed_customers;

                      array_push($result, $products_data);
                      $options = array();
                      $attr = array();

                      $stocks = 0;
                      $stockOut = 0;
                      $defaultStock = 0;
                      if ($products_data->products_type == '0') {
                          $stocks = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'in')->sum('stock');
                          $stockOut = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'out')->sum('stock');
                          $defaultStock = $stocks - $stockOut;
                      }

                      if ($products_data->products_max_stock < $defaultStock && $products_data->products_max_stock > 0) {
                          $result[$index]->defaultStock = $products_data->products_max_stock;
                      } else {
                          $result[$index]->defaultStock = $defaultStock;
                      }

                      //like product
                      if (!empty($request->customers_id)) {
                          $liked_customers_id = $request->customers_id;
                          $categories = DB::table('liked_products')->where('liked_products_id', '=', $products_id)->where('liked_customers_id', '=', $liked_customers_id)->get();
                          if (count($categories) > 0) {
                              $result[$index]->isLiked = '1';
                          } else {
                              $result[$index]->isLiked = '0';
                          }
                      } else {
                          $result[$index]->isLiked = '0';
                      }

                      // fetch all options add join from products_options table for option name
                      $products_attribute = DB::table('products_attributes')->where('products_id', '=', $products_id)->groupBy('options_id')->get();
                      if (count($products_attribute) > 0) {
                          $index2 = 0;
                          foreach ($products_attribute as $attribute_data) {
                              $option_name = DB::table('products_options')
                                  ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $attribute_data->options_id)->get();
                              if (count($option_name) > 0) {
                                  $temp = array();
                                  $temp_option['id'] = $attribute_data->options_id;
                                  $temp_option['name'] = $option_name[0]->products_options_name;
                                  $attr[$index2]['option'] = $temp_option;

                                  // fetch all attributes add join from products_options_values table for option value name
                                  $attributes_value_query = DB::table('products_attributes')->where('products_id', '=', $products_id)->where('options_id', '=', $attribute_data->options_id)->get();
                                  foreach ($attributes_value_query as $products_option_value) {

                                      //$option_value = DB::table('products_options_values')->leftJoin('products_options_values_descriptions','products_options_values_descriptions.products_options_values_id','=','products_options_values.products_options_values_id')->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name' )->where('products_options_values_descriptions.language_id','=', $language_id)->where('products_options_values.products_options_values_id','=', $products_option_value->options_values_id)->get();
                                      $option_value = DB::table('products_options_values')->where('products_options_values_id', '=', $products_option_value->options_values_id)->get();

                                      $attributes = DB::table('products_attributes')->where([['products_id', '=', $products_id], ['options_id', '=', $attribute_data->options_id], ['options_values_id', '=', $products_option_value->options_values_id]])->get();
                                      $temp_i['products_attributes_id'] = $attributes[0]->products_attributes_id;
                                      $temp_i['id'] = $products_option_value->options_values_id;

                                      if (!empty($option_value[0]->products_options_values_name)) {
                                          $temp_i['value'] = $option_value[0]->products_options_values_name;
                                      } else {
                                          $temp_i['value'] = '';
                                      }

                                      //check currency start
                                      $current_price = $products_option_value->options_values_price;

                                      $attribute_price = Product::convertprice($current_price, $requested_currency);


                                      //check currency end

                                      //$temp_i['price'] = $products_option_value->options_values_price;
                                      $temp_i['price'] = $attribute_price;
                                      $temp_i['price_prefix'] = $products_option_value->price_prefix;
                                      array_push($temp, $temp_i);

                                  }
                                  $attr[$index2]['values'] = $temp;
                                  $result[$index]->attributes = $attr;
                                  $index2++;
                              }
                          }
                      } else {
                          $result[$index]->attributes = array();
                      }
                      $index++;
                  }

                  $responseData = array('success' => '1', 'product_data' => $result, 'message' => "Returned all products.", 'total_record' => count($total_record));
              } else {
                  $responseData = array('success' => '0', 'product_data' => $result, 'message' => "Empty record.", 'total_record' => count($total_record));
              }
          }
      } else {
          $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

  public static function likeproduct($request)
  {
      $liked_products_id = $request->liked_products_id;
      $liked_customers_id = $request->liked_customers_id;
      $date_liked = date('Y-m-d H:i:s');
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

          //to avoide duplicate record
          DB::table('liked_products')->where([
              'liked_products_id' => $liked_products_id,
              'liked_customers_id' => $liked_customers_id,
          ])->delete();

          DB::table('liked_products')->insert([
              'liked_products_id' => $liked_products_id,
              'liked_customers_id' => $liked_customers_id,
              'date_liked' => $date_liked,
          ]);

          $response = DB::table('liked_products')->select('liked_products_id')->where('liked_customers_id', '=', $liked_customers_id)->get();
          DB::table('products')->where('products_id', '=', $liked_products_id)->increment('products_liked');

          $responseData = array('success' => '1', 'product_data' => $response, 'message' => "Product is liked.");

      } else {
          $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

  public static function unlikeproduct($request)
  {
      $liked_products_id = $request->liked_products_id;
      $liked_customers_id = $request->liked_customers_id;
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
          DB::table('liked_products')->where([
              'liked_products_id' => $liked_products_id,
              'liked_customers_id' => $liked_customers_id,
          ])->delete();

          DB::table('products')->where('products_id', '=', $liked_products_id)->decrement('products_liked');

          $response = DB::table('liked_products')->select('liked_products_id')->where('liked_customers_id', '=', $liked_customers_id)->get();
          $responseData = array('success' => '1', 'product_data' => $response, 'message' => "Product is unliked.");
      } else {
          $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

  public static function getfilters($request)
  {
      $language_id = $request->language_id;
      $categories_id = $request->categories_id;
      $currentDate = time();
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

          $price = DB::table('products_to_categories')
              ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
              ->join('products', 'products.products_id', '=', 'products_to_categories.products_id');
          if (isset($categories_id) and !empty($categories_id)) {
              $price->where('products_to_categories.categories_id', '=', $categories_id);
          }
        //   $price->where('categories.parent_id', '!=', '0');

          $priceContent = $price->max('products_price');

          if (!empty($priceContent)) {
              $maxPrice = $priceContent;
          } else {
              $maxPrice = '';
          }

          $product = DB::table('products')
              ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
              ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
              ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
              ->LeftJoin('specials', function ($join) use ($currentDate) {
                  $join->on('specials.products_id', '=', 'products.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
              });

          if (isset($categories_id) and !empty($categories_id)) {
              $product->LeftJoin('products_to_categories', 'products.products_id', '=', 'products_to_categories.products_id')->select('products_to_categories.*', 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price');
          } else {
              $product->select('products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price');
          }
          $product->where('products_description.language_id', '=', $language_id);

          if (isset($categories_id) and !empty($categories_id)) {
              $product->where('products_to_categories.categories_id', '=', $categories_id);
          }

          $products = $product->get();

          $index = 0;
          $optionsIdArray = array();
          $valueIdArray = array();
          foreach ($products as $products_data) {
              $option_name = DB::table('products_attributes')->where('products_id', '=', $products_data->products_id)->get();
              foreach ($option_name as $option_data) {

                  if (!in_array($option_data->options_id, $optionsIdArray)) {
                      $optionsIdArray[] = $option_data->options_id;
                  }

                  if (!in_array($option_data->options_values_id, $valueIdArray)) {
                      $valueIdArray[] = $option_data->options_values_id;
                  }
              }
          }

          if (!empty($optionsIdArray)) {

              $index3 = 0;
              $result = array();
              foreach ($optionsIdArray as $optionsIdArray) {
                  $option_name = DB::table('products_options')
                      ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $optionsIdArray)->get();
                  if (count($option_name) > 0) {
                      $attribute_opt_val = DB::table('products_options_values')->where('products_options_id', $optionsIdArray)->get();
                      if (count($attribute_opt_val) > 0) {
                          $temp = array();
                          $temp_name['name'] = $option_name[0]->products_options_name;
                          $attr[$index3]['option'] = $temp_name;

                          foreach ($attribute_opt_val as $attribute_opt_val_data) {

                              $attribute_value = DB::table('products_options_values')
                                  ->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')
                                  ->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name', 'products_options_values_descriptions.language_id')
                                  ->where('products_options_values.products_options_values_id', $attribute_opt_val_data->products_options_values_id)->where('language_id', $language_id)->get();

                              foreach ($attribute_value as $attribute_value_data) {

                                  if (in_array($attribute_value_data->products_options_values_id, $valueIdArray)) {
                                      $temp_value['value'] = $attribute_value_data->products_options_values_name;
                                      $temp_value['value_id'] = $attribute_value_data->products_options_values_id;
                                      array_push($temp, $temp_value);
                                  }
                              }
                              $attr[$index3]['values'] = $temp;
                          }
                          $index3++;
                      }

                      $responseData = array('success' => '1', 'filters' => $attr, 'message' => "Returned all filters successfully.", 'maxPrice' => $maxPrice);
                  }
              }

          } else {
              $responseData = array('success' => '1', 'filters' => array(), 'message' => "Filter is empty for this category.", 'maxPrice' => $maxPrice);
          }
      } else {
          $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

  public static function getfilterproducts($request)
  {
      $language_id = '1';
      $skip = $request->page_number . '0';
      $categories_id = $request->categories_id;
      $minPrice = $request->price['minPrice'];
      $maxPrice = $request->price['maxPrice'];
      $currentDate = time();

      $filterProducts = array();
      $eliminateRecord = array();
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
          if (!empty($request->filters)) {

              foreach ($request->filters as $filters_attribute) {
                  //print_r($filters_attribute);

                  $getProducts = DB::table('products_to_categories')
                      ->join('products', 'products.products_id', '=', 'products_to_categories.products_id')
                      ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                      ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                      ->LeftJoin('specials', function ($join) use ($currentDate) {
                          $join->on('specials.products_id', '=', 'products_to_categories.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                      })

                      ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                      ->leftJoin('products_attributes', 'products_attributes.products_id', '=', 'products.products_id')
                      ->leftJoin('products_options', 'products_options.products_options_id', '=', 'products_attributes.options_id')
                      ->leftJoin('products_options_values', 'products_options_values.products_options_values_id', '=', 'products_attributes.options_values_id')

                      ->select('products.*')
                  //->where('products_description.language_id','=', $language_id)
                  //->where('manufacturers_info.languages_id','=', $language_id)
                      ->whereBetween('products.products_price', [$minPrice, $maxPrice])
                      ->where('products_to_categories.categories_id', '=', $categories_id)
                      ->where('products_options.products_options_name', '=', $filters_attribute['name'])
                      ->where('products_options_values.products_options_values_name', '=', $filters_attribute['value'])
                      ->where('categories.parent_id', '!=', '0')
                      ->skip($skip)->take(10)
                      ->groupBy('products.products_id')
                      ->get();

                  if (count($getProducts) > 0) {
                      foreach ($getProducts as $getProduct) {
                          if (!in_array($getProduct->products_id, $eliminateRecord)) {
                              $eliminateRecord[] = $getProduct->products_id;

                              $products = DB::table('products_to_categories')
                                  ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                                  ->join('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                                  ->leftJoin('categories_description', 'categories_description.categories_id', '=', 'products_to_categories.categories_id')
                                  ->leftJoin('products', 'products.products_id', '=', 'products_to_categories.products_id')
                                  ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                                  ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                                  ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                                  ->LeftJoin('specials', function ($join) use ($currentDate) {
                                      $join->on('specials.products_id', '=', 'products_to_categories.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                                  })
                                  ->select('products_to_categories.*', 'categories_description.categories_name', 'categories.*', 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price')
                                  ->where('products.products_id', '=', $getProduct->products_id)
                                  ->where('categories.parent_id', '!=', '0')
                                  ->get();

                              $result = array();
                              $index = 0;
                              foreach ($products as $products_data) {
                                  //check currency start
                                  $requested_currency = $request->currency_code;
                                  $current_price = $products_data->products_price;

                                  if($current_currency == $request->currency){
                                    $products_price = $current_price;
                                  }else{
                                    $products_price = Product::convertprice($current_price,  $requested_currency);
                                    ////////// for discount price    /////////
                                    if(!empty($products_data->discount_price)){
                                      $discount_price = Product::convertprice($products_data->discount_price, $requested_currency);
                                      $products_data->discount_price = $discount_price;
                                    }
                                  }

                                  $products_data->products_price = $products_price;
                                  $products_data->currency = $requested_currency;
                                  //check currency end

                                  $products_id = $products_data->products_id;

                                  $detail = DB::table('products_description')->where('products_id', '=', $products_id)->get();
                                  $index3 = 0;
                                  foreach ($detail as $detail_data) {

                                      //get function from other controller
                                      $myVar = new AdminSiteSettingController();
                                      $languages = $myVar->getSingleLanguages($detail_data->language_id);

                                      $result2[$languages[$index3]->code] = $detail_data;
                                      $index3++;
                                  }
                                  //multiple images
                                  $products_images = DB::table('products_images')
                                      ->LeftJoin('image_categories', function ($join) {
                                          $join->on('image_categories.image_id', '=', 'products_images.image')
                                              ->where(function ($query) {
                                                  $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                                      ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                                      ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                                              });
                                      })
                                      ->select('products_images.*', 'image_categories.path as image')
                                      ->where('products_id', '=', $products_id)->orderBy('sort_order', 'ASC')->get();

                                  $categories = DB::table('products_to_categories')
                                      ->leftjoin('categories', 'categories.categories_id', 'products_to_categories.categories_id')
                                      ->leftjoin('categories_description', 'categories_description.categories_id', 'products_to_categories.categories_id')
                                      ->select('categories.categories_id', 'categories_description.categories_name', 'categories.categories_image', 'categories.categories_icon', 'categories.parent_id')
                                      ->where('products_id', '=', $products_id)
                                      ->where('categories_description.language_id', '=', $language_id)->get();

                                  $products_data->categories = $categories;
                                  $reviews = DB::table('reviews')
                                      ->join('users', 'users.id', '=', 'reviews.customers_id')
                                      ->where('products_id', $products_data->products_id)
                                      ->where('reviews_status', '1')
                                      ->get();

                                  $avarage_rate = 0;
                                  $total_user_rated = 0;

                                  if (count($reviews) > 0) {

                                      $five_star = 0;
                                      $five_count = 0;

                                      $four_star = 0;
                                      $four_count = 0;

                                      $three_star = 0;
                                      $three_count = 0;

                                      $two_star = 0;
                                      $two_count = 0;

                                      $one_star = 0;
                                      $one_count = 0;

                                      foreach ($reviews as $review) {

                                          //five star ratting
                                          if ($review->reviews_rating == '5') {
                                              $five_star += $review->reviews_rating;
                                              $five_count++;
                                          }

                                          //four star ratting
                                          if ($review->reviews_rating == '4') {
                                              $four_star += $review->reviews_rating;
                                              $four_count++;
                                          }
                                          //three star ratting
                                          if ($review->reviews_rating == '3') {
                                              $three_star += $review->reviews_rating;
                                              $three_count++;
                                          }
                                          //two star ratting
                                          if ($review->reviews_rating == '2') {
                                              $two_star += $review->reviews_rating;
                                              $two_count++;
                                          }

                                          //one star ratting
                                          if ($review->reviews_rating == '1') {
                                              $one_star += $review->reviews_rating;
                                              $one_count++;
                                          }

                                      }

                                      $five_ratio = round($five_count / count($reviews) * 100);
                                      $four_ratio = round($four_count / count($reviews) * 100);
                                      $three_ratio = round($three_count / count($reviews) * 100);
                                      $two_ratio = round($two_count / count($reviews) * 100);
                                      $one_ratio = round($one_count / count($reviews) * 100);

                                      $avarage_rate = (5 * $five_star + 4 * $four_star + 3 * $three_star + 2 * $two_star + 1 * $one_star) / ($five_star + $four_star + $three_star + $two_star + $one_star);
                                      $total_user_rated = count($reviews);
                                      $reviewed_customers = $reviews;
                                  } else {
                                      $reviewed_customers = array();
                                      $avarage_rate = 0;
                                      $total_user_rated = 0;

                                      $five_ratio = 0;
                                      $four_ratio = 0;
                                      $three_ratio = 0;
                                      $two_ratio = 0;
                                      $one_ratio = 0;
                                  }

                                  $products_data->rating = number_format($avarage_rate, 2);
                                  $products_data->total_user_rated = $total_user_rated;

                                  $products_data->five_ratio = $five_ratio;
                                  $products_data->four_ratio = $four_ratio;
                                  $products_data->three_ratio = $three_ratio;
                                  $products_data->two_ratio = $two_ratio;
                                  $products_data->one_ratio = $one_ratio;

                                  //review by users
                                  $products_data->reviewed_customers = $reviewed_customers;

                                  array_push($result, $products_data);
                                  $options = array();
                                  $attr = array();

                                  //like product
                                  if (!empty($request->customers_id)) {
                                      $liked_customers_id = $request->customers_id;
                                      $categories = DB::table('liked_products')->where('liked_products_id', '=', $products_id)->where('liked_customers_id', '=', $liked_customers_id)->get();

                                      if (count($categories) > 0) {
                                          $result[$index]->isLiked = '1';
                                      } else {
                                          $result[$index]->isLiked = '0';
                                      }
                                  } else {
                                      $result[$index]->isLiked = '0';
                                  }

                                  $stocks = 0;
                                  $stockOut = 0;
                                  $defaultStock = 0;
                                  if ($products_data->products_type == '0') {
                                      $stocks = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'in')->sum('stock');
                                      $stockOut = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'out')->sum('stock');
                                      $defaultStock = $stocks - $stockOut;
                                  }

                                  if ($products_data->products_max_stock < $defaultStock && $products_data->products_max_stock > 0) {
                                      $result[$index]->defaultStock = $products_data->products_max_stock;
                                  } else {
                                      $result[$index]->defaultStock = $defaultStock;
                                  }

                                  //get function from other controller
                                  $myVar = new AdminSiteSettingController();
                                  $languages = $myVar->getLanguages();
                                  $data = array();
                                  foreach ($languages as $languages_data) {
                                      $products_attribute = DB::table('products_attributes')->where('products_id', '=', $products_id)->groupBy('options_id')->get();
                                      if (count($products_attribute) > 0) {
                                          $index2 = 0;
                                          foreach ($products_attribute as $attribute_data) {
                                              $option_name = DB::table('products_options')
                                                  ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $attribute_data->options_id)->get();

                                              if (count($option_name) > 0) {
                                                  $temp = array();
                                                  $temp_option['id'] = $attribute_data->options_id;
                                                  $temp_option['name'] = $option_name[0]->products_options_name;
                                                  $attr[$index2]['option'] = $temp_option;

                                                  // fetch all attributes add join from products_options_values table for option value name
                                                  $attributes_value_query = DB::table('products_attributes')->where('products_id', '=', $products_id)->where('options_id', '=', $attribute_data->options_id)->get();
                                                  foreach ($attributes_value_query as $products_option_value) {
                                                      $option_value = DB::table('products_options_values')->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name')->where('products_options_values_descriptions.language_id', '=', $language_id)->where('products_options_values.products_options_values_id', '=', $products_option_value->options_values_id)->get();
                                                      $attributes = DB::table('products_attributes')->where([['products_id', '=', $products_id], ['options_id', '=', $attribute_data->options_id], ['options_values_id', '=', $products_option_value->options_values_id]])->get();
                                                      $temp_i['products_attributes_id'] = $attributes[0]->products_attributes_id;
                                                      $temp_i['id'] = $products_option_value->options_values_id;
                                                      $temp_i['value'] = $option_value[0]->products_options_values_name;

                                                      //check currency start
                                                      $current_price = $products_option_value->options_values_price;

                                                      $attribute_price = Product::convertprice($current_price, $requested_currency);


                                                      //check currency end
                                                      $temp_i['price'] = $attribute_price;
                                                      $temp_i['price_prefix'] = $products_option_value->price_prefix;
                                                      array_push($temp, $temp_i);

                                                  }
                                                  $attr[$index2]['values'] = $temp;
                                                  $data[$languages_data->code] = $attr;
                                                  $result[$index]->detail = $result2;
                                                  $index2++;
                                              }

                                          }
                                          $result[$index]->attributes = $data;
                                      } else {
                                          $result[$index]->attributes = array();
                                      }
                                  }
                                  $index++;
                              }
                          }
                      }
                      $responseData = array('success' => '1', 'product_data' => $filterProducts, 'message' => "Returned all products.", 'total_record' => count($index));
                  } else {
                      $total_record = array();
                      $responseData = array('success' => '0', 'product_data' => $filterProducts, 'message' => "Empty record.", 'total_record' => count($total_record));
                  }
              }
          } else {

              $total_record = DB::table('products_to_categories')
                  ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                  ->join('products', 'products.products_id', '=', 'products_to_categories.products_id')
                  ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                  ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                  ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                  ->LeftJoin('specials', function ($join) use ($currentDate) {
                      $join->on('specials.products_id', '=', 'products_to_categories.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                  })
                  ->whereBetween('products.products_price', [$minPrice, $maxPrice])
                  ->where('products_to_categories.categories_id', '=', $categories_id)
                  ->where('categories.parent_id', '!=', '0')
                  ->get();

              $products = DB::table('products_to_categories')
                  ->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
                  ->join('products', 'products.products_id', '=', 'products_to_categories.products_id')
                  ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
                  ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
                  ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
                  ->LeftJoin('specials', function ($join) use ($currentDate) {
                      $join->on('specials.products_id', '=', 'products_to_categories.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
                  })
                  ->select('products_to_categories.*', 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price')
                  ->whereBetween('products.products_price', [$minPrice, $maxPrice])
                  ->where('products_to_categories.categories_id', '=', $categories_id)
                  ->where('categories.parent_id', '!=', '0')
                  ->skip($skip)->take(10)
                  ->get();

              $result = array();
              //check if record exist
              if (count($products) > 0) {
                  $index = 0;
                  foreach ($products as $products_data) {
                      //check currency start
                      $requested_currency = $request->currency_code;
                      $current_price = $products_data->products_price;

                        $products_price = Product::convertprice($current_price, $requested_currency);
                        ////////// for discount price    /////////
                        if(!empty($products_data->discount_price)){
                          $discount_price = Product::convertprice($products_data->discount_price, $requested_currency);
                          $products_data->discount_price = $discount_price;
                        }


                      $products_data->products_price = $products_price;
                      $products_data->currency = $requested_currency;
                      //check currency end
                      $products_id = $products_data->products_id;

                      //multiple images
                      $products_images = DB::table('products_images')
                          ->LeftJoin('image_categories', function ($join) {
                              $join->on('image_categories.image_id', '=', 'products_images.image')
                                  ->where(function ($query) {
                                      $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                          ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                          ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                                  });
                          })
                          ->select('products_images.*', 'image_categories.path as image')
                          ->where('products_id', '=', $products_id)->orderBy('sort_order', 'ASC')->get();

                      $categories = DB::table('products_to_categories')
                          ->leftjoin('categories', 'categories.categories_id', 'products_to_categories.categories_id')
                          ->leftjoin('categories_description', 'categories_description.categories_id', 'products_to_categories.categories_id')
                          ->select('categories.categories_id', 'categories_description.categories_name', 'categories.categories_image', 'categories.categories_icon', 'categories.parent_id')
                          ->where('products_id', '=', $products_id)
                          ->where('categories_description.language_id', '=', $language_id)->get();

                      $products_data->categories = $categories;

                      $reviews = DB::table('reviews')
                          ->join('users', 'users.id', '=', 'reviews.customers_id')
                          ->select('reviews.*', 'users.avatar as image')
                          ->where('products_id', $products_data->products_id)
                          ->where('reviews_status', '1')
                          ->get();

                      $avarage_rate = 0;
                      $total_user_rated = 0;

                      if (count($reviews) > 0) {

                          $five_star = 0;
                          $five_count = 0;

                          $four_star = 0;
                          $four_count = 0;

                          $three_star = 0;
                          $three_count = 0;

                          $two_star = 0;
                          $two_count = 0;

                          $one_star = 0;
                          $one_count = 0;

                          foreach ($reviews as $review) {

                              //five star ratting
                              if ($review->reviews_rating == '5') {
                                  $five_star += $review->reviews_rating;
                                  $five_count++;
                              }

                              //four star ratting
                              if ($review->reviews_rating == '4') {
                                  $four_star += $review->reviews_rating;
                                  $four_count++;
                              }
                              //three star ratting
                              if ($review->reviews_rating == '3') {
                                  $three_star += $review->reviews_rating;
                                  $three_count++;
                              }
                              //two star ratting
                              if ($review->reviews_rating == '2') {
                                  $two_star += $review->reviews_rating;
                                  $two_count++;
                              }

                              //one star ratting
                              if ($review->reviews_rating == '1') {
                                  $one_star += $review->reviews_rating;
                                  $one_count++;
                              }

                          }

                          $five_ratio = round($five_count / count($reviews) * 100);
                          $four_ratio = round($four_count / count($reviews) * 100);
                          $three_ratio = round($three_count / count($reviews) * 100);
                          $two_ratio = round($two_count / count($reviews) * 100);
                          $one_ratio = round($one_count / count($reviews) * 100);

                          $avarage_rate = (5 * $five_star + 4 * $four_star + 3 * $three_star + 2 * $two_star + 1 * $one_star) / ($five_star + $four_star + $three_star + $two_star + $one_star);
                          $total_user_rated = count($reviews);
                          $reviewed_customers = $reviews;
                      } else {
                          $reviewed_customers = array();
                          $avarage_rate = 0;
                          $total_user_rated = 0;

                          $five_ratio = 0;
                          $four_ratio = 0;
                          $three_ratio = 0;
                          $two_ratio = 0;
                          $one_ratio = 0;
                      }

                      $products_data->rating = number_format($avarage_rate, 2);
                      $products_data->total_user_rated = $total_user_rated;

                      $products_data->five_ratio = $five_ratio;
                      $products_data->four_ratio = $four_ratio;
                      $products_data->three_ratio = $three_ratio;
                      $products_data->two_ratio = $two_ratio;
                      $products_data->one_ratio = $one_ratio;

                      //review by users
                      $products_data->reviewed_customers = $reviewed_customers;

                      array_push($result, $products_data);
                      $options = array();
                      $attr = array();

                      //like product
                      if (!empty($request->customers_id)) {
                          $liked_customers_id = $request->customers_id;
                          $categories = DB::table('liked_products')->where('liked_products_id', '=', $products_id)->where('liked_customers_id', '=', $liked_customers_id)->get();
                          //print_r($categories);
                          if (count($categories) > 0) {
                              $result[$index]->isLiked = '1';
                          } else {
                              $result[$index]->isLiked = '0';
                          }
                      } else {
                          $result[$index]->isLiked = '0';
                      }

                      $stocks = 0;
                      $stockOut = 0;
                      $defaultStock = 0;
                      if ($products_data->products_type == '0') {
                          $stocks = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'in')->sum('stock');
                          $stockOut = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'out')->sum('stock');
                          $defaultStock = $stocks - $stockOut;
                      }

                      if ($products_data->products_max_stock < $defaultStock && $products_data->products_max_stock > 0) {
                          $result[$index]->defaultStock = $products_data->products_max_stock;
                      } else {
                          $result[$index]->defaultStock = $defaultStock;
                      }

                      // fetch all options add join from products_options table for option name
                      $products_attribute = DB::table('products_attributes')->where('products_id', '=', $products_id)->groupBy('options_id')->get();
                      if (count($products_attribute) > 0) {
                          $index2 = 0;
                          foreach ($products_attribute as $attribute_data) {
                              $option_name = DB::table('products_options')
                                  ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $attribute_data->options_id)->get();
                              $temp = array();
                              $temp_option['id'] = $attribute_data->options_id;
                              $temp_option['name'] = $option_name[0]->products_options_name;
                              $attr[$index2]['option'] = $temp_option;

                              // fetch all attributes add join from products_options_values table for option value name

                              $attributes_value_query = DB::table('products_attributes')->where('products_id', '=', $products_id)->where('options_id', '=', $attribute_data->options_id)->get();
                              foreach ($attributes_value_query as $products_option_value) {
                                  $option_value = DB::table('products_options_values')->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name')->where('products_options_values_descriptions.language_id', '=', $language_id)->where('products_options_values.products_options_values_id', '=', $products_option_value->options_values_id)->get();
                                  $attributes = DB::table('products_attributes')->where([['products_id', '=', $products_id], ['options_id', '=', $attribute_data->options_id], ['options_values_id', '=', $products_option_value->options_values_id]])->get();
                                  $temp_i['products_attributes_id'] = $attributes[0]->products_attributes_id;
                                  $temp_i['id'] = $products_option_value->options_values_id;
                                  $temp_i['value'] = $option_value[0]->products_options_values_name;
                                  //check currency start
                                  $current_price = $products_option_value->options_values_price;


                                 $attribute_price = Product::convertprice($current_price, $requested_currency);


                                  //check currency end

                                  $temp_i['price'] = $attribute_price;

                                  $temp_i['price_prefix'] = $products_option_value->price_prefix;
                                  array_push($temp, $temp_i);

                              }
                              $attr[$index2]['values'] = $temp;
                              $result[$index]->attributes = $attr;
                              $index2++;

                          }
                      } else {
                          $result[$index]->attributes = array();
                      }
                      $index++;
                  }
                  $responseData = array('success' => '1', 'product_data' => $result, 'message' => "Returned all products.", 'total_record' => count($total_record));
              } else {
                  $total_record = array();
                  $responseData = array('success' => '0', 'product_data' => $result, 'message' => "Empty record.", 'total_record' => count($total_record));
              }

          }
      } else {
          $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
      }
      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

  public static function getsearchdata($request)
  {

    $language_id = $request->language_id;
    $searchValue = $request->searchValue;
    $currentDate = time();

    $result = array();
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

        $mainCategories = DB::table('categories')
            ->leftJoin('categories_description', 'categories_description.categories_id', '=', 'categories.categories_id')
            ->select('categories.categories_id as id', 'categories.categories_image as image', 'categories_description.categories_name as name')
            ->where('categories_description.categories_name', 'LIKE', '%' . $searchValue . '%')
            ->where('categories_description.language_id', '=', $language_id)
            ->where('parent_id', '0')->get();

        $result['mainCategories'] = $mainCategories;

        $subCategories = DB::table('categories')
            ->leftJoin('categories_description', 'categories_description.categories_id', '=', 'categories.categories_id')
            ->select('categories.categories_id as id', 'categories.categories_image as image', 'categories_description.categories_name as name')
            ->where('categories_description.categories_name', 'LIKE', '%' . $searchValue . '%')
            ->where('categories_description.language_id', '=', $language_id)
            ->where('parent_id', '1')->get();

        $result['subCategories'] = $subCategories;

        $manufacturers = DB::table('manufacturers')
            ->leftJoin('manufacturers_info', 'manufacturers_info.manufacturers_id', '=', 'manufacturers.manufacturers_id')
            ->select('manufacturers.manufacturers_id as id', 'manufacturers.manufacturer_image as image', 'manufacturers.manufacturer_name as name')
            ->where('manufacturers.manufacturer_name', 'LIKE', '%' . $searchValue . '%')
            ->get();

        $productsAttribute = DB::table('products')
            ->leftJoin('products_description', 'products_description.products_id', '=', 'products.products_id')
            ->leftJoin('manufacturers', 'manufacturers.manufacturers_id', '=', 'products.manufacturers_id')
            ->leftJoin('manufacturers_info', 'manufacturers.manufacturers_id', '=', 'manufacturers_info.manufacturers_id')
            ->leftJoin('products_attributes', 'products_attributes.products_id', '=', 'products.products_id')
            ->leftJoin('products_options', 'products_options.products_options_id', '=', 'products_attributes.options_id')
            ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')
            ->leftJoin('products_options_values', 'products_options_values.products_options_values_id', '=', 'products_attributes.options_values_id')
            ->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')
            ->LeftJoin('specials', function ($join) use ($currentDate) {
                $join->on('specials.products_id', '=', 'products.products_id')->where('specials.status', '=', '1')->where('expires_date', '>', $currentDate);
            })
            ->LeftJoin('image_categories', function ($join) {
                $join->on('image_categories.image_id', '=', 'products.products_image')
                    ->where(function ($query) {
                        $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                            ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                            ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                    });
            })

            ->leftJoin('flash_sale', 'flash_sale.products_id', '=', 'products.products_id')
        //->select(DB::raw(time().' as server_time'),'products.*','products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price','image_categories.path as products_image','users.*')

            ->select(DB::raw(time() . ' as server_time'), 'products.*', 'products_description.*', 'manufacturers.*', 'manufacturers_info.manufacturers_url', 'specials.specials_new_products_price as discount_price', 'specials.specials_new_products_price as discount_price', 'image_categories.path as products_image')

            ->orWhere('products_options_descriptions.options_name', 'LIKE', '%' . $searchValue . '%')
            ->whereNotIn('products.products_id', function ($query) {
                $query->select('flash_sale.products_id')->from('flash_sale');
            })
            ->where('products_description.language_id', '=', $language_id)
            ->where('products.products_status', '=', 1)
            ->orWhere('products_options_values_descriptions.options_values_name', 'LIKE', '%' . $searchValue . '%')
            ->whereNotIn('products.products_id', function ($query) {
                $query->select('flash_sale.products_id')->from('flash_sale');
            })
            ->where('products_description.language_id', '=', $language_id)
            ->where('products.products_status', '=', 1)
            ->orWhere('products_name', 'LIKE', '%' . $searchValue . '%')
            ->whereNotIn('products.products_id', function ($query) {
                $query->select('flash_sale.products_id')->from('flash_sale');
            })
            ->where('products_description.language_id', '=', $language_id)
            ->where('products.products_status', '=', 1)
            ->orWhere('products_model', 'LIKE', '%' . $searchValue . '%')
            ->whereNotIn('products.products_id', function ($query) {
                $query->select('flash_sale.products_id')->from('flash_sale');
            })
            ->where('products_description.language_id', '=', $language_id)
            ->where('products.products_status', '=', 1)
            ->groupBy('products.products_id')
            ->get();

        $result2 = array();
        //check if record exist
        if (count($productsAttribute) > 0) {
            $index = 0;
            foreach ($productsAttribute as $products_data) {
                //check currency start
                $requested_currency = $request->currency_code;
                $current_price = $products_data->products_price;
                
                  $products_price = Product::convertprice($current_price, $requested_currency);
                  ////////// for discount price    /////////
                  if(!empty($products_data->discount_price)){
                    $discount_price = Product::convertprice($products_data->discount_price, $requested_currency);
                    $products_data->discount_price = $discount_price;
                  }


                $products_data->products_price = $products_price;
                $products_data->currency = $requested_currency;
                //check currency end

                $products_id = $products_data->products_id;

                //multiple images
                $products_images = DB::table('products_images')
                    ->LeftJoin('image_categories', function ($join) {
                        $join->on('image_categories.image_id', '=', 'products_images.image')
                            ->where(function ($query) {
                                $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                                    ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                                    ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                            });
                    })
                    ->select('products_images.*', 'image_categories.path as image')
                    ->where('products_id', '=', $products_id)->orderBy('sort_order', 'ASC')->get();

                $categories = DB::table('products_to_categories')
                    ->leftjoin('categories', 'categories.categories_id', 'products_to_categories.categories_id')
                    ->leftjoin('categories_description', 'categories_description.categories_id', 'products_to_categories.categories_id')
                    ->select('categories.categories_id', 'categories_description.categories_name', 'categories.categories_image', 'categories.categories_icon', 'categories.parent_id')
                    ->where('products_id', '=', $products_id)
                    ->where('categories_description.language_id', '=', $language_id)->get();

                $products_data->categories = $categories;

                $reviews = DB::table('reviews')
                    ->join('users', 'users.id', '=', 'reviews.customers_id')
                    ->select('reviews.*', 'users.avatar as image')
                    ->where('products_id', $products_data->products_id)
                    ->where('reviews_status', '1')
                    ->get();

                $avarage_rate = 0;
                $total_user_rated = 0;

                if (count($reviews) > 0) {

                   $five_star = 0;
                  $five_count = 0;

                  $four_star = 0;
                  $four_count = 0;

                  $three_star = 0;
                  $three_count = 0;

                  $two_star = 0;
                  $two_count = 0;

                  $one_star = 0;
                  $one_count = 0;

                    foreach ($reviews as $review) {

                        //five star ratting
                        if ($review->reviews_rating == '5') {
                            $five_star += $review->reviews_rating;
                            $five_count++;
                        }

                        //four star ratting
                        if ($review->reviews_rating == '4') {
                            $four_star += $review->reviews_rating;
                            $four_count++;
                        }
                        //three star ratting
                        if ($review->reviews_rating == '3') {
                            $three_star += $review->reviews_rating;
                            $three_count++;
                        }
                        //two star ratting
                        if ($review->reviews_rating == '2') {
                            $two_star += $review->reviews_rating;
                            $two_count++;
                        }

                        //one star ratting
                        if ($review->reviews_rating == '1') {
                            $one_star += $review->reviews_rating;
                            $one_count++;
                        }

                    }
                  $five_ratio = round($five_count / count($reviews) * 100);
                  $four_ratio = round($four_count / count($reviews) * 100);
                  $three_ratio = round($three_count / count($reviews) * 100);
                  $two_ratio = round($two_count / count($reviews) * 100);
                  $one_ratio = round($one_count / count($reviews) * 100);
                    $avarage_rate = (5 * $five_star + 4 * $four_star + 3 * $three_star + 2 * $two_star + 1 * $one_star) / ($five_star + $four_star + $three_star + $two_star + $one_star);
                    $total_user_rated = count($reviews);
                    $reviewed_customers = $reviews;

                } else {
                    $reviewed_customers = array();
                    $avarage_rate = 0;
                    $total_user_rated = 0;
                     $five_ratio = 0;
                      $four_ratio = 0;
                      $three_ratio = 0;
                      $two_ratio = 0;
                      $one_ratio = 0;
                }

                $products_data->rating = number_format($avarage_rate, 2);
                $products_data->total_user_rated = $total_user_rated;
                $products_data->five_ratio = $five_ratio;
                  $products_data->four_ratio = $four_ratio;
                  $products_data->three_ratio = $three_ratio;
                  $products_data->two_ratio = $two_ratio;
                  $products_data->one_ratio = $one_ratio;
                //review by users
                $products_data->reviewed_customers = $reviewed_customers;

                array_push($result2, $products_data);
                $options = array();
                $attr = array();

                //like product
                if (!empty($request->customers_id)) {
                    $liked_customers_id = $request->customers_id;
                    $categories = DB::table('liked_products')->where('liked_products_id', '=', $products_id)->where('liked_customers_id', '=', $liked_customers_id)->get();
                    if (count($categories) > 0) {
                        $result2[$index]->isLiked = '1';
                    } else {
                        $result2[$index]->isLiked = '0';
                    }
                } else {
                    $result2[$index]->isLiked = '0';
                }
               $stocks = 0;
              $stockOut = 0;
              $defaultStock = 0;
              if ($products_data->products_type == '0') {
                  $stocks = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'in')->sum('stock');
                  $stockOut = DB::table('inventory')->where('products_id', $products_data->products_id)->where('stock_type', 'out')->sum('stock');
                  $defaultStock = $stocks - $stockOut;
              }

              if ($products_data->products_max_stock < $defaultStock && $products_data->products_max_stock > 0) {
                  $result2[$index]->defaultStock = $products_data->products_max_stock;
              } else {
                  $result2[$index]->defaultStock = $defaultStock;
              }
                // fetch all options add join from products_options table for option name
                $products_attribute = DB::table('products_attributes')->where('products_id', '=', $products_id)->groupBy('options_id')->get();
                if (count($products_attribute) > 0) {
                    $index2 = 0;
                    foreach ($products_attribute as $attribute_data) {
                        $option_name = DB::table('products_options')
                            ->leftJoin('products_options_descriptions', 'products_options_descriptions.products_options_id', '=', 'products_options.products_options_id')->select('products_options.products_options_id', 'products_options_descriptions.options_name as products_options_name', 'products_options_descriptions.language_id')->where('language_id', '=', $language_id)->where('products_options.products_options_id', '=', $attribute_data->options_id)->get();
                        if (count($option_name) > 0) {
                            $temp = array();
                            $temp_option['id'] = $attribute_data->options_id;
                            $temp_option['name'] = $option_name[0]->products_options_name;
                            $attr[$index2]['option'] = $temp_option;

                            // fetch all attributes add join from products_options_values table for option value name
                            $attributes_value_query = DB::table('products_attributes')->where('products_id', '=', $products_id)->where('options_id', '=', $attribute_data->options_id)->get();
                            foreach ($attributes_value_query as $products_option_value) {
                                $option_value = DB::table('products_options_values')->leftJoin('products_options_values_descriptions', 'products_options_values_descriptions.products_options_values_id', '=', 'products_options_values.products_options_values_id')->select('products_options_values.products_options_values_id', 'products_options_values_descriptions.options_values_name as products_options_values_name')->where('products_options_values_descriptions.language_id', '=', $language_id)->where('products_options_values.products_options_values_id', '=', $products_option_value->options_values_id)->get();
                                $attributes = DB::table('products_attributes')->where([['products_id', '=', $products_id], ['options_id', '=', $attribute_data->options_id], ['options_values_id', '=', $products_option_value->options_values_id]])->get();
                                $temp_i['products_attributes_id'] = $attributes[0]->products_attributes_id;
                                $temp_i['id'] = $products_option_value->options_values_id;
                                $temp_i['value'] = $option_value[0]->products_options_values_name;
                                //check currency start
                                $current_price = $products_option_value->options_values_price;


                                $attribute_price = Product::convertprice($current_price, $requested_currency);

                                //check currency end

                                $temp_i['price'] = $attribute_price;
                                $temp_i['price_prefix'] = $products_option_value->price_prefix;
                                array_push($temp, $temp_i);

                            }
                            $attr[$index2]['values'] = $temp;
                            $result2[$index]->attributes = $attr;
                            $index2++;
                        }
                    }
                } else {
                    $result2[$index]->attributes = array();
                }
                $index++;
            }

        }

        $result['products'] = $result2;
        $total_record = count($result['products']) + count($result['subCategories']) + count($result['mainCategories']);

        if (count($result['products']) == 0 and count($result['subCategories']) == 0 and count($result['mainCategories']) == 0) {
            $result = new \stdClass();
            $responseData = array('success' => '0', 'product_data' => $result, 'message' => "Search result is not found.", 'total_record' => $total_record);

        } else {
            $responseData = array('success' => '1', 'product_data' => $result, 'message' => "Returned all searched products.", 'total_record' => $total_record);
        }

    } else {
        $responseData = array('success' => '0', 'product_data' => array(), 'message' => "Unauthenticated call.");
    }
    $categoryResponse = json_encode($responseData);

    return $categoryResponse;
}

  public static function getquantity($request)
  {
      $inventory_ref_id = '';
      $result = array();
      $products_id = $request['products_id'];
      $productsType = DB::table('products')->where('products_id', $products_id)->get();
      //check products type
      if ($productsType[0]->products_type == 1) {
          $attributes = array_filter($request['attributes']);
          $attributeid = implode(',', $attributes);

          $postAttributes = count($attributes);

          $inventories = DB::table('inventory')->where('products_id', $products_id)->get();
          $reference_ids = array();
          $stocks = 0;
          $stockIn = 0;
          foreach ($inventories as $inventory) {

              $totalAttribute = DB::table('inventory_detail')->where('inventory_detail.inventory_ref_id', '=', $inventory->inventory_ref_id)->get();
              $totalAttributes = count($totalAttribute);

              if ($postAttributes > $totalAttributes) {
                  $count = $postAttributes;
              } elseif ($postAttributes < $totalAttributes or $postAttributes == $totalAttributes) {
                  $count = $totalAttributes;
              }

              $individualStock = DB::table('inventory')->leftjoin('inventory_detail', 'inventory_detail.inventory_ref_id', '=', 'inventory.inventory_ref_id')
                  ->selectRaw('inventory.*')
                  ->whereIn('inventory_detail.attribute_id', [$attributeid])
                  ->where(DB::raw('(select count(*) from `inventory_detail` where `inventory_detail`.`attribute_id` in (' . $attributeid . ') and `inventory_ref_id`= "' . $inventory->inventory_ref_id . '")'), '=', $count)
                  ->where('inventory.inventory_ref_id', '=', $inventory->inventory_ref_id)
                  ->groupBy('inventory_detail.inventory_ref_id')
                  ->get();

              if (count($individualStock) > 0) {
                  $inventory_ref_id = $individualStock[0]->inventory_ref_id;
                  $stockIn += $individualStock[0]->stock;
              }

          }

          //get option name and value
          $options_names = array();
          $options_values = array();
          foreach ($attributes as $attribute) {
              $productsAttributes = DB::table('products_attributes')
                  ->leftJoin('products_options', 'products_options.products_options_id', '=', 'products_attributes.options_id')
                  ->leftJoin('products_options_values', 'products_options_values.products_options_values_id', '=', 'products_attributes.options_values_id')
                  ->select('products_attributes.*', 'products_options.products_options_name as options_name', 'products_options_values.products_options_values_name as options_values')
                  ->where('products_attributes_id', $attribute)->get();

              $options_names[] = $productsAttributes[0]->options_name;
              $options_values[] = $productsAttributes[0]->options_values;
          }

          $options_names_count = count($options_names);
          $options_names = implode("','", $options_names);
          $options_names = "'" . $options_names . "'";
          $options_values = "'" . implode("','", $options_values) . "'";

          //orders products
          $orders_products = DB::table('orders_products')->where('products_id', $products_id)->get();
          $stockOut = 0;
          foreach ($orders_products as $orders_product) {
              $totalAttribute = DB::table('orders_products_attributes')->where('orders_products_id', '=', $orders_product->orders_products_id)->get();
              $totalAttributes = count($totalAttribute);

              if ($postAttributes > $totalAttributes) {
                  $count = $postAttributes;
              } elseif ($postAttributes < $totalAttributes or $postAttributes == $totalAttributes) {
                  $count = $totalAttributes;
              }

              $products = DB::select("select orders_products.* from `orders_products` left join `orders_products_attributes` on `orders_products_attributes`.`orders_products_id` = `orders_products`.`orders_products_id` where `orders_products`.`products_id`='" . $products_id . "' and `orders_products_attributes`.`products_options` in (" . $options_names . ") and `orders_products_attributes`.`products_options_values` in (" . $options_values . ") and (select count(*) from `orders_products_attributes` where `orders_products_attributes`.`products_id` = '" . $products_id . "' and `orders_products_attributes`.`products_options` in (" . $options_names . ") and `orders_products_attributes`.`products_options_values` in (" . $options_values . ") and `orders_products_attributes`.`orders_products_id`= '" . $orders_product->orders_products_id . "') = " . $count . " and `orders_products`.`orders_products_id` = '" . $orders_product->orders_products_id . "' group by `orders_products_attributes`.`orders_products_id`");

              if (count($products) > 0) {
                  $stockOut += $products[0]->products_quantity;
              }
          }
          $stocks = $stockIn - $stockOut;

      } else {

          $stocks = 0;

          $stocksin = DB::table('inventory')->where('products_id', $products_id)->where('stock_type', 'in')->sum('stock');
          $stockOut = DB::table('inventory')->where('products_id', $products_id)->where('stock_type', 'out')->sum('stock');
          $stocks = $stocksin - $stockOut;
      }

      $responseData = array('success' => '1', 'stock' => $stocks, 'message' => "Attributes are returned successfull!");
      $response = json_encode($responseData);

      return $response;
  }

  public static function shppingbyweight($request)
  {
      $result = array();
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
          $result = DB::table('products_shipping_rates')->where('products_shipping_status', '1')->get();
          $responseData = array('success' => '1', 'product_data' => $result, 'message' => "Returned all products.", 'total_record' => count($total_record));
      } else {
          $responseData = array('success' => '0', 'data' => $result, 'message' => "Unauthenticated call.");
      }

      $categoryResponse = json_encode($responseData);

      return $categoryResponse;
  }

}
