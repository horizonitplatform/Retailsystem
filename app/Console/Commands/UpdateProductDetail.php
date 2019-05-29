<?php

namespace App\Console\Commands;

use App\Shop\Categories\Category;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\Products\Product;
use DB;
use Image;
use File;

class UpdateProductDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:product';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update image and productname';

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
        $api_response = $client->get('http://180.183.247.217:96/prod/horizont/productdetails/get');
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);
        foreach ($data as $item) {
            $getProduct = Product::where('sku', $item['item_code'])->first();
            if (!empty($getProduct)) {
                if ($getProduct->sku == $item['item_code']) {
                    $getProduct->name =  $item['item_name'];
                    $path = $item['img'];
                    $filename = basename($path);
                    $pathImg = storage_path('app/public/products/' . $filename);
                    if(!File::exists($pathImg)) {
                        Image::make($path)->save($pathImg);
                        $getProduct->cover = 'products/'.$filename;
                    }else{
                        if ($getProduct->cover !== $filename) {
                            File::delete($pathImg);
                            Image::make($path)->save($pathImg);
                            $getProduct->cover = 'products/'.$filename;
                        }
                    }
                    $getProduct->save();
                }
            }
        }
    }
}
