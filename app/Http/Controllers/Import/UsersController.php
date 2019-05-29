<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Import\UsersImportExcel;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Storage;

class UsersController extends Controller 
{
    public function import() 
    {
        $path = 'excel/Customer_Priority1.xlsx' ;
        $content = Storage::disk('public')->get($path);
        // dd($content);
        Excel::import(new UsersImportExcel,$content );
        
        // return redirect('/')->with('success', 'All good!');
    }
}