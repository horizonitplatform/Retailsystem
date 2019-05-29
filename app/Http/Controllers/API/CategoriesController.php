<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Branch;
use App\Models\Category;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;


class CategoriesController extends Controller
{

   function index()
    {
        $client = new Client();
        $request = $client->get('http://180.183.247.217:96/prod/horizont/categories/getcatall');
        $response = $request->getBody()->getContents();
        // print_r($response);
        $responses = json_decode($response);
        // dd($responses);
        foreach($responses as $res){
            $slug = str_slug($res->category_name, '-');
            $checkCategory = Category::where('slug' , $slug)->first();
            // $checkCategory = Branch::where('branch_id' , $res->branch_id)->first();
            if(!$checkCategory){
                $newCategory = new Category;
                $newCategory->name =  $res->category_name;
                $newCategory->slug = $slug;
                $newCategory->description =  $res->description;
                $newCategory->status =  0;
                $newCategory->parent_id =  0;
                $newCategory ->save();
            }
        }
        return  'success';
    } 
}
