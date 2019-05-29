<?php

namespace App\Http\Controllers\Admin\Branch;

use App\Http\Controllers\Controller;
use App\Branch;
use App\BranchZipcode;
use Illuminate\Http\Request;
use DB;

class BranchController extends Controller
{

    public function __construct()
    {

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $branchs = Branch::paginate(15);
        // $branchZipcode = BranchZipcode::where('branch_id' , 1)->get();
        $zipcode = array();
        $province = array();
        foreach ($branchs as $key => $item) {
            $branchZipcode = DB::table('branch_zipcode')->select("zipcode")->where('branch_id' , $item->id)->groupBy('zipcode')->get();
            $branchProvince = DB::table('branch_zipcode')->select("province")->where('branch_id' , $item->id)->groupBy('province')->get();
            $zipcode[$key] = $branchZipcode;
            $province[$key] = $branchProvince;
        }

        return view('admin.branchs.list' , [
            'branchs' => $branchs,
            'zipcode' => $zipcode,
            'province' => $province,
            ]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        return view('admin.branchs.create');
    }

    /**
     * @param CreateBrandRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $branch = new Branch ;
        $branch->name = $request->name ;
        $branch->zipcode = $request->zipcode ;
        $branch->province = $request->province ;
        $branch->save();

        return redirect()->route('admin.branchs.index')->with('message', 'Create brand successful!');
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id)
    {
        $branch = Branch::find($id);
        $branchZipcode = BranchZipcode::where('branch_id' , $id)->get();
        return view('admin.branchs.edit', [
            'branch' => $branch,
            'branchZipcode' => $branchZipcode,
            ]);
    }

    /**
     * @param UpdateBrandRequest $request
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \App\Shop\Brands\Exceptions\UpdateBrandErrorException
     */
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->name = $request->name;
        $branch->save();

        $zipcode = $request->zipcode;
        $province = $request->province;
        for ($i=0; $i < sizeof($zipcode); $i++) {
            $checkZipcode = BranchZipcode::where('branch_id' , $id)->where('zipcode' , $zipcode[$i])->first();
            if(empty($checkZipcode)){
                $branchZipcode = new BranchZipcode;
                $branchZipcode->zipcode =  $zipcode[$i];
                $branchZipcode->province =  $province[$i];
                $branchZipcode->branch_id =  $id;
                $branchZipcode->save();
            }
        }

        return redirect()->route('admin.branchs.edit', $id)->with('message', 'Update successful!');
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($id)
    {
        $branch = Branch::destroy($id);
        return redirect()->route('admin.branchs.index')->with('message', 'Delete successful!');
    }

    public function show($id)
    {
        $branch = Branch::findOrFail($id);
        // dd($branch);
        return view('admin.branchs.show' ,[
            'branch' => $branch,
        ]);
    }

    public function deleteBranch($id)
    {
        $branch = Branch::destroy($id);
        if ($request->ajax()) {
            return response()->json([
                'status' => true,
            ]);
        }else{
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function deleteZipcode(Request $request)
    {   
        $data = $request->all();

        $branchZipcode = BranchZipcode::findOrFail($data['id']);

        if ( !empty($branchZipcode)) {
            $destroy = BranchZipcode::destroy($data['id']);

            return response()->json([
                'status' => true,
            ]);
        }else{
            return response()->json([
                'status' => false,
            ]);
        }
    }
}
