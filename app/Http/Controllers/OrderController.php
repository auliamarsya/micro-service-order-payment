<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Str;

class OrderController extends Controller
{
    public function index(Request $request){
        $userId = $request->user_id;

        $getOrders = Order::when($userId, function($query) use ($userId) {
            return $query->where('user_id', $userId);
        });

        return response()->json([
            'status' => 'success',
            'data' => $getOrders->get()
        ]);
    }
    /**
     * The create function in PHP creates an order, generates a payment URL using Midtrans, and saves
     * the order details.
     * 
     * @param Request request The `` parameter is an instance of the `Request` class, which
     * represents an HTTP request. It contains data sent by the client, such as form inputs, query
     * parameters, and headers.
     * 
     * @return a JSON response with the status "success" and the data of the created order.
     */
    public function create(Request $request) 
    {
        try {
            $user = $request->user;
            $course = $request->course;

            $orderCreated = Order::create([
                'user_id' => $user['id'],
                'course_id' => $course['id']
            ]);

            $transactionDetails = [
                "order_id" => $orderCreated->id."-".Str::random(5),
                "gross_amount" => $course['price'],
            ];

            $itemDetails = [
                [
                    "id" => $course["id"],
                    "price" => $course["price"],
                    "quantity" => 1,
                    "name" => $course["name"],
                    "brand" => "LisyaCourse",
                    "category" => "Online Course"
                ]
            ];

            $customerDetails = [
                "first_name" => $user['name'],
                "email" => $user['email']
            ];

            $midtransParams = [
                'transaction_details' => $transactionDetails,
                "item_details" => $itemDetails,
                "customer_details" => $customerDetails
            ];

            $snapUrl = $this->getMidtrastSnapUrl($midtransParams);

            $orderCreated->snap_url = $snapUrl;
            $orderCreated->metadata = [
                "id" => $course["id"],
                "price" => $course["price"],
                "course_name" => $course["name"],
                "course_thumbnail" => $course["thumbnail"],
                "course_level" => $course["level"],
            ];
            $orderCreated->save();

            return response()->json([
                'status' => 'success',
                'data' => $orderCreated
            ]);
        } catch (\Throwable $th) {
            return [
                "status" => "error",
                "http_code" => 500,
                "message" => $th->getMessage()
            ];
        }
        
    }

    /**
     * The function `getMidtrastSnapUrl` is used to retrieve the redirect URL for creating a transaction
     * using the Midtrans payment gateway in PHP.
     * 
     * @param params The `` parameter is an array that contains the necessary information to create
     * a transaction using Midtrans. It typically includes details such as the transaction amount, customer
     * details, and other relevant information needed to process the payment.
     * 
     * @return string the value of the variable ``, which is a string representing the redirect URL
     * for the transaction.
     */
    private function getMidtrastSnapUrl($params)
    {

        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');

        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');

        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_3DS');

        $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;

        return $snapUrl;
    }
}
