<?php

namespace Paymob\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayMobWebHockController extends  controller
{
    public function run(Request $request)
    {
        PaymobClient::checkHmac($request->input('hmac'));
        return view('paymob::webhook');
    }

}