<?php

namespace App\Console\Commands;

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

class updateProductByCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:product-by-category';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add product from API';

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
        $api_response = $client->get('http://180.183.247.217:96/prod/horizont/products?page=1&perpage=10000');
        $response = $api_response->getBody()->getContents();
        $data = json_decode($response,true);
        foreach ($data as $category){
            $systemLink = SystemLink::where([
                'link_id' => $category['category_id'],
                'type' => 'category',
            ])->first();

            foreach ($category['category'] as $item){
                $product = Product::where(['sku' => $item['item_code']])->first();
                $product->weight = $item['unit_weight'];
                $product->quantity = $item['qty'];
                $product->price = $item['price'];
                $product->status = 1;
                $product->save();

                $productRepo = new ProductRepository($product);
                $productRepo->syncCategories([$systemLink['system_id']]);

            }
        }

    }

}
