<?php

namespace App\Http\Controllers\Front;

use App\Shop\Categories\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\Products\Transformations\ProductTransformable;
use App\Shop\Categories\Category;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Categories\Repositories\CategoryRepository;
use Illuminate\Support\Facades\DB;
use App\Banner;
use Auth;
use App\Branch;
use App\BranchZipcode;
use App\Shop\Products\Product;
use App\Models\ProductBranch;
use GuzzleHttp\Client;
use CyrildeWit\EloquentViewable\Support\Period;

class HomeController extends Controller
{
    use ProductTransformable;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepo;

    /**
     * HomeController constructor.
     *
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(ProductRepositoryInterface $productRepository, CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepo = $categoryRepository;
        $this->productRepo = $productRepository;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        // $cat1 = $this->categoryRepo->findCategoryById(15);
        // $cat2 = $this->categoryRepo->findCategoryById(16);

        $categoryRepo = new CategoryRepository(new Category());
        $lists = $categoryRepo->listCategories('created_at', 'asc', [1, 10])->where('slug', '!=', 'others');
        $getProduct = $this->getProduct();
        

        foreach ($lists as $key => $list) {
            $id = $list->id;
            $total = DB::table('category_product')->where('category_product.category_id', '=', $id)->count('category_product.category_id');
            $list['total'] = $total;
        }

        $products = array();
        $recomments = Product::orderByViews('desc')->limit(10)->get();
    
        // foreach ($lists as $key => $list) {
        //     $repo = new CategoryRepository($list);
        //     $product = $repo->findProducts()->where('status', 1)->all();
        //     // $product = $this->getProductCategory($list);
        //     // dd($product);
        //     $products[$key] = $product;
        //     $randomProduct = $this->randomGen(0, count($product), 3);
        //     $list['popular'] = $randomProduct;   
        // }
       
        $banners = Banner::where('type', 'banner')->get();
        $event = Banner::where('type', 'event')->first();

        return view('frontend.index', compact('lists', 'products', 'banners', 'event', 'getProduct' , 'recomments'));
    }

    public function getProduct() {
        if(Auth::check()){
            $user = Auth::user();
            $zipcode = $user->getAttribute('zipcode');
            $branchZip = BranchZipcode::where([
                'zipcode' => $zipcode
            ])->first();
        }
        $proBranch = ProductBranch::select('products.*', 'product_branch_uom.price as price_product' , 'product_branch.qty as qty' )
                ->leftJoin('products', 'product_branch.product_id', '=', 'products.id')
                ->leftJoin('product_branch_uom', 'product_branch_uom.product_branch_id', '=', 'product_branch.id')
                // ->where('cover','!=','')
                ->where('category_id' , '!=' , 1)
                ->where('status' , 1)
                ->where('product_branch.qty'  , '!=' , 0)
                ->groupBy('product_branch.product_id')
                ->orderByRaw("RAND()")
                ->limit(60);

        if(!empty($branchZip)){
            $branch = Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first();
            $branch_id = $branch->getAttribute('branch_id');
            $proBranch = $proBranch->where('product_branch.branch_id' , $branch_id) ; 
            
        }
        // dd($proBranch->get(['sku'])->toArray());
        // $param = [] ;
        // foreach ($proBranch->get() as $item){
        //     $param[] ='{"item_id":"","item_code":"' .$item->sku . '"}'; 
        // }
        // $items_code = implode(',' , $param);
        // // dd(ENV('PRODUCT_API').'/products/getbyitemsbranch?items=['.$items_code.']&branch_id=344');
        // $client = new Client();

        // $api_response = $client->get(ENV('PRODUCT_API').'/products/getbyitemsbranch?items=['.$items_code.']&branch_id=344');
        // $api_response2 = $client->get(ENV('PRODUCT_API').'/products/getbyitemsbranch?items=['.$items_code.']&branch_id=344');
        // $response = $api_response->getBody()->getContents();
        // $response = json_decode($response,true);
        // $skuList = [];

        // // dd($response);

        // foreach ($response as $v){
        //     $skuList[] = $v['item_code'];
        // }

        return $proBranch->get();

    }

    public function randomGen($min, $max, $quantity)
    {
        $numbers = range($min, $max);
        shuffle($numbers);

        return array_slice($numbers, 0, $quantity);
    }

    public function getProductCategory($category) 
    {
        if(Auth::check()){
            $user = Auth::user();
            $zipcode = $user->getAttribute('zipcode');
            $branchZip = BranchZipcode::where([
                'zipcode' => $zipcode
            ])->first();
        }
        $products_category = ProductBranch::select('products.*', 'product_branch_uom.price as price_product')
                                ->leftJoin('products', 'product_branch.product_id', '=', 'products.id')
                                ->leftJoin('product_branch_uom', 'product_branch_uom.product_branch_id', '=', 'product_branch.id')
                                ->leftJoin('category_product', 'category_product.product_id', '=', 'product_branch.product_id')
                                ->where('category_product.category_id' , $category->id)
                                ->where('status' , 1)
                                ->where('product_branch.qty' , '!=', 0)
                                ->groupBy('product_branch.product_id');

        $branch_id = !empty($branchZip) ? Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first()->branch_id : null ;
        $products_category = !empty($branchZip) ?  $products_category->where('product_branch.branch_id' , $branch_id)->get()   : $products_category->get() ;
        
        return $products_category ;
        
    }
}
