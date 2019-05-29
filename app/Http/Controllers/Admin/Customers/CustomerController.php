<?php

namespace App\Http\Controllers\Admin\Customers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Address;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Shop\Customers\Requests\CreateCustomerRequest;
use App\Shop\Customers\Requests\UpdateCustomerRequest;
use App\Shop\Customers\Transformations\CustomerTransformable;
use App\Http\Controllers\Controller;
use App\Branch;
use App\BranchZipcode;
use App\Models\CustomerType;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    use CustomerTransformable;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * CustomerController constructor.
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepo = $customerRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $list = $this->customerRepo->listCustomers('created_at', 'desc');
        $customers = Customer::with('getBranchZipcode' , 'getBranchZipcode.getBranch')->where('deleted_at' , null);
        $branchs = Branch::all();
        $q = null ;
        $branchId = null;

        if (request()->has('q')) {
            $q = request()->input('q') ;
            // $list = $this->customerRepo->searchCustomer(request()->input('q'));
            $customers = $customers->where('name' ,  'like' , '%' . $q . '%' );

        }
        //
        if(request()->has('branch')){
            $branchId = request()->input('branch');

            if($branchId  == 'all'){
                $customers = $customers;
            }elseif($branchId == 'not-at-all'){
                $customers = $customers->doesnthave('getBranchZipcode');
            }else{
                $customers = $customers->whereHas('getBranchZipcode.getBranch' , function($query) use ($branchId){
                    $query->where('id' , $branchId);
                });
            }

        }

        // $customers = $list->map(function (Customer $customer) {
        //     return $this->transformCustomer($customer);
        // })->all();

        $counts = count($customers->get());
        $customers = $customers->paginate(50);
        $customers->appends(request()->query());

        return view('admin.customers.list', [
            // 'customers' => $this->customerRepo->paginateArrayResults($customers)
            'customers' => $customers,
            'branchs' => $branchs,
            'branchId' => $branchId,
            'q' => $q,
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.customers.create');
    }
    public function createCustomer(){
        return view('admin.customers.createCus');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  CreateCustomerRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateCustomerRequest $request)
    {
        $this->customerRepo->createCustomer($request->except('_token', '_method'));

        return redirect()->route('admin.customers.index');
    }
    public function storeCustomer(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'zipcode' => 'required|string|max:5|min:5',
            'phone' => 'nullable|string|max:10|unique:customers,phone',
            'email' => 'nullable|string|email|max:50|unique:customers,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
           return redirect()->back()->withErrors($validator)
           ->withInput();;
        }

        if ($request->type == 1) {
            $choice = 1 ;
        }else{
            if(!empty($request->type_choice) ){
                $choice = $request->type_choice;
            }else{
                return redirect()->back();
            }
        }

        $customer = new Customer;
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->password = bcrypt($request->password);
        $customer->zipcode = $request->zipcode;
        $customer->role = 'user';
        $customer->phone = $request->phone;
        $customer->save();

        $address = new Address;
        $address->alias = $request->name;
        $address->address_1 = $request->address_1  . " " . $request->district . " "  . $request->amphoe  . " " . $request->province . " "  . $request->zipcode;
        $address->country_id = 211;
        $address->customer_id = $customer->id;
        $address->zip = $request->zipcode;
        $address->phone = $request->phone;
        $address->status = 1;
        $address->save();

        $pickType = new CustomerType;
        $pickType->customer_id = $customer->id;
        $pickType->type_id =  $choice ;
        $pickType->save();

        return redirect()->route('admin.customers.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $customer = $this->customerRepo->findCustomerById($id);

        return view('admin.customers.show', [
            'customer' => $customer,
            'addresses' => $customer->addresses
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('admin.customers.edit', ['customer' => $this->customerRepo->findCustomerById($id)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateCustomerRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCustomerRequest $request, $id)
    {
        $customer = $this->customerRepo->findCustomerById($id);

        $update = new CustomerRepository($customer);
        $data = $request->except('_method', '_token', 'password');

        if ($request->has('password')) {
            $data['password'] = bcrypt($request->input('password'));
        }

        $update->updateCustomer($data);

        $request->session()->flash('message', 'Update successful');
        return redirect()->route('admin.customers.edit', $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy($id)
    {
        $customer = $this->customerRepo->findCustomerById($id);

        $customerRepo = new CustomerRepository($customer);
        $customerRepo->deleteCustomer();

        return redirect()->route('admin.customers.index')->with('message', 'Delete successful');
    }
}
