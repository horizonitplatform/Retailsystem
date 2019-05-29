<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use GuzzleHttp\Psr7\Stream;

class checkQtyAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:qtyAPI {id}';
    private $orderRepo;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct( OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepo = $orderRepository;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $orderId = $this->argument('id');
        // $productsObject = $orderRepo->listOrderedProducts();
        // $branch_id = $order->branch_id;
        $order = $this->orderRepo->findOrderById($orderId);
        $branch_id = $order->branch_id;
        $orderRepo = new OrderRepository($order);
        $items = $orderRepo->listOrderedProducts();
        foreach ( $items as $product){
            $uom =  $product['description'];
            if(empty($uom)){
                $productBranch = ProductBranch::where([
                    'branch_id' => $branch_id,
                    'product_id' => $product->id,
                ])->first();
                $productUom = ProductBranchUOM::where([
                    'product_branch_id' => $productBranch->id,
                    'um_convert' => 1,
                ])->first();

                $uom = $productUom->uom;
            }
            if($uom == 'BOX'){
                $uom = 'Box';
            }

            $link = SystemLink::where([
                'system_id' => $product->id,
                'type' => 'product',
            ])->first();

            $products[] = [
                'uom' => $uom,
                'qty' => $product->quantity,
                'price' => $product->price,
                'item_id' => $link->link_id
            ];
        }
        $uomMapping = [
         'PACK' =>'PCK',
         'Box' => 'BOX',
         'BOX' => 'BOX',
         'Kilogram' => 'KG',
        ];
        if (empty($products)) {
            return 'error: please try again';
        }
        foreach ($products as &$product){
            if(isset( $uomMapping[ $product['uom'] ])){
               $product['uom'] = $uomMapping[$product['uom']];
            }
        }
        $branchObject = Branch::where([
            'branch_id' => $branch_id,
        ])->first();
        $branchPrefix = explode(' ', $branchObject->name);
        $branchPrefix = $branchPrefix[0];

        // $trnsf = '?doc_no=WEB-MMS-2019011601&pmf_org_id='.$branchObject->branch_id.'&lines=[{"item_id":'.$products[0]['item_id'].',"qty":'.$products[0]['qty'].',"uom":"'.$products[0]['uom'].'"}]';

        // $trnsf = ENV('PRODUCT_API').'/subinvtransfer/subpmf2hoz?doc_no=WEB-MMS-2019011601&pmf_org_id='.$branchObject->branch_id.'&lines=[{"item_id":'.$products[0]['item_id'].',"qty":'.$products[0]['qty'].',"uom":"'.$products[0]['uom'].'"}]';
        // $trnsf = 'http://180.183.247.217:96/crp/horizont/subinvtransfer/subpmf2hoz?doc_no=WEB-MMS-2019011601&pmf_org_id=360&lines=[{"item_id":45958,"qty":1,"uom":"PCK"}]';

        $response = $this->updateQtyApi([
            'doc_no' => 'WEB-'.$branchPrefix.'-'.sprintf('%010d', $order->id).'',
            'pmf_org_id' => $branch_id,
            'lines' => json_encode($products, true),
        ]);
        // $response = $this->updateQtyApi($trnsf);
        if ($response == 'OK') {
            return 'success';
        } else {
            return 'errror';
        }
    }


    public function updateQtyApi($postdata){
       $postdata = http_build_query($postdata);
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents( ENV('PRODUCT_API').'/subinvtransfer/subpmf2hoz', false, $context);
        $fp = fopen(storage_path('logs/subinvtransfer/'.date('Y-m-d').'.log'), 'a');
        fwrite($fp, json_encode([
                'TIME' => date('d-m-Y H:i:s'),
                'postdata' => $postdata,
                'result' => $result,
            ],true).",\r\n");
        fclose($fp);
        return $result;
    }
}
