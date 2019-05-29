<?php

namespace App\Http\Controllers\Front\Payments;

use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\OrderRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Routing\Controller as BaseController;

class PaymentCreditResponseController extends BaseController
{
    public function BBL()
    {
        $req_dump = json_encode([
            'TIME' => date('d-m-Y H:i:s'),
            '$_POST' => $_POST,
        ], TRUE).",\r\n";
        $fp = fopen(storage_path('logs/payment/'.date('Y-m-d').'.log'), 'a');
        fwrite($fp, $req_dump);
        fclose($fp);
        echo 'OK';

        $successCode = isset($_POST['successcode']) ? $_POST['successcode'] : '-1' ;
        $orderRef = isset($_POST['Ref']) ? $_POST['Ref'] : '-1' ;
        $amt = isset($_POST['Amt']) ? intval($_POST['Amt']) : 0;
        if($successCode == '0'){
            // Approve
            $orderRef = str_replace('TEST','',$orderRef);
            $orderRef = intval($orderRef);

            $orderRepo = new OrderRepository(new Order);
            $order = $orderRepo->findOrderById($orderRef);

            if($order ){
                $orderRepo = new OrderRepository($order);
                $orderRepo->updateOrder([
                    'total_paid' => $amt,
                    'order_status_id' => 1,
                ]);

                // UPDATE API
                $products = [];

                $branch_id = $order->branch_id;
                $productsObject = $orderRepo->listOrderedProducts();

                foreach ( $productsObject as $product){

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

                $courier = $order->courier()->get()->toArray()[0];

                $shippingCost = $courier['is_free'] ? 0 : intval($courier['cost']);

                $this->updateQtyApi([
                   'doc_no' => 'WEB-'.$branchPrefix.'-'.sprintf('%010d',$order->id).'',
                   'pmf_org_id' => $branch_id,
                   'billed_delivery' => $shippingCost,
                   'billed_discount' => intval($order->discounts),
                   'json_selling' => json_encode($products,true),
                ]);

//                foreach ( $productsObject as $product){
//                    ob_start();
//                    \Artisan::call('update:product-by-id', [
//                        'id' => $product->id
//                    ]);
//                    $result = ob_get_clean();
//                }
            }
        } else {
            // Reject
            if($orderRef !== '-1'){
                $orderRepo = new OrderRepository(new Order);

                $order = $orderRepo->findOrderById($orderRef);
                if($order){
                    $orderRepo = new OrderRepository($order);
                    $orderRepo->updateOrder([
                        'order_status_id' => 3,
                    ]);
                }
            }

        }

    }

    public function updateQtyApi($param){
       $postdata = http_build_query($param);
       $opts = array('http' =>
           array(
               'method'  => 'POST',
               'header'  => 'Content-type: application/x-www-form-urlencoded',
               'content' => $postdata
           )
       );
       $context  = stream_context_create($opts);
       $result = file_get_contents(ENV('PRODUCT_API').'/transaction/sell_issue', false, $context);
       $fp = fopen(storage_path('logs/sell_issue/'.date('Y-m-d').'.log'), 'a');
       fwrite($fp, json_encode([
           'TIME' => date('d-m-Y H:i:s'),
           'param' => $param,
           'result' => $result,
       ],true).",\r\n");
       fclose($fp);
       return $result;
    }
}