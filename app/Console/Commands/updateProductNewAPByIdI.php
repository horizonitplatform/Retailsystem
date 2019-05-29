<?php

namespace App\Console\Commands;

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
use File;
use Image;
class updateProductNewAPIById extends updateProductNewAPI
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:productAPIById';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update product from new API';

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

}
//
