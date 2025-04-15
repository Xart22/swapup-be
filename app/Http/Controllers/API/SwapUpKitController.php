<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SwapUpKit;
use App\Services\UserConsignServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class SwapUpKitController extends Controller
{
    private $userConsignServices;

    public function getSwapUpKit(UserConsignServices $userConsignServices)
    {
        $this->userConsignServices = $userConsignServices;

        $response = SwapUpKit::first();
        return Helpers::Response(200, $response);
    }

    public function upsert(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }
        $swapUpKit = SwapUpKit::first(); // Assuming there is only one data entry

        $imagePath = $swapUpKit->image ?? null;

        if ($swapUpKit) {
            // Update the existing entry
            if ($request->hasFile('image')) {
                // Delete the old image
                if ($swapUpKit->image) {
                    unlink(public_path('/') . $swapUpKit->image);
                }

                // Store the new image with a random name
                $imageName = Str::random(10) . '.' . $request->image->getClientOriginalExtension();
                $imagePath = $request->file('image')->move('images', $imageName);
                $imagePath = 'images/' . $imageName; // Save the relative path
            } else {
                $imagePath = $swapUpKit->image;
            }

            $swapUpKit->update([
                'title' => $request->title,
                'image' => $imagePath,
                'description' => $request->description,
            ]);

            return response()->json(['message' => 'Swap Up Kit updated successfully', 'data' => $swapUpKit], 200);
        } else {
            // Create a new entry
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imageName = Str::random(10) . '.' . $request->image->getClientOriginalExtension();
                $imagePath = $request->file('image')->move('images', $imageName);
                $imagePath = 'images/' . $imageName; // Save the relative path
            }

            $swapUpKit = SwapUpKit::create([
                'title' => $request->title,
                'image' => $imagePath,
                'description' => $request->description,
            ]);

            return response()->json(['message' => 'Swap Up Kit created successfully', 'data' => $swapUpKit], 201);
        }
    }

    public function sellSwapUpKitViaBalance(Request $request) {

        $validator = Validator::make($request->all(), [
            'variant_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        

        // DB::beginTransaction();
        // try {
        //     $input = $request->all();
        //     $user = Auth::user()->consign_id;

        //     $memo = "$".(isset($input['amount']) ? number_format($input['amount'] / 100, 2)  : 0) . " cash out request to ". Auth::user()->bsb ." ".Auth::user()->account_number;

        //     $consignData = [
        //         "account" => $user,
        //         "amount" => -$input['amount'],
        //         "location" => null,
        //         "memo" => $memo,
        //     ];

        //     $data = $this->userConsignServices->getUser($user);

        //     if ($data["balance"] < $input['amount']) {
        //         return response()->json([
        //             "error" => "Validation failed cashout",
        //             "message" => 'You have a balance $' . number_format((isset($data['balance']) ? $data['balance'] : 0) / 100, 2) . ' cannot sell with your balance'
        //         ], 500);
        //     }

        //     $consignResponse = $this->userConsignServices->cashout($consignData);

        //     $cashout = new RequestCashout();

        //     $cashout->user_id = Auth::id();
        //     $cashout->cashout_amount = $input['amount'];
        //     $cashout->request_date = Carbon::now();
        //     $cashout->receipt_number = null;
        //     $cashout->mark_paid = null;

        //     $cashout->save();

        //     DB::commit();
        //     return Helpers::Response(201, $consignResponse);
        // } catch (\Exception $e) {
        //     DB::rollback();
        //     return Helpers::Response(400, $e->getMessage());
        // }
    }
}
