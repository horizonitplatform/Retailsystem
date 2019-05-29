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
use DB;
use App\Shop\AttributeValues\Repositories\AttributeValueRepository;
use App\Shop\ProductAttributes\ProductAttribute;
use App\Shop\Products\Repositories\ProductRepository;
use App\Models\ProductCronjob;
use App\Models\Category;
use File;
use Image;

class updateProductNewAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:productAPI';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update product from new API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->getProductInBranch = array();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // add & update product
        $this->getProduct();
        // get branch Id
        $getAllBranchesId = $this->getAllBranchesId();

         // get all category in that branch
        foreach ($getAllBranchesId as $branch_id) {
            $getAllCategoryInBranch = $this->getAllCategoryInBranch($branch_id);
        }

        // set zero qty if 0 stock
        $this->setZeroQty($this->getProductInBranch, $getAllBranchesId);
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
            echo "UPDATE ID : ".$addProductId. " PRODUCT : ".$item['item_code']." \r\n";

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
        if(empty($type) || $type = 'addProductById'){
            $this->updateCatPro($item['category_id'],$product->id);
        }
        $this->addSystemLink($product->id,$item);
        return $product->id;
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
            echo "Add systemLink ,";
        } else {
            dd('กรุณารัน artisan add:categories ก่อน');
        }
        return true;
    }
    public function getAllCategoryId(){
        $getAllCategory = ENV('PRODUCT_API').'/categories/getall';
        $client = new Client();
        $api_response = $client->get($getAllCategory);
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);
        $categoriesId = [];
        foreach ($data as $item) {
            $categoriesId[] = $item['category_id'];
        }
        return $categoriesId;
    }

     public function getAllBranchesId(){
        $branches = Branch::all();
        if (empty($branches)) {
            die('กรุณาเพิ่มสาขาก่อน');
            exit;
        }
        $branchIds = [];
        foreach ($branches as $branch) {
            $branchIds[] = $branch->branch_id;
        }
        return $branchIds;
    }

    public function getAllCategoryInBranch($branch_id) {

        // get all category
        $getAllCategoryId = $this->getAllCategoryId();
         // get All Branch
        $checkItem = $this->getProductInCatBranch($branch_id,$getAllCategoryId);
    }

    public function getProductInCatBranch($branch_id,$getAllCategoryId) {
        foreach ($getAllCategoryId as $categoryId) {
        //    $getAllCategoryInBranch = ENV('PRODUCT_API').'products/getbycatbranch?category_id='.$categoryId.'&branch_id='.$branch_id . '&page=1&perpage=1000';
           $getAllCategoryInBranch = ENV('PRODUCT_API').'/products/getbycatbranch?category_id='.$categoryId.'&branch_id='.$branch_id . '&perpage=1000&page=1';

           $client = new Client();
           $api_response = $client->get($getAllCategoryInBranch);
           $response = $api_response->getBody()->getContents();
           $data = json_decode($response,true);

            // dd($data);
            $this->checkProductInBranch($branch_id,$data);
        }
    }

    public function checkProductInBranch($branch_id,$data) {
        foreach ($data as $item) {
            if(!empty($item['branch'])) {
                $product = Product::where('sku', $item['item_code'])->first();
                if (empty($product)) {
                    // insert new product
                    $this->updateProduct(New Product,$item);
                }else{
                    // update new product
                    $this->updateProduct($product,$item);
                }
            }
        }
    }

    public function updateProduct($product, $item) {
        $product_id = $product->id;
        $this->updateProductBranch($item, $product_id);
        // echo "UPDATE branch_id: ".$branch_id." item_id:".$item['item_id'].":".$item['item_name']." \r\n";
    }

    public function updateProductBranch($item, $product_id) {
        $productBranch = ProductBranch::where('product_id', $product_id)->where('branch_id',  $item['branch'][0]['branch_id'])->first();
        if(array_key_exists($item['branch'][0]['branch_id'], $this->getProductInBranch)) {
            // $this->getProductInBranch[$item['branch'][0]['branch_id']] = ['product_id' => $product_id];
            array_push( $this->getProductInBranch[$item['branch'][0]['branch_id']], $product_id);
        } else {
            $this->getProductInBranch[$item['branch'][0]['branch_id']] = [0 => $product_id];
            // $this->getProductInBranch[$item['branch'][0]['branch_id']] = [
            //     'product_id' => $product_id
            // ];
            // array_push( $this->getProductInBranch[$item['branch'][0]['branch_id']], $product_id);
        }


        // $productBranch = $item['branch'][0]['branch_id'];
        if (empty($productBranch)) {
            // insert
            $productBranchId = $this->addProductBranch(new ProductBranch,$item, $product_id);
        } else {
            //  update
            $productBranchId = $this->addProductBranch($productBranch,$item, $product_id);
        }
        // check uom
        $count = count($item['branch'][0]['prices']);
        $this->addUOM($item,$productBranchId, $count, $product_id);
    }

    public function addProductBranch($productBranch,$item, $product_id , $type = ''){
        if($type == 'setQtyZero'){
            $productBranch->qty = 0;
            $productBranch->save();
            $productBranchId = $productBranch->id;
            echo "UPDATE ProductBranchID : ".$productBranchId." BranchID : ".$productBranch->branch_id. " itemCode : ".$item['item_code']. "Set Qty = 0 " . "\r\n";
        }else{
            $productBranch->branch_id = $item['branch'][0]['branch_id'];
            $productBranch->qty = $item['branch'][0]['qty'];
            $productBranch->product_id = $product_id;
            $productBranch->save();
            $productBranchId = $productBranch->id;
            // echo $productBranch->id.'/:'.$item['item_code'];
            echo "UPDATE ProductBranchID : ".$productBranchId." BranchID : ".$productBranch->branch_id. " itemCode : ".$item['item_code']." \r\n";
        }
        return $productBranchId;
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

     public function addUOM($item, $productBranchId, $count, $productId) {

        for ($i=0; $i < $count ; $i++) {
            $pack =  $item['branch'][0]['prices'][$i]['uom'];
            $uom = ProductBranchUOM::where('product_branch_id', $productBranchId)->where('uom', $pack)->first();
            $dataUOM = $item['branch'][0]['prices'][$i];
            if (empty($uom)) {
                $uom = new ProductBranchUOM;
                $this->addDataUOM($uom, $dataUOM,$productBranchId,$count, $productId, $i);

            } else {
                $this->addDataUOM($uom, $dataUOM,$productBranchId,$count, $productId, $i);
            }
        }
    }

    public function addDataUOM($uom,$dataUOM,$productBranchId,$count, $productId, $index) {
        $uom->price_list = $dataUOM['price_list'];
        $uom->product_branch_id = $productBranchId;
        $uom->uom = $dataUOM['uom'];
        $uom->price = (double)$dataUOM['price'];
        $uom->um_convert = $dataUOM['um_convert'];
        $uom->save();
        if($count > 1){
            $this->addAttr($uom,$dataUOM,$productBranchId, $productId, $index);
        }
        return true;
    }

     public function addAttr($uom, $dataUOM, $productBranchId, $productId, $index){
        $productAttr = AttributeValue::where([
            'value' => strtoupper($dataUOM['uom']),
            'attribute_id' => 1,
        ])->first();
        if(!$productAttr){
            $productAttr = new AttributeValue();
            $productAttr->value = strtoupper($dataUOM['uom']);
            $productAttr->attribute_id = 1;
            $productAttr->save();
        }

        $this->addAttrValue($productAttr, $uom, $dataUOM, $productId, $index);

    }

    public function addAttrValue($productAttr, $uom, $dataUOM, $productId, $index){
        $productRepo = new ProductRepository(new Product);
        $product = $productRepo->findProductById($productId);
        $productRepo = new ProductRepository($product);
        $attributeValueRepository = new AttributeValueRepository($productAttr);

        //  $c = $productRepo->listCombinations();
        $pa = $productRepo->listProductAttributes();
        // if($pa){
        //     return true;
        // }
        $checkProductAttribute = count($pa->where('product_id', $productId)->where('default' , $index == 0 ));
        if($checkProductAttribute > 0) {
            $data = DB::table('product_attributes')->where('product_id', $productId)->get();
            foreach($data as $getItem) {
                $test[] = $dataUOM['price'];
                $dataUOM['uom'] = strtoupper($dataUOM['uom']);
                if($getItem->default == 1 && $dataUOM['uom'] == 'PACK'){
                    $getUom = DB::table('product_attributes')->where('product_id', $productId)->where('default',1)->update(['price' => $dataUOM['price']]);
                }
                if($getItem->default == 0 && $dataUOM['uom'] == 'BOX' ){
                    $getUom = DB::table('product_attributes')->where('product_id', $productId)->where('default',0)->update(['price' => $dataUOM['price']]);
                }
            }
        }
        if($checkProductAttribute ==  0 ){
            $productAttribute = $productRepo->saveProductAttributes(
                new ProductAttribute([
                    'quantity' => 0,
                    'price' => (double)$dataUOM['price'],
                    'default' => $index == 0
                ])
            );

            // save the combinations
            collect([$productAttr->id])->each(function ($attributeValueId) use ($productRepo, $productAttribute, $attributeValueRepository) {
                $attribute = $attributeValueRepository->find($attributeValueId);
                return $productRepo->saveCombination($productAttribute, $attribute);
            })->count();
        }

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

    public function setZeroQty($getAllBranchesProdId, $getBranch) {
        foreach ($getBranch as $branch_id) {
            if (!empty($getAllBranchesProdId[$branch_id])) {
                $query = ProductBranch::where('branch_id', $branch_id)->whereNotIn('product_id',$getAllBranchesProdId[$branch_id])->get();
                foreach ($query as $data) {
                    $update = ProductBranch::find($data->id);
                    $update->qty = 0;
                    $update->save();
                    echo "UPDATE ID : ".$data->id. " Branch : ".$data->branch_id." Set Qty = 0 \r\n";
                }
            }else {
                $query = ProductBranch::where('branch_id', $branch_id)->update(['qty' => 0]);
                echo  "Branch : ".$branch_id." Set Qty = 0 \r\n";
            }
        }
    }


}
