<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Variants;

class VariantsController extends Controller
{

    public function listVariants(Request $request)
    {
        return Variants::listVariants($request);
    }

    public function addVariant(Request $request)
    {
        return Variants::addVariant($request);
    }

    public function updateVariant(Request $request)
    {
        return Variants::updateVariant($request);
    }

    public function deleteVariant(Request $request)
    {
        return Variants::deleteVariant($request);
    }

    public function showVariants(Request $request)
    {
        return Variants::showVariants($request);
    }

    public function showVariant($id)
    {
        return Variants::showVariant($id);
    }
}
