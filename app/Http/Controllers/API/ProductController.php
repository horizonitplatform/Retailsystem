<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use App\Branch;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Models\ProductCronjob;
use App\Shop\Products\Product;
use DB;
use Image;
use File;

class ProductController extends Controller
{
	public function index() {
		$branches = Branch::all();
		if (empty($branches)) {
			die('กรุณาเพิ่้มสาขาก่อน');
			exit;
		}
		foreach ($branches as $branch) {
			// $branchIds[] = $branch->branch_id;
			$client = new Client();
			$api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbybranch?perpage=10&page=1&branch_id='.$branch->branch_id);
			$response = $api_response->getBody()->getContents();
			$data = json_decode($response,true);
			if (count($data) > 0) {
				$branchIds[] = $branch->branch_id;
			}
		}
		dd($branchIds);
		// foreach ($branchIds as $bIds) {
		// 	$i = 0;
		// 	while (true) {
		// 	$i++;
		// 		$client = new Client();
		// 		$api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbybranch?perpage=10&page='.$i.'&branch_id='.$bIds);
		// 		$response = $api_response->getBody()->getContents();
		// 		$data = json_decode($response,true);
		// 		if (count($data) > 0) {
		// 			$api[] = 'http://180.183.247.217:96/prod/horizont/products/getbybranch?perpage=10&page='.$i.'&branch_id='.$bIds;
		// 			$lastPage = $i;
		// 			$lastbranch = $bIds;
		// 		} else {
		// 			break;
		// 		}
		// 	}
		// }
		// foreach ($branchIds as $branchId) {
			// loop
			$i = 0;
			while (true) {
				$i++;
				$client = new Client();
				$api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbybranch?perpage=5&page='.$i.'&branch_id=345');
				$response = $api_response->getBody()->getContents();
				$data = json_decode($response,true);
				if (!empty($data)) {
					// check error
					// DB::beginTransaction();
					// try{
						foreach ($data as $item ) {
							$getProduct = Product::where('sku', $item['item_code'])->first();
							// check if alredy add product then update
							if (!empty($getProduct)) {
								// update
								$product = Product::where('sku',$item['item_code'])->first();
								$update = $this->addProduct($product, $item);
							} else {
								// insert
								$product = New Product;
								$insert = $this->addProduct($product, $item);
							}
						}
					// 	DB::commit();
					// } catch(Exception $e){
					//   DB::rollback();
					//   die('something error:13123');
					// }
		        // $add = new ProductCronjob;
		        // $add->current_branch_id = $branchId;
		        // $add->last_branch_id = $lastbranch;
		        // $add->cueent_page = $i;
		        // $add->last_page = $lastPage;
		        // $add->save();

				} else {
					break;
				}
			}

		// }

		return redirect()->route('update.products');
	}

	public function addProduct($product,$item) {
		$product->sku = $item['item_code'];
		$product->name = $item['item_code'];
		$product->slug = 'product-';
		$product->description = 'ss';
		$product->cover = '';
		$product->quantity = 0;
		$product->price = 0;
		$product->weight = $item['unit_weight'];
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

	public function addSystemLink($lastId,$item) {
		$systemLink = SystemLink::where('system_id', $lastId)->first();
		if(empty($systemLink)) {
			$add = New SystemLink;
			$add->system_id = $lastId;
			$add->link_id	= $item['item_code'];
			$add->type = 'product';
			$add->save();
		}
		return true;
	}

	public function productBranch($item,$lastId) {
		foreach ($item['branches'] as $key =>$value) {
			$productBranch = ProductBranch::where('product_id', $lastId)->where('branch_id', $value['branch_id'])->first();
			$count = count($value['prices']);
			if (empty($productBranch)) {
				// insert
				$productBranch = new ProductBranch;
				$productBranchId = $this->addProductBrach($productBranch,$value, $lastId);
			} else {
				//  update
				$productBranchId = $this->addProductBrach($productBranch,$value, $lastId);
			}
			// check UOM
			$this->addUOM($value,$productBranchId, $count);

		}
	}

	public function addProductBrach($productBranch,$value, $lastId){
		$productBranch->branch_id = $value['branch_id'];
		$productBranch->qty = $value['qty'];
		$productBranch->product_id = $lastId;
		$productBranch->save();
		$productBranchId = $productBranch->id;
		return $productBranchId;
	}

	public function addUOM($value, $productBranchId, $count) {
		for ($i=0; $i < $count ; $i++) {
			$pack =  $value['prices'][$i]['uom'];
			$uom = ProductBranchUOM::where('product_branch_id', $productBranchId)->where('uom', $pack)->first();
			$dataUOM = $value['prices'][$i];
			if (empty($uom)) {
				$uom = new ProductBranchUOM;
				$this->addDataUOM($uom, $dataUOM,$productBranchId);

			} else {
				$this->addDataUOM($uom, $dataUOM,$productBranchId);
			}
		}
	}

	public function addDataUOM($uom,$dataUOM,$productBranchId) {
		$uom->price_list = $dataUOM['price_list'];
		$uom->product_branch_id = $productBranchId;
		$uom->uom = $dataUOM['uom'];
		$uom->price = (double)$dataUOM['price'];
		$uom->um_convert = $dataUOM['um_convert'];
		$uom->save();
		return true;
	}

	// update image
	public function updateImage() {
		// update product name
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
					$pathImg = public_path('images/product/' . $filename);
					if ($getProduct->cover == $filename) {
						if(File::exists($pathImg)) {
							File::delete($pathImg);
						}
					}
					Image::make($path)->save($pathImg);
					$getProduct->cover = $filename;
					$getProduct->save();
					$getProduct->save();
				}
			}
		}
		return  'success';

	}
}
