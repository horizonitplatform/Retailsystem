<?php

namespace App\Http\Controllers\Import;

use App\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;

class UsersImportExcel implements ToModel
{
    /**
     * @param array $row
     *
     * @return User|null
     */
    public function model(array $row)
    {   
        dd($row);
        // return new User([
        //    'name'     => $row[0],
        //    'email'    => $row[1], 
        //    'password' => Hash::make($row[2]),
        // ]);
    }
}