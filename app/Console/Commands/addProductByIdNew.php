<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;

use App\Models\ProductCronjob;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\Products\Product;
use DB;
use Image;
use App\Shop\AttributeValues\AttributeValue;
use App\Shop\AttributeValues\Repositories\AttributeValueRepository;
use App\Shop\ProductAttributes\ProductAttribute;
use App\Shop\Products\Repositories\ProductRepository;

class addProductByIdNew extends updateProductNewAPI
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature ='update:new-product-by-id {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add product from API by id';
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

        // $find = ProductBranch::where('branch_id',351)->where('product_id',35)->get();
        // foreach ($find as $item) {
        //     $id[] = $item->id;
        //     $uom = ProductBranchUOM::where('product_branch_id',$item->id)->first();
        //     $uom->delete();
        //     $getPro = ProductBranch::find($item->id);
        //     $getPro->delete();
        // }
        // echo "success";

        $id = $this->argument('id');
        $item = $this->systemLink($id);
        $branchesId = $this->getAllBranchesId();
        if(!empty($item)){
             $getProduct = $this->getProductById($item->link_id,$item->system_id);
        }

        foreach ($branchesId as $branchId) {
            $updateProduct = $this->updateProductById($item->system_id,$item->link_id, $branchId);
        }

    }

    public function systemLink($id) {
        return SystemLink::where([
                    'system_id' => $id,
                    'type' => 'product',
                ])->first();
    }

    public function getProductById($productId, $systemId) {
        $getProductById = ENV('PRODUCT_API').'/products/getbyitems?items=[{"item_id":"'.$productId.'","item_code":""}]';
        $client = new Client();
        $api_response = $client->get($getProductById);
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);

        $product = Product::where('id', $systemId)->first();
        if (empty($product)) {
            $addProductId = $this->addProduct(New Product,$data[0],'addProductById');
        } else {
            //dd($product,$data);
            if(empty($data)){
                $setStatus = Product::find($product->id);
                $setStatus->status = 2;
                $setStatus->save();
                $addProductId = $setStatus->id;
                $data[0]['item_code'] = '';
            } else {
                $addProductId =$this->addProduct($product,$data[0],'addProductById');
            }
        }
        echo "UPDATE ID : ".$addProductId. " PRODUCT : ".$data[0]['item_code']." \r\n";
    }

    public function updateProductById($systemId, $productId, $branchId) {
        $getAllCategoryInBranch = ENV('PRODUCT_API').'/products/getbyitemsbranch?items=[{"item_id":"'.$productId.'","item_code":""}]&branch_id='.$branchId.'&page=1&perpage=10000';
        // $getAllCategoryInBranch = ENV('PRODUCT_API').'/products/getbyitemsbranch?items=[{"item_id":"46448","item_code":""}]&branch_id=351&page=1&perpage=10000';
        $client = new Client();
        $api_response = $client->get($getAllCategoryInBranch);
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);
        // dd($data);
        if (!empty($data)) {
            if (!empty($data[0]['branch'])) {
                $productBranch = ProductBranch::where('product_id', $systemId)->where('branch_id', $branchId)->first();
                 if (empty($productBranch)) {
                // insert
                    $productBranchId = $this->addProductBranch(new ProductBranch,$data[0], $systemId);
                } else {
                    //  update
                    $productBranchId = $this->addProductBranch($productBranch,$data[0], $systemId);
                }
                $count = count($data[0]['branch'][0]['prices']);
                $this->addUOM($data[0],$productBranchId, $count, $systemId);

            }else{
                $productBranch = ProductBranch::where('product_id', $systemId)->where('branch_id', $branchId)->first();
                // $count = count($data[0]['branch'][0]['prices']);
                $type = 'setQtyZero' ; 
                if (empty($productBranch)) {
                    // insert
                    $productBranchId = $this->addProductBranch(new ProductBranch,$data[0], $systemId , $type);
                } else {
                    //  update
                    $productBranchId = $this->addProductBranch($productBranch,$data[0], $systemId , $type);
                }
                // $productBranchId = $this->addProductBranch($productBranch,$data[0], $systemId , $type);
                // $this->addUOM($data[0],$productBranchId, $count, $systemId);
                // dd($productBranch);
            }
        }
    }
}
