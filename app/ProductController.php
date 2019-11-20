<?php

namespace App\Http\Controllers\Front;

use App\Shop\Products\Product;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Products\Transformations\ProductTransformable;
use DB;
class ProductController extends Controller
{
    use ProductTransformable;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    /**
     * ProductController constructor.
     *
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepo = $productRepository;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function search()
    {
        if (request()->has('q') && request()->input('q') != '') {
            $list = $this->productRepo->searchProduct(request()->input('q'));
        } else {
            $list = $this->productRepo->listProducts();
        }

        $products = $list->where('status', 1)->map(function (Product $item) {
            return $this->transformProduct($item);
        });

        return view('front.products.product-search', [
            'products' => $this->productRepo->paginateArrayResults($products->all(), 10),
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
        $product = Product::whereIn('id', $getProductID)->get();
        $productRepo = new ProductRepository($product);
        $product['price'] = $productRepo->getPrice();
        $product['quantity'] = $productRepo->getQuantity();

        return view('frontend.detail', compact('product', 'images', 'productAttributes', 'category', 'combos','getProduct'));
    }
}