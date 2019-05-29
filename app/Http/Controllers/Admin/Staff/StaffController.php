<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Shop\Admins\Requests\CreateEmployeeRequest;
use App\Shop\Admins\Requests\UpdateEmployeeRequest;
use App\Shop\Employees\Repositories\EmployeeRepository;
use App\Shop\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Shop\Roles\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Branch;

class StaffController extends Controller
{   
     /**
     * @var EmployeeRepositoryInterface
     */
    private $employeeRepo;
    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepo;

    /**
     * EmployeeController constructor.
     *
     * @param EmployeeRepositoryInterface $employeeRepository
     * @param RoleRepositoryInterface $roleRepository
     */
    public function __construct(
        EmployeeRepositoryInterface $employeeRepository,
        RoleRepositoryInterface $roleRepository
    ) {
        $this->employeeRepo = $employeeRepository;
        $this->roleRepo = $roleRepository;
    }

    public function index()
    {
        $lists = $this->employeeRepo->listEmployees('created_at', 'desc');
        $staff_id = DB::table('roles')->where('name', 'staff')->first()->id;
        $staff_lists = array();
        foreach($lists as $list){
            $role = $list->roles()->pluck('role_id')->all();
            $list['role'] = $role['0']; 
            if($role['0'] == $staff_id ){
                array_push($staff_lists  , $list);
            }
        }
        
        return view('admin.staff.list' ,[
            'staff_lists' => $this->employeeRepo->paginateArrayResults($staff_lists,10),
        ]);
    }

    public function create()
    {
        $branchs = Branch::all();
        $role = $this->roleRepo->findRoleByName('staff');
        return view('admin.staff.create' ,[
            'branchs' => $branchs , 
            'role' => $role ,
        ]);
    }

    public function store(CreateEmployeeRequest $request)
    {
        $employee = $this->employeeRepo->createEmployee($request->all());
        
        if ($request->has('role')) {
            $employeeRepo = new EmployeeRepository($employee);
            $employeeRepo->syncRoles([$request->input('role')]);
        }

        return redirect()->route('admin.staff.index');
    }

    public function edit(int $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $role = $this->roleRepo->findRoleByName('staff');
        $isCurrentUser = $this->employeeRepo->isAuthUser($employee);
        $branchs = Branch::all();
                
        return view(
            'admin.staff.edit',
            [
                'employee' => $employee,
                'role' => $role,
                'isCurrentUser' => $isCurrentUser,
                'selectedIds' => $employee->roles()->pluck('role_id')->all(),
                'branchs' => $branchs,
            ]
        );
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $isCurrentUser = $this->employeeRepo->isAuthUser($employee);

        $empRepo = new EmployeeRepository($employee);
        $empRepo->updateEmployee($request->except('_token', '_method', 'password'));

        if ($request->has('password') && !empty($request->input('password'))) {
            $employee->password = Hash::make($request->input('password'));
            $employee->save();
        }

        if ($request->has('roles') and !$isCurrentUser) {
            $employee->roles()->sync($request->input('roles'));
        } elseif (!$isCurrentUser) {
            $employee->roles()->detach();
        }

        return redirect()->route('admin.staff.edit', $id)
            ->with('message', 'Update successful');
    }

    public function destroy(int $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $employeeRepo = new EmployeeRepository($employee);
        $employeeRepo->deleteEmployee();
        
        return redirect()->route('admin.staff.index')->with('message', 'Delete successful');
    }
    public function registerStaff()
    {
        $branchs = Branch::all();
        return view('auth.staff.register' , ['branchs' => $branchs ]);
    }

    public function storeStaff(Request $request)
    {

        $messages = [
            'email.unique'    => 'มีการใช้  email นี้แล้ว',
            'password.confirmed' => 'รหัสผ่าน กับ รหัสผ่านยืนยัน ไม่เหมือนกัน',
            'password.min' => 'รหัสผ่านที่ใช้ต้องมีอย่างน้อย 8 หลัก',
            'phone.between' => 'หมายเลขโทรศัพท์ต้องมีจำนวน 9-10 หลัก'
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|',
            'email' => 'required|string|email|max:50|unique:customers,email|unique:employees,email',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'required|string|between:9,10'
        ], $messages);

        if ($validator->fails()) {
            // dd($request->all() , $validator->errors());
            return back()->withErrors($validator->errors());
        }else{
            $employee = $this->employeeRepo->createEmployee($request->all());
        
            if ($request->has('role')) {
                $employeeRepo = new EmployeeRepository($employee);
                $employeeRepo->syncRoles([$request->input('role')]);
            }
        }

        return back()->with('success' , 'Register succes');
    }

    public function staffRequest()
    {
        $topic ='รายชื่อคำร้องขอเป็นสมาชิก';
        $lists = $this->employeeRepo->listEmployees('created_at', 'desc')->where('status' , 0);
        $staff_id = DB::table('roles')->where('name', 'staff')->first()->id;
        $staff_lists = array();
        foreach($lists as $list){
            $role = $list->roles()->pluck('role_id')->all();
            $list['role'] = $role['0']; 
            if($role['0'] == $staff_id ){
                array_push($staff_lists  , $list);
            }
        }
        
        return view('admin.staff.list' ,[
            'staff_lists' => $this->employeeRepo->paginateArrayResults($staff_lists,10),
            'topic' => $topic,
        ]);
    }

    public function approve($id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $employee->status = 1 ; 
        $employee->save() ; 

        return back()->with('success' , 'อนมุัติผู้ใช้เรียบร้อยแล้ว');
    }
}
