<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Shop\Products\Product;
use App\Models\SystemLink;
use App\Shop\AttributeValues\AttributeValue;
use App\Shop\AttributeValues\Repositories\AttributeValueRepository;
use App\Shop\ProductAttributes\ProductAttribute;
use App\Shop\Products\Repositories\ProductRepository;
use App\Models\ProductCronjob;
use App\Models\Category;
use File;
use Image;

class updateNewProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature ='update:newProductsOnly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'uppdate product only from API';
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
        $this->getProduct();
    }

    public function getProduct() {
        $addProduct = ENV('PRODUCT_API').'/products/getall?page=1&perpage=10000';
        $client = new Client();
        $api_response = $client->get($addProduct);
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);
        $i = 0;
        foreach ($data as $item) {
            $product = Product::where('sku', $item['item_code'])->first();
            if (empty($product)) {
                $addProductId = $this->addProduct(New Product,$item);
            } else {
                $addProductId =$this->addProduct($product,$item);
            }
            // echo "UPDATE ID : ".$addProductId. " PRODUCT : ".$item['item_code']." \r\n";

        }
    }

    public function addProduct($product, $item,$type="") {
        $product->name = $item['item_name'];
        $product->sku = $item['item_code'];
        $file = $this->uploadFile($item['default_image'],$product);
        $product->cover = $file;
        $product->description = $item['description'];
        $product->price = $item['price'];
        $product->quantity = 0;
        $product->weight = $item['unit_weight'];
        $product->mass_unit = $item['weight_uom_code'];
        $product->slug = 'product-'.$product->id;
        $product->category_id = $item['category_id'];
        $product->save();
        if(empty($type)){
            $this->updateCatPro($item['category_id'],$product->id);
        }
        $this->addSystemLink($product->id,$item);
        return $product->id;
    }

    public function uploadFile($file,$product) {
        // $file = 'http://www.pmart.co.th/img/item/xhdpi/260404009_0.jpg';
        if (!empty($file)) {
            $path = $file;
            $filename = basename($path);
            $pathImg = storage_path('app/public/products/' . $filename);
            if(!File::exists($pathImg)) {
                Image::make($path)->save($pathImg);
                $product->cover = 'products/'.$filename;
            }else{
                if ($product->cover !== $filename) {
                    File::delete($pathImg);
                    Image::make($path)->save($pathImg);
                    $product->cover = 'products/'.$filename;
                }
            }
            $cover = 'products/'.$filename;
        } else {
            $cover = '';
        }
        return $cover;
    }

    public function updateCatPro($catId,$proId){
        $systemLink = SystemLink::where([
                        'link_id' => $catId,
                        'type' => 'category',
                    ])->first();
        if ($systemLink) {
            $product = Product::find($proId);
            $productRepo = new ProductRepository($product);
            $productRepo->syncCategories([$systemLink['system_id']]);
            // echo "Add systemLink ,";
        } else {
            dd('กรุณารัน artisan add:categories ก่อน');
        }
        return true;
    }

    public function addSystemLink($product_id,$item) {
        $systemLink = SystemLink::where('system_id', $product_id)->where('type','product')->first();
        if(empty($systemLink)) {
            $add = New SystemLink;
            $add->system_id = $product_id;
            $add->link_id   = $item['item_id'];
            $add->type = 'product';
            $add->save();
        }else{
            $systemLink->link_id   = $item['item_id'];
            $systemLink->save();
        }
        return true;
    }

}
