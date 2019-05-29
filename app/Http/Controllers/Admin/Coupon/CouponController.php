<?php

namespace App\Http\Controllers\Admin\Coupon;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsed;
use App\Models\BranchCoupon;
use App\Branch;
use Validator;
use Session;
use Carbon\Carbon;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coupon = Coupon::all();
        return view('admin.coupon.list',compact('coupon'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $branchs = Branch::all();
        
        return view('admin.coupon.create' , ['branchs' => $branchs ] );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coupon_code' => 'required|min:2|max:255',
            'value' => 'required|integer',
            'minimum' => 'required|integer',
            'limit' => 'required|integer',
            'status' => 'required',
            'startDate' => 'required',
            'expDate' => 'required',
        ]);

        if ($validator->fails()) {
            Session::flash('error', $validator->messages()->first());
            return redirect()->back()->withInput();;
        }
        // dd($request->all());
        $addCupon = New Coupon;
        $addCupon->type =  $request->type;
        $addCupon->coupon_code = $request->coupon_code;
        $addCupon->minimum = $request->minimum;
        $addCupon->value = $request->value;
        $addCupon->limit = $request->limit;
        $addCupon->startDate = Carbon::parse($request->startDate . $request->startTime);
        $addCupon->expDate = Carbon::parse($request->expDate . $request->expTime);
        $addCupon->status = $request->status;
        $addCupon->save();

        if(!empty($request->branchs)){
            $addCupon->branch()->sync($request->branchs);
        }


        return redirect()->route('admin.coupon.index')->with('message', 'Create Coupon successful!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $coupon = Coupon::findOrFail($id);
        $branchs = Branch::all();
        $selectedIds = [];
        $brancnCoupons = BranchCoupon::where('coupon_id' , $id)->get();

        foreach ($brancnCoupons as $key => $item) {
            # code...
            array_push($selectedIds ,$item->branch_id);
        }
        // dd($selectedIds);
        if(!empty($coupon)){
            $coupon['startTime'] = Carbon::parse($coupon->startDate)->format('H:i'); 
            $coupon['expTime'] = Carbon::parse($coupon->expDate)->format('H:i'); 
            $coupon['startDate'] = Carbon::parse($coupon->startDate)->format('d-m-Y'); 
            $coupon['expDate'] = Carbon::parse($coupon->expDate)->format('d-m-Y');

            return view('admin.coupon.edit',compact('coupon' , 'branchs' , 'selectedIds'));
        }else{
            return back()->with('error' , 'ไม่มีคูปอง');
        }
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $data = $request->all();
        $coupon = Coupon::findOrFail($id);

        if(!empty($coupon)){
            $coupon->coupon_code = $data['coupon_code'] ;
            $coupon->minimum =$data['minimum']  ;
            $coupon->type = $data['type'];
            $coupon->value = $data['value'] ;
            $coupon->limit = $data['limit'] ;
            $coupon->startDate = Carbon::parse($data['startDate'] . $data['startTime']);
            $coupon->expDate = Carbon::parse($data['expDate'] . $data['expTime']);
            $coupon->status = $data['status'];
            $coupon->save();

            if(!empty($data['branchs'])){
                $coupon->branch()->sync($data['branchs']);
            }else{
                $couponUsed = CouponUsed::where('coupon_id' , $coupon->id )->get();
                $coupon->branch()->sync($data['branchs']);
                // $coupon->branch()->attach();
            }

            return redirect()->route('admin.coupon.index')->with('message', 'update coupon successful!');
        }else{
            return redirect()->route('admin.coupon.index')->with('error' , 'ไม่มีคูปอง');
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function couponUsed(Request $request)
    {
        $data = $request->all();
        $startDate = null;
        $endDate = null;

        if(!empty($data['coupon']) and $data['coupon'] !== 'all'){
            $coupon = CouponUsed::where('coupon_id' ,$data['coupon'] );
            $couponId = $data['coupon'] ;
        }else{
            $coupon = CouponUsed::orderBy('id' , 'desc');
            $couponId = 'all' ;
        }

        if(!empty($data['startDate']) and $data['endDate'] !== 'all'){
            $startDate = Carbon::parse($data['startDate'])->startOfday(); 
            $endDate = Carbon::parse($data['endDate'])->endOfDay();
            $coupon = $coupon->where([
                ['created_at'  , '>=' , $startDate ] ,
                ['created_at'  , '<=' , $endDate ] ,
            ]);
            $startDate = $startDate->format('d-m-Y');
            $endDate = $endDate->format('d-m-Y');
        }

        $coupon = $coupon->paginate(100);

        $couponAll = Coupon::all();
        $name = [] ; 
        foreach ($couponAll as $key => $item) {
            $name[$item->id] = $item->coupon_code;
            $discount[$item->id]  =  $item->value;
        }



        return view('admin.coupon.used',compact('coupon' , 'name' , 'couponAll' , 'couponId' , 'discount' , 'startDate' , 'endDate'));
    }

    public function couponCancel($id)
    {
        $coupon = CouponUsed::destroy($id);
        return back()->with('success' , 'success');
    }
}
