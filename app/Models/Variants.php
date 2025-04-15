<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helpers;

class Variants extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'label',
        'description',
        'price',
        'available_stock',
        'shipping_label'
    ];

    public static function listVariants($request)
    {
        $perPage = $request->perPage ?? 10;
        $sortField = $request->sortField ?? 'created_at';
        $sortDirection = $request->sortDirection ?? 'asc';

        $variants = Variants::orderBy($sortField, $sortDirection)->paginate($perPage);

        return response()->json($variants, 200);
    }

    public static function addVariant($request)
    {
        DB::beginTransaction();
        try {
            $variant = new Variants();
            $variant->label = $request->label;
            $variant->description = $request->description;
            $variant->price = $request->price;
            $variant->available_stock = $request->available_stock;
            $variant->shipping_label = $request->shipping_label;
            $variant->gift_card_amount = $request->gift_card_amount ?? 0;

            $variant->save();
            DB::commit();

            return Helpers::Response(201, $variant);
        } catch (\Exception $ex) {
            DB::rollback();
            $responseData = $ex->getMessage();
            return Helpers::Response(400, $responseData);
        }
    }

    public static function updateVariant($request)
    {
        DB::beginTransaction();
        try {
            $variant = Variants::find($request->id);
            $variant->label = $request->label;
            $variant->description = $request->description;
            $variant->price = $request->price;
            $variant->available_stock = $request->available_stock;
            $variant->shipping_label = $request->shipping_label;
            $variant->gift_card_amount = $request->gift_card_amount ?? 0;
            $variant->save();
            DB::commit();
            return Helpers::Response(201, $variant);
        } catch (\Exception $ex) {
            DB::rollback();
            $responseData = $ex->getMessage();
            return Helpers::Response(400, $responseData);
        }
    }

    public static function deleteVariant($request)
    {
        DB::beginTransaction();
        try {
            $variant = Variants::find($request->id);
            $variant->delete();
            DB::commit();
            return Helpers::Response(201, 'Variants Deleted successful');
        } catch (\Exception $ex) {
            DB::rollback();
            $responseData = $ex->getMessage();
            return Helpers::Response(400, $responseData);
        }
    }

    public static function showVariants($request)
    {

        $variants = Variants::all();

        return response()->json($variants, 200);
    }

    public static function showVariant($id)
    {
        $variant = Variants::find($id);

        return response()->json($variant, 200);
    }
}
