<?php

namespace App\Http\Controllers\Front;

use App\Shop\Categories\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Shop\Products\Product;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Products\Transformations\ProductTransformable;
use App\Banner;
use DB;
use Auth;
use App\Branch;
use App\BranchZipcode;
use App\Models\ProductBranch;

class ProductController extends Controller
{
    use ProductTransformable;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    private $categoryRepo;

    /**
     * ProductController constructor.
     *
     * @param ProductRepositoryInterface  $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->productRepo = $productRepository;
        $this->categoryRepo = $categoryRepository;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function search()
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
            if (Auth::check()) {
                $user = Auth::user();
                $zipcode = $user->getAttribute('zipcode');
                $branchZip = BranchZipcode::where([
                    'zipcode' => $zipcode,
                ])->first();
            }
            if (!empty($branchZip)) {
                $branch = Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first();
                $branch_id = $branch->getAttribute('branch_id');

                // $searchs = ProductBranch::with('getProduct')
                //                     ->whereHas('getProduct', function($q) use($keyword){
                //                         return $q->where('status', 1)
                //                                 ->where('category_id', '!=', 1)
                //                                 ->where('name', 'LIKE', '%'.$keyword.'%');
                //                     })
                //                     ->where('branch_id',$branch_id)
                //                     ->groupBy('product_id')
                //                     ->get();

                $searchs = ProductBranch::select('products.*', 'product_branch_uom.price as price_product')
                            ->leftJoin('products', 'product_branch.product_id', '=', 'products.id')
                            ->leftJoin('product_branch_uom', 'product_branch_uom.product_branch_id', '=', 'product_branch.id')
                            ->leftJoin('category_product', 'category_product.product_id', '=', 'product_branch.product_id')
                            ->where('status' , 1)
                            ->where('product_branch.qty' , '!=', 0)
                            ->where('branch_id',$branch_id)
                            ->where('products.name', 'LIKE', '%'.$keyword.'%')
                            ->groupBy('product_branch.product_id')
                            ->get();
                // dd($searchs);
            } else {
                $searchs = Product::where('name', 'LIKE', '%'.$keyword.'%')
                                ->where('status', 1)
                                ->get();
            }
        }

        return view('frontend.product', [
            'list_categories' => $list_categories,
            'slug' => $slug,
            'banners' => $banners,
            'event' => $event,
            'searchs' => $searchs,
            'keyword' => $keyword,
        ]);
    }

    /**
     * Get the product.
     *
     * @param string $slug
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(string $slug)
    {
        $product = $this->productRepo->findProductBySlug(['slug' => $slug]);

        // if ($product->status == 0) {
        //     return redirect('/');
        // }
        $expiresAt = now()->addHours(3);

        $images = $product->images()->get();
        $category = $product->categories()->first();
        $productAttributes = $product->attributes;

        // getcategory
        $getcategoryProduct = DB::select('SELECT * FROM category_product WHERE category_id ='.$category->id);
        if (!empty($getcategoryProduct)) {
            foreach ($getcategoryProduct as $data) {
                $getProductID[] = $data->product_id;
            }
        }
        $getProduct = Product::whereIn('id', $getProductID)->get();
        $productRepo = new ProductRepository($product);

        if (Auth::check()) {
            $product['price'] = $productRepo->getPrice('', true);
        } else {
            $product['price'] = $product->price;
        }

        $product['quantity'] = $productRepo->getQuantity(true);

//        if(isset($_GET['debug'])){
//            dd($product['quantity']);
//        }
//
        if(!empty($productAttributes)){
            foreach ($productAttributes as &$item){
                $uom = $item->attributesValues()->first()->value;
                $item->price =  Auth::check() ? $productRepo->getPrice($uom, true) : $item->price ;
            }
        }


        //count views
        views($product)->delayInSession(now()->addHours(2))->record();
        $count = views($product)->unique()->count();
        // dd($product , $product['quantity'] , $productAttributes);
        return view('frontend.detail', compact('product', 'images', 'productAttributes', 'category', 'combos', 'getProduct' , 'count'));
    }
}
