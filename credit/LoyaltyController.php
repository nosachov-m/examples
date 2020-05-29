<?php

namespace App\Http\Controllers;

use App\CreditRequest;
use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Action;
use XLSXWriter;
use App\Credit;
use App\Http\Requests\AddLoyaltyCoupon;
use App\LoyaltyCoupons;
use App\LoyaltyCouponsUsed;

class LoyaltyController extends Controller
{

    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function index(Request $request)
    {
        return view('loyalty.index',
            [

            ]);
    }

    public function addPromo(AddLoyaltyCoupon $request, LoyaltyCoupons $coupon)
    {
        $coupon->name = $request->name;
        $coupon->discount = $request->discount;
        if (isset($request->reusable) AND $request->reusable == 'on') {
            $coupon->reusable = true;
        }
        if (isset($request->is_active) AND $request->is_active == 'on') {
            $coupon->is_active = true;
        }
        $coupon->save();
        return redirect()->back();

    }

    public function getCouponsListAjax(Request $request)
    {
        try {
            $params = $request->all();

            $recordsTotal = LoyaltyCoupons::with('manager');
            if ($request->coupon_name) {
                $recordsTotal = $recordsTotal->where('name', '=', $request->coupon_name);
            }

            if ($request->coupon_create_start AND $request->coupon_create_end) {
                $coupon_create_start = Carbon::createFromFormat('d.m.Y', $request->coupon_create_start)->format('Y-m-d');
                $coupon_create_end = Carbon::createFromFormat('d.m.Y', $request->coupon_create_end)->format('Y-m-d');
                $recordsTotal = $recordsTotal->where('created_at', '>=', $coupon_create_start.' 00:00:00')
                    ->where('created_at', '<=', $coupon_create_end.' 23:59:59');
            }
            $start = $request->start ? $request->start : 0;
            $length = $request->length ? $request->length : 50;
            $coupons = $recordsTotal->orderByDesc('id')
                ->offset($start)->limit($length)
                ->get();
            $recordsTotal = LoyaltyCoupons::where('id', '>', 0)->get()->count();
            $cnt = $coupons->count();

            $data = [];
            foreach ($coupons as $coupon){

                $data[] = [
                    'name' => $coupon->name,
                    'discount' => $coupon->discount,
                    'reusable' => $coupon->reusable ? 'Да' : 'Нет',
                    'is_active' => $coupon->is_active ? 'Да' : 'Нет',
                    'date' => $coupon->active_till ? Carbon::parse($coupon->active_till)->format('Y-m-d') : '',
                    'comment' => $coupon->comment,
                ];


            }

            return json_encode(['data' => $data, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $cnt, 'params' => $params]);
        } catch (\Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }

    }

    public function getUsedCouponsListAjax(Request $request)
    {
        try {
            $params = $request->all();

            $recordsTotal = LoyaltyCouponsUsed::with('user', 'coupon_info');
            if ($request->coupon_name) {
                $recordsTotal = $recordsTotal->where('name', '=', $request->coupon_name);
            }

            if ($request->coupon_create_start AND $request->coupon_create_end) {
                $coupon_create_start = Carbon::createFromFormat('d.m.Y', $request->coupon_create_start)->format('Y-m-d');
                $coupon_create_end = Carbon::createFromFormat('d.m.Y', $request->coupon_create_end)->format('Y-m-d');
                $recordsTotal = $recordsTotal->where('created_at', '>=', $coupon_create_start.' 00:00:00')
                    ->where('created_at', '<=', $coupon_create_end.' 23:59:59');
            }
            $start = $request->start ? $request->start : 0;
            $length = $request->length ? $request->length : 50;
            $coupons = $recordsTotal->orderByDesc('id')
                ->offset($start)->limit($length)
                ->get();
            $recordsTotal = LoyaltyCouponsUsed::where('id', '>', 0)->get()->count();
            $cnt = $coupons->count();

            $data = [];
            foreach ($coupons as $coupon){

                $data[] = [
                    'date' => Carbon::parse($coupon->created_at)->format('Y-m-d H:i:s'),
                    'user' => '<a href="'.route('viewUser', [$coupon->user_id]).'" target="_blank">'.$coupon->user['lastname'].' '.$coupon->user['firstname'].'</a>',
                    'request' => '<a href="'.route('viewRequest', [$coupon->request_id]).'" target="_blank">Заявка №'.$coupon->request_id.'</a>',
                    'name' => $coupon->coupon_info['name'],
                    'discount' => $coupon->coupon_info['discount'],
                ];


            }

            return json_encode(['data' => $data, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $cnt, 'params' => $params]);
        } catch (\Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }

    }
}
