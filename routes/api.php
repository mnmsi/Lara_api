<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::group(['prefix' => 'v1'], function () {

    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] passwordConf
     * @param  [string] phone
     * @param  [string] address
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    Route::post('signup', function (Request $req) {

        $reqData = $req->all();

        $rules = array(
            'name'         => 'required|string',
            'email'        => 'required|string|email|unique:users',
            'password'     => 'required|string|min:6',
            'passwordConf' => 'min:6|same:password',
            'phone'        => 'required|string',
            'address'      => 'required|string',
        );

        $validator = Validator::make($reqData, $rules);

        $attributes = array(
            'name'         => 'Name',
            'email'        => 'Email',
            'password'     => 'Password',
            'passwordConf' => 'Password Confirm',
            'phone'        => 'Phone',
            'address'      => 'Address',
        );

        $validator->setAttributeNames($attributes);

        if ($validator->fails()) {
            return response()->json([
                'responseType' => 'error',
                'message'      => implode(' || ', $validator->messages()->all()),
            ]);
        }

        $reqData['password'] = Hash::make($reqData['password']);
        $userInsert          = User::create($reqData);

        if ($userInsert) {
            $returnMsg = array(
                'responseType' => 'success',
                'message'      => 'Successfully created user!',
            );
        } else {
            $returnMsg = array(
                'responseType' => 'error',
                'message'      => 'Unsuccessfull to create user!',
            );
        }

        return response()->json($returnMsg);
    });

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    Route::post('login', function (Request $req) {

        $reqData = $req->all();

        $rules = array(
            'email'       => 'required|string|email',
            'password'    => 'required|string',
            'remember_me' => 'boolean',
        );

        $validator = Validator::make($reqData, $rules);

        if ($validator->fails()) {
            return response()->json([
                'responseType' => 'error',
                'message'      => implode(' || ', $validator->messages()->all()),
            ]);
        }

        if (!Auth::attempt(request(['email', 'password']))) {

            return response()->json([
                'responseType' => 'error',
                'message'      => 'Unauthorized to login!',
            ]);
        }

        $user        = $req->user();
        $tokenResult = $user->createToken('Personal Access Token');
        $token       = $tokenResult->token;

        if ($req->remember_me) {
            $token->expires_at = Carbon::now()->addWeeks(1);
        }

        $token->save();

        $returnMsg = array(
            'responseType' => 'success',
            'message'      => 'Successfully login!!',
            'access_token' => $tokenResult->accessToken,
            'token_type'   => 'Bearer',
            'expires_at'   => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString(),
        );

        return response()->json($returnMsg);
    });

    Route::group(['middleware' => 'auth:api', 'cors', 'json.response'], function () {

        /**
         * Logout user (Revoke the token)
         *
         * @return [string] message
         */
        Route::get('logout', function (Request $req) {

            $req->user()->token()->revoke();
            return response()->json([
                'responseType' => 'success',
                'message'      => 'Successfully logged out',
            ]);
        });

        /**
         * Get the all products
         *
         * @return [json] products object
         */
        Route::get('products', function (Request $req) {

            $products = DB::table('products')
                ->where('isDelete', 0)
                ->get();

            if ($products) {
                $data = array(
                    'responseType' => 'success',
                    'message'      => "Successfully loaded product!!",
                    'products'     => $products,
                );
            } else {
                $data = array(
                    'responseType' => 'error',
                    'message'      => "Unable to load product!!",
                );
            }

            return response()->json($data);
        });

        /**
         * add product into cart
         *
         * @param [string] productId
         * @param [string] productQuantity
         * @param [decimal] totalPrice
         * @param [decimal] totalDiscount
         * @param [decimal] totalAmount
         * @param [string] method
         * @return [json] response message object
         */
        Route::post('addProductToCart', function (Request $req) {

            $userData = $req->user();

            $isProductExist = DB::table('carts')
                ->where([['status', null], ['productId', $req->productId], ['userId', $userData->id]])
                ->first();

            if ($req->method == 'decrement') {
                DB::table('carts')
                    ->where([['status', null], ['productId', $req->productId], ['userId', $userData->id]])->update(['totalQuantity' => $isProductExist->totalQuantity - 1, 'totalAmount' => $isProductExist->totalPrice * ($isProductExist->totalQuantity - 1)]);

                $returnMsg = array(
                    'responseType' => 'success',
                    'message'      => 'Product removed!',
                );

                return response()->json($returnMsg);
            }

            if ($isProductExist) {
                DB::table('carts')
                    ->where([['status', null], ['productId', $req->productId], ['userId', $userData->id]])->update(['totalQuantity' => $isProductExist->totalQuantity + 1, 'totalAmount' => $isProductExist->totalPrice * ($isProductExist->totalQuantity + 1)]);

                $returnMsg = array(
                    'responseType' => 'success',
                    'message'      => 'Product added to cart!',
                );

                return response()->json($returnMsg);
            }

            DB::beginTransaction();
            try {
                $cartInsertId = DB::table('carts')->insertGetId([
                    'productId'     => $req->productId,
                    'userId'        => $userData->id,
                    'totalQuantity' => $req->productQuantity,
                    'totalPrice'    => $req->totalPrice,
                    'totalDiscount' => $req->totalDiscount,
                    'totalAmount'   => $req->totalAmount,
                    'createdBy'     => $userData->id,
                ]);

                $isCartDetailInsert = DB::table('cart_details')->insert([
                    'cartId'      => $cartInsertId,
                    'userName'    => $userData->name,
                    'userPhone'   => $userData->phone,
                    'userEmail'   => $userData->email,
                    'userAddress' => $userData->address,
                ]);

            } catch (\Throwable $th) {
                DB::rollback();
                return response()->json([
                    'responseType' => 'error',
                    'message'      => 'Unable to add product to cart!',
                ]);
            }

            DB::commit();
            $returnMsg = array(
                'responseType' => 'success',
                'message'      => 'Product added to cart!',
            );

            return response()->json($returnMsg);
        });

        /**
         * get number of carted item
         *
         * @param [string] user Id
         * @param [string] cart status
         * @return [json] response items object
         */
        Route::get('numCartedItem', function (Request $req) {

            $numCartedItem = DB::table('carts')
                ->where([['status', null], ['userId', $req->user()->id]])
                ->sum('totalQuantity');

            return response()->json($numCartedItem);
        });

        /**
         * get number of carted item
         *
         * @param [string] user Id
         * @param [string] cart status
         * @return [json] response items object
         */
        Route::get('cartItems', function (Request $req) {

            $products = DB::table('carts as c')
                ->where([['c.status', null], ['c.userId', $req->user()->id]])
                ->leftjoin('products as p', 'c.productId', 'p.id')
                ->select('c.id', 'c.totalQuantity', 'p.price', 'c.totalAmount', 'p.id as productId', 'p.name as productName', 'p.name as productModel')
                ->get();

            $totalPrice = $products->sum('totalAmount');

            $data = array(
                'responseType' => 'success',
                'products'     => $products,
                'totalPrice'   => $totalPrice,
            );

            return response()->json($data);
        });

        /**
         * edit product quantity
         *
         * @param [integer || string] cartId
         * @param [integer || string] updated quantity
         * @return [json] response message object
         */
        Route::put('updateCartQtn', function (Request $req) {

            $updateCartQtn = DB::table('carts')->where('id', $req->id)->update(['totalQuantity' => $req->updateQuantity]);

            if ($updateCartQtn) {
                $returnMsg = array(
                    'responseType' => 'success',
                    'message'      => 'Successfully updated quantity!',
                );
            } else {
                $returnMsg = array(
                    'responseType' => 'error',
                    'message'      => 'Unsuccessfull to updated quantity!',
                );
            }

            return response()->json($returnMsg);
        });

        /**
         * delete product from cart
         *
         * @param [integer || string] cartId
         * @return [json] response message object
         */
        Route::delete('deleteProductFromCart', function (Request $req) {

            DB::beginTransaction();
            try {
                DB::table('carts')->where('id', $req->id)->delete();
                DB::table('cart_details')->where('cartId', $req->id)->delete();

                DB::commit();
                $returnMsg = array(
                    'responseType' => 'success',
                    'message'      => 'Successfully deleted product from cart!',
                );

                return response()->json($returnMsg);

            } catch (\Throwable $th) {
                DB::rollback();
                return response()->json([
                    'responseType' => 'error',
                    'message'      => 'Unsuccessfull to deleted product from cart!',
                ]);
            }

        });

        /**
         * order product from cart
         *
         * @param [array] productIdArr
         * @param [array] productQuantityArr
         * @param [array] productPriceArr
         * @param [array] productDiscountArr
         * @param [array] productTotalAmountArr
         * @param [integer || string] totalQuantity
         * @param [integer || string] totalPrice
         * @param [integer || string] totalDiscount
         * @param [integer || string] totalAmount
         * @return [json] response message object
         */
        Route::post('submitOrder', function (Request $req) {

            $userData = $req->user();

            $productIdArr          = (isset($req->productIdArr) ? $req->productIdArr : array());
            $productQuantityArr    = (isset($req->productQuantityArr) ? $req->productQuantityArr : array());
            $productPriceArr       = (isset($req->productPriceArr) ? $req->productPriceArr : array());
            $productDiscountArr    = (isset($req->productDiscountArr) ? $req->productDiscountArr : array());
            $productTotalAmountArr = (isset($req->productTotalAmountArr) ? $req->productTotalAmountArr : array());

            DB::beginTransaction();
            try {

                $orderInsertId = DB::table('orders')->insertGetId([
                    'userId'        => $userData->id,
                    'status'        => 'processing',
                    'userName'      => $userData->name,
                    'userPhone'     => $userData->phone,
                    'userEmail'     => $userData->email,
                    'userAddress'   => $userData->address,
                    'totalQuantity' => $req->totalQuantity,
                    'totalPrice'    => $req->totalPrice,
                    'totalDiscount' => $req->totalDiscount,
                    'totalAmount'   => $req->totalAmount,
                    'createdBy'     => $userData->id,
                ]);

                foreach ($productIdArr as $key => $productId) {

                    $isInsert = DB::table('order_details')->insert([
                        'orderId'         => $orderInsertId,
                        'productId'       => $productId,
                        'productQuantity' => $productQuantityArr[$productId],
                        'productPrice'    => $productPriceArr[$productId],
                        'productDiscount' => $productDiscountArr[$productId],
                        'totalAmount'     => $productTotalAmountArr[$productId],
                    ]);

                    DB::table('carts')
                        ->where([
                            ['status', null],
                            ['productId', $productId],
                            ['userId', $userData->id],
                        ])
                        ->update(['status' => 'ordered']);
                }

            } catch (\Throwable $th) {
                DB::rollback();
                return response()->json([
                    'responseType' => 'error',
                    'message'      => 'Unsuccessfull to place order!',
                ]);
            }

            DB::commit();
            $returnMsg = array(
                'responseType' => 'success',
                'message'      => 'Successfully place your order!',
            );

            return response()->json($returnMsg);
        });
    });
});
