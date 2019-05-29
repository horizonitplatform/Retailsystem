<?php

namespace App\Console\Commands;

use App\Shop\AttributeValues\AttributeValue;
use App\Shop\AttributeValues\Repositories\AttributeValueRepository;
use App\Shop\ProductAttributes\ProductAttribute;
use App\Shop\Products\Repositories\ProductRepository;
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

class addProductById extends addCategoriesAPI
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:product-by-id {id}';

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
        $id = $this->argument('id');
        $item = SystemLink::where([
            'system_id' => $id,
            'type' => 'product',
        ])->first();
        if($item && !empty($item['link_id'])){
            $item_id = $item['link_id'];
            $client = new Client();
            $api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbyitem?item_id='.$item_id);
            $response = $api_response->getBody()->getContents();
            $data = json_decode($response,true);
            if (!empty($data)) {
                foreach ($data as $item ) {
                    $getProduct = Product::where('sku', $item['item_code'])->first();
                    // check if alredy add product then update
                    if (!empty($getProduct)) {
                        // update
                        echo "UPDATE \r\n";
                        $update = $this->updateProduct($getProduct, $item);
                    } else {
                        // insert
                        echo "INSERT \r\n";
                        $insert = $this->addProduct(New Product, $item);
                    }
                }
            }
        }

    }

    public function addProduct($product,$item) {
        $product->sku = $item['item_code'];
        $product->name = $item['item_name'];
        $product->slug = 'product-';
        $product->description = '';
        $product->cover = '';
        $product->quantity = 0;
        $product->price = 0;
        $product->weight = $item['unit_weight'];
        $product->category_id = $item['category_id'];
        $product->mass_unit = $item['weight_uom_code'];
        $product->save();
        $lastId = $product->id;
        $findProduct = Product::find($lastId);
        $findProduct->slug = 'product-'.$lastId;
        $findProduct->save();
        // inesrt image
        // insert systemlink
        $this->addSystemLink($lastId,$item);
        $this->productBranch($item,$lastId);
        return true;
    }

    public function updateProduct($product,$item) {
        $product->weight = $item['unit_weight'];
        $product->name = $item['item_name'];
        $product->mass_unit = $item['weight_uom_code'];
        $product->save();
        $lastId = $product->id;
        $this->addSystemLink($lastId,$item);
        $this->productBranch($item,$lastId);
        return true;
    }

    public function addSystemLink($lastId,$item) {
        $systemLink = SystemLink::where('system_id', $lastId)->first();
        if(empty($systemLink)) {
            $add = New SystemLink;
            $add->system_id = $lastId;
            $add->link_id   = $item['item_id'];
            $add->type = 'product';
            $add->save();
        }else{
            $systemLink->link_id   = $item['item_id'];
            $systemLink->save();
        }
        return true;
    }

    public function productBranch($item,$lastId) {
        foreach ($item['branches'] as $key =>$value) {
            $productBranch = ProductBranch::where('product_id', $lastId)->where('branch_id', $value['branch_id'])->first();
            $count = count($value['prices']);
            if (empty($productBranch)) {
                // insert
                $productBranchId = $this->addProductBranch(new ProductBranch,$value, $lastId);
            } else {
                //  update
                $productBranchId = $this->addProductBranch($productBranch,$value, $lastId);
            }
            // check UOM
            $this->addUOM($value,$productBranchId, $count, $lastId);

        }
    }

    public function addProductBranch($productBranch,$value, $lastId){
        $productBranch->branch_id = $value['branch_id'];
        $productBranch->qty = $value['qty'];
        $productBranch->product_id = $lastId;
        $productBranch->save();
        $productBranchId = $productBranch->id;
        return $productBranchId;
    }

    public function addUOM($value, $productBranchId, $count, $productId) {
        for ($i=0; $i < $count ; $i++) {
            $pack =  $value['prices'][$i]['uom'];
            $uom = ProductBranchUOM::where('product_branch_id', $productBranchId)->where('uom', $pack)->first();
            $dataUOM = $value['prices'][$i];
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

//        $c = $productRepo->listCombinations();
        $pa = $productRepo->listProductAttributes();
        if($pa){
            return true;
        }

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
