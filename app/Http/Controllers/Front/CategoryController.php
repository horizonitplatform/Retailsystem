<?php

namespace App\Http\Controllers\Front;

use App\Models\SystemLink;
use App\Shop\Categories\Repositories\CategoryRepository;
use App\Shop\Categories\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Banner;
use Auth;
use App\Branch;
use App\BranchZipcode;
use App\Shop\Products\Product;
use App\Models\ProductBranch;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepo;

    /**
     * CategoryController constructor.
     *
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepo = $categoryRepository;
    }

    /**
     * Find the category via the slug.
     *
     * @param string $slug
     *
     * @return \App\Shop\Categories\Category
     */
    public function getCategory(string $slug)
    {
        $list_categories = $this->categoryRepo->listCategories('created_at', 'asc', [1,10]);

        $category = $this->categoryRepo->findCategoryBySlug(['slug' => $slug]);

        $repo = new CategoryRepository($category);

        if(Auth::check()){
            $user = Auth::user();
            $zipcode = $user->getAttribute('zipcode');
            $branchZip = BranchZipcode::where([
                'zipcode' => $zipcode
            ])->first();
        }
        // if(isset($_GET['debug'])){
        //            Artisan::call('update:new-product-by-id', [
        //                'id' => 198,
        //            ]); // THIS IS BUG
        $linkData = SystemLink::where([
            'system_id' => $category->id,
            'type' => 'category'
        ])->first();
        if($linkData ){
            $client = new Client();

            $api_response = $client->get(ENV('PRODUCT_API').'/products/getbycatbranch?category_id='.$linkData->link_id.'&perpage=10000&page=1');
            //$api_response = $client->get('http://3bbfc.ddns.cyberoam.com:96/prod/horizont/products/getbycatbranch?category_id='.$linkData->link_id.'&perpage=10000&page=1');
            $response = $api_response->getBody()->getContents();
            $response = json_decode($response,true);
            $skuList = [];

            foreach ($response as $v){
                $skuList[] = $v['item_code'];
            }
            // $products_category = $repo->findProducts()->whereIn('sku', $skuList)->all();
        }
        // }

        if(!empty($branchZip)){
           
            $branch = Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first();
            $branch_id = $branch->getAttribute('branch_id');

            $products_category = ProductBranch::select('products.*', 'product_branch_uom.price as price_product')
                ->leftJoin('products', 'product_branch.product_id', '=', 'products.id')
                ->leftJoin('product_branch_uom', 'product_branch_uom.product_branch_id', '=', 'product_branch.id')
                                ->leftJoin('category_product', 'category_product.product_id', '=', 'product_branch.product_id')
                                ->where('category_product.category_id' , $category->id)
                                ->where('status' , 1)
                                ->where('product_branch.branch_id' , $branch_id)
                                ->where('product_branch.qty' , '!=', 0)
                                ->whereIn('products.sku', $skuList)
                                ->groupBy('product_branch.product_id')
                                ->get() ;

            // $products_category = $repo->findProducts()->whereIn('sku', $skuList)->all();


        }else{
            $products_category = $repo->findProducts()->where('status', 1)->all();
        }

        $banners = Banner::where('type', 'banner')->get();
        $event = Banner::where('type', 'event')->first();

        foreach ($list_categories as $key => $list) {
            $id = $list->id;
            $total = DB::table('category_product')->where('category_product.category_id', '=', $id)->count('category_product.category_id');
            $list['total'] = $total;
        }


        return view('frontend.product', [
            'category' => $category,
            'products_category' => $products_category,
            'list_categories' => $list_categories,
            'slug' => $slug,
            'banners' => $banners,
            'event' => $event,
        ]);
    }

    public function getCategoryAll()
    {
        $lists = $this->categoryRepo->listCategories('created_at', 'asc', [1,10]);
        foreach ($lists as $key => $list) {
            $id = $list->id;
            $total = DB::table('category_product')->where('category_product.category_id', '=', $id)->count('category_product.category_id');
            $list['total'] = $total;
        }

        return view('frontend.category-all', [
            'categories' => $this->categoryRepo->paginateArrayResults($lists->all()),
        ]);
    }

    public function showAll()
    {
        $lists = $this->categoryRepo->listCategories('created_at', 'asc', 1);

        $products = array();
        foreach ($lists as $key => $list) {
            $repo = new CategoryRepository($list);
            $product = $repo->findProducts()->where('status', 1)->all();
            $products[$key] = $product;
        }

        $banners = Banner::where('type', 'banner')->get();
        $event = Banner::where('type', 'event')->first();
        // dd($banners);

        return view('frontend.product', [
            'lists' => $lists,
            'products' => $products,
            'banners' => $banners,
            'event' => $event,
        ]);
    }

    public function searchProduct()
    {
        $products = null;
        $seach = true;
        $slug = null;
        $banners = Banner::where('type', 'banner')->get();
        $event = Banner::where('type', 'event')->first();
        $keyword = request()->input('q');

        $list_categories = $this->categoryRepo->listCategories('created_at', 'asc', 1);

        foreach ($list_categories as $key => $list) {
            $id = $list->id;
            $total = DB::table('category_product')->where('category_product.category_id', '=', $id)->count('category_product.category_id');
            $list['total'] = $total;
        }

        if (request()->has('q') && request()->input('q') != '') {
            $searchs  = Product::where('name' , 'LIKE' , '%'.$keyword.'%')
                                ->where('status', 1)
                                ->get();
        }

        return view('frontend.product', [
            'list_categories' => $list_categories,
            'slug' => $slug,
            'banners' => $banners,
            'event' => $event,
            'searchs' => $searchs,
            'keyword' => $keyword ,
        ]);
    }
}
