<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Branch;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class CategoryController extends Controller
{

   function index()
    {
        $client = new Client();
        $request = $client->get('http://3bbfc.ddns.cyberoam.com:96/crp/horizont/category/get');
        $response = $request->getBody()->getContents();
        // print_r($response);
        $responses = json_decode($response);
        // dd($responses);
        foreach($responses as $res){
            $checkBranch = Branch::where('branch_id' , $res->branch_id)->first();
            if(!$checkBranch){
                $newBranch = new Branch;
                $newBranch->name =  $res->branch_name;
                $newBranch->branch_id =  $res->branch_id;
                $newBranch ->save();
            }
        }
        return  'success';
    } 
}
