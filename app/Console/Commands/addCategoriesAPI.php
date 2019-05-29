<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Shop\Categories\Repositories\CategoryRepository;
use App\Shop\Categories\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Shop\Categories\Requests\CreateCategoryRequest;
use App\Shop\Categories\Requests\UpdateCategoryRequest;
use App\Branch;
use App\Models\Category;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\Products\Product;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use DB;
use Image;
use File;

class addCategoriesAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add categorie from API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $request = $client->get(ENV('PRODUCT_API').'/categories/getall');
        $response = $request->getBody()->getContents();
        $responses = json_decode($response);
        // dd($responses);
        foreach($responses as $res){
            $slug = str_slug($res->category_name, '-');
            $checkCategory = SystemLink::where('link_id' , $res->category_id)->where('type' , 'category')->first();
            if(empty($checkCategory)){
                $newCategory = new Category;
                $newCategory->name =  $res->description;
                $newCategory->slug = $slug;
                $newCategory->description =  $res->description;
                $newCategory->status =  1;
                $newCategory->parent_id =  1;
                $path = $res->img;
                $filename = basename($path);
                $newCategory->cover =  "categories/" .$filename;

                if(!empty($path)){
                    $pathImg = storage_path('app/public/categories/' . $filename);
                    if ($newCategory->cover == $filename) {
                        if(File::exists($pathImg)) {
                            File::delete($pathImg);
                        }
                    }
                    Image::make($res->img)->save($pathImg);
                }
                if($newCategory->save()){
                    $systemLink = SystemLink::where('system_id', $newCategory->id)->where('type', 'category')->first();
                    if(empty($systemLink)) {
                        $add = New SystemLink;
                        $add->system_id = $newCategory->id;
                        $add->link_id   = $res->category_id;
                        $add->type = 'category';
                        $add->save();
                    }else{
                        $systemLink->system_id = $newCategory->id;
                        $systemLink->link_id   = $res->category_id;
                        $systemLink->type = 'category';
                        $systemLink->save();
                    }
                }

            }else{
                $updateCategory = Category::where('id' , $checkCategory->system_id)->first();
                $updateCategory->name =  $res->description;
                $updateCategory->description =  $res->description;
//                $updateCategory->status =  1;
                $updateCategory->parent_id =  1;
                $path = $res->img;
                $filename = basename($path);
                $updateCategory->cover =  "categories/" .$filename;

                if(!empty($path)){
                    $pathImg = storage_path('app/public/categories/' . $filename);
                    if ($updateCategory->cover == $filename) {
                        if(File::exists($pathImg)) {
                            File::delete($pathImg);
                        }
                    }
                    Image::make($res->img)->save($pathImg);
                }
                $updateCategory ->save();
            }
        }
        return  'success';

    }


}
