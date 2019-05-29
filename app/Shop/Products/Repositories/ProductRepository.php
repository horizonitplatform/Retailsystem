<?php

namespace App\Shop\Products\Repositories;

use App\Branch;
use App\BranchZipcode;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use App\Shop\AttributeValues\AttributeValue;
use App\Shop\Products\Exceptions\ProductCreateErrorException;
use App\Shop\Products\Exceptions\ProductUpdateErrorException;
use App\Shop\Tools\UploadableTrait;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Jsdecena\Baserepo\BaseRepository;
use App\Shop\Brands\Brand;
use App\Shop\ProductAttributes\ProductAttribute;
use App\Shop\ProductImages\ProductImage;
use App\Shop\Products\Exceptions\ProductNotFoundException;
use App\Shop\Products\Product;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Transformations\ProductTransformable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    use ProductTransformable, UploadableTrait;

    /**
     * ProductRepository constructor.
     *
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        parent::__construct($product);
        $this->model = $product;
    }

    /**
     * List all the products.
     *
     * @param string $order
     * @param string $sort
     * @param array  $columns
     *
     * @return Collection
     */
    public function listProducts(string $order = 'id', string $sort = 'desc', array $columns = ['*']): Collection
    {
        return $this->all($columns, $order, $sort);
    }

    /**
     * Create the product.
     *
     * @param array $data
     *
     * @return Product
     *
     * @throws ProductCreateErrorException
     */
    public function createProduct(array $data): Product
    {
        try {
            return $this->create($data);
        } catch (QueryException $e) {
            throw new ProductCreateErrorException($e);
        }
    }

    /**
     * Update the product.
     *
     * @param array $data
     *
     * @return bool
     *
     * @throws ProductUpdateErrorException
     */
    public function updateProduct(array $data): bool
    {
        $filtered = collect($data)->except('image')->all();

        try {
            return $this->model->where('id', $this->model->id)->update($filtered);
        } catch (QueryException $e) {
            throw new ProductUpdateErrorException($e);
        }
    }

    /**
     * Find the product by ID.
     *
     * @param int $id
     *
     * @return Product
     *
     * @throws ProductNotFoundException
     */
    public function findProductById(int $id): Product
    {
        try {
            return $this->transformProduct($this->findOneOrFail($id));
        } catch (ModelNotFoundException $e) {
            throw new ProductNotFoundException($e);
        }
    }

    /**
     * Delete the product.
     *
     * @param Product $product
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @deprecated
     * @use removeProduct
     */
    public function deleteProduct(Product $product): bool
    {
        $product->images()->delete();

        return $product->delete();
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function removeProduct(): bool
    {
        return $this->model->where('id', $this->model->id)->delete();
    }

    /**
     * Detach the categories.
     */
    public function detachCategories()
    {
        $this->model->categories()->detach();
    }

    /**
     * Return the categories which the product is associated with.
     *
     * @return Collection
     */
    public function getCategories(): Collection
    {
        return $this->model->categories()->get();
    }

    /**
     * Sync the categories.
     *
     * @param array $params
     */
    public function syncCategories(array $params)
    {
        $this->model->categories()->sync($params);
    }

    /**
     * @param $file
     * @param null $disk
     *
     * @return bool
     */
    public function deleteFile(array $file, $disk = null): bool
    {
        return $this->update(['cover' => null], $file['product']);
    }

    /**
     * @param string $src
     *
     * @return bool
     */
    public function deleteThumb(string $src): bool
    {
        return DB::table('product_images')->where('src', $src)->delete();
    }

    /**
     * Get the product via slug.
     *
     * @param array $slug
     *
     * @return Product
     *
     * @throws ProductNotFoundException
     */
    public function findProductBySlug(array $slug): Product
    {
        try {
            return $this->findOneByOrFail($slug);
        } catch (ModelNotFoundException $e) {
            throw new ProductNotFoundException($e);
        }
    }

    /**
     * @param string $text
     *
     * @return mixed
     */
    public function searchProduct(string $text): Collection
    {
        if (!empty($text)) {
            return $this->model->searchProduct($text);
        } else {
            return $this->listProducts();
        }
    }

    /**
     * @return mixed
     */
    public function findProductImages(): Collection
    {
        return $this->model->images()->get();
    }

    /**
     * @param UploadedFile $file
     *
     * @return string
     */
    public function saveCoverImage(UploadedFile $file): string
    {
        return $file->store('products', ['disk' => 'public']);
    }

    /**
     * @param Collection $collection
     */
    public function saveProductImages(Collection $collection)
    {
        $collection->each(function (UploadedFile $file) {
            $filename = $this->storeFile($file);
            $productImage = new ProductImage([
                'product_id' => $this->model->id,
                'src' => $filename,
            ]);
            $this->model->images()->save($productImage);
        });
    }

    /**
     * Associate the product attribute to the product.
     *
     * @param ProductAttribute $productAttribute
     *
     * @return ProductAttribute
     */
    public function saveProductAttributes(ProductAttribute $productAttribute): ProductAttribute
    {
        $this->model->attributes()->save($productAttribute);

        return $productAttribute;
    }

    /**
     * List all the product attributes associated with the product.
     *
     * @return Collection
     */
    public function listProductAttributes(): Collection
    {
        return $this->model->attributes()->get();
    }

    /**
     * Delete the attribute from the product.
     *
     * @param ProductAttribute $productAttribute
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function removeProductAttribute(ProductAttribute $productAttribute): ?bool
    {
        return $productAttribute->delete();
    }

    /**
     * @param ProductAttribute $productAttribute
     * @param AttributeValue   ...$attributeValues
     *
     * @return Collection
     */
    public function saveCombination(ProductAttribute $productAttribute, AttributeValue ...$attributeValues): Collection
    {
        return collect($attributeValues)->each(function (AttributeValue $value) use ($productAttribute) {
            return $productAttribute->attributesValues()->save($value);
        });
    }

    /**
     * @return Collection
     */
    public function listCombinations(): Collection
    {
        return $this->model->attributes()->map(function (ProductAttribute $productAttribute) {
            return $productAttribute->attributesValues;
        });
    }

    /**
     * @param ProductAttribute $productAttribute
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findProductCombination(ProductAttribute $productAttribute)
    {
        $values = $productAttribute->attributesValues()->get();

        return $values->map(function (AttributeValue $attributeValue) {
            return $attributeValue;
        })->keyBy(function (AttributeValue $item) {
            return strtolower($item->attribute->name);
        })->transform(function (AttributeValue $value) {
            return $value->value;
        });
    }

    /**
     * @param Brand $brand
     */
    public function saveBrand(Brand $brand)
    {
        $this->model->brand()->associate($brand);
    }

    /**
     * @return Brand
     */
    public function findBrand()
    {
        return $this->model->brand;
    }



    public function getPrice($uom = '', $formApi = false){
        if (Auth::check()) {
            $user = Auth::user();
            $zipcode = $user->getAttribute('zipcode');
            $branchZip = BranchZipcode::where([
                'zipcode' => $zipcode
            ])->first();
            if($uom == 'BOX'){
                $uom = 'Box';

            }

            if($branchZip){
                $branch = Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first();

                $branch_id = $branch->getAttribute('branch_id');
                $product_branch = ProductBranch::where([
                    'product_id' => $this->model->getAttribute('id'),
                    'branch_id' => $branch_id
                ])->first();
                if(!empty($uom)){
                    if(empty($product_branch)){
                        // $product_branch = ProductBranch::where([
                        //     'product_id' => $this->model->getAttribute('id'),
                        // ])->first();
                        $product = Product::where('id', $this->model->getAttribute('id'))->first();
                        $productUOM['price'] = $product->price;
                        return $productUOM['price'];
                    }
                    $productUOM = ProductBranchUOM::where([
                        'product_branch_id' => $product_branch->getAttribute('id'),
                        'uom' => $uom,
                    ])->first();
                }else{
                    if(empty($product_branch)){
                        // $product_branch = ProductBranch::where([
                        //     'product_id' => $this->model->getAttribute('id'),
                        // ])->first();
                        $product = Product::where('id', $this->model->getAttribute('id'))->first();
                        $productUOM['price'] = $product->price;
                        return $productUOM['price'];
                    }
                    $productUOM = ProductBranchUOM::where([
                        'product_branch_id' => $product_branch->getAttribute('id'),
                        'um_convert' => 1
                    ])->first();
                }
                if($formApi){ // GET REAL INFO
                    $client = new Client();
                    $product_id = $this->model->getAttribute('id'); //
                    $linkData = SystemLink::where([
                        'system_id' => $product_id,
                        'type' => 'product'
                    ])->first();
                    if($linkData ){
                        // $api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbyitem_branch?item_id='.$linkData->link_id.'&branch_id='.$branch_id);
                        $api_response = $client->get(ENV('PRODUCT_API').'/products/getbyitemsbranch?items=[{"item_id":"'.$linkData->link_id.'","item_code":""}]&branch_id='.$branch_id);
                        $response = $api_response->getBody()->getContents();
                        $response = json_decode($response,true);


                        if(empty($response)){
                            return 0;
                        }else{
                            if(isset($response[0]['branch'][0])){
                                foreach ($response[0]['branch'][0]['prices'] as $p){
                                    if($p['uom'] === $productUOM->uom){
                                        return $p['price'];
                                    }
                                }
                            }
                        }
                    }
                }
                return $productUOM['price'];
            }else{
                $product_branch = ProductBranch::where([
                    'product_id' => $this->model->getAttribute('id'),
                ])->first();
                $productUOM = ProductBranchUOM::where([
                    'product_branch_id' => $product_branch->getAttribute('id'),
                    'um_convert' => 1
                ])->first();
                return $productUOM['price'];
            }
        }else{
            $product_branch = ProductBranch::where([
                'product_id' => $this->model->getAttribute('id'),
            ])->first();
            $productUOM = ProductBranchUOM::where([
                'product_branch_id' => $product_branch->getAttribute('id'),
                'um_convert' => 1
            ])->first();
            return $productUOM['price'];
        }
    }

    public function getQuantity($formApi = false){
        if (Auth::check()) {
            $user = Auth::user();
            $zipcode = $user->getAttribute('zipcode');
            $branchZip = BranchZipcode::where([
                'zipcode' => $zipcode
            ])->first();
            if($branchZip){
                $branch = Branch::where(['id' => $branchZip->getAttribute('branch_id')])->first();
                $branch_id = $branch->getAttribute('branch_id');

                if($formApi){ // GET REAL INFO
                    $client = new Client();
                    $product_id = $this->model->getAttribute('id'); //
                    $linkData = SystemLink::where([
                        'system_id' => $product_id,
                        'type' => 'product'
                    ])->first();
                    if($linkData ){
                        // $api_response = $client->get('http://180.183.247.217:96/prod/horizont/products/getbyitem_branch?item_id='.$linkData->link_id.'&branch_id='.$branch_id);
                        $api_response = $client->get(ENV('PRODUCT_API').'/products/getbyitemsbranch?items=[{"item_id":"'.$linkData->link_id.'","item_code":""}]&branch_id='.$branch_id);
                        $response = $api_response->getBody()->getContents();
                        $response = json_decode($response,true);
                        // dd($response);
                        if(empty($response)){
                            return 0;
                        }else{
                            $response = isset($response[0]) && isset($response[0]['branch'][0]['qty']) ?$response[0]['branch'][0]['qty'] : null;
                            if($response !== null){
                                return floor($response);
                            }
                        }
                    }

                }

                // $product_branch = ProductBranch::where([
                //     'product_id' => $this->model->getAttribute('id'),
                //     'branch_id' => $branch_id
                // ])->first();
                
                // return $product_branch['qty'];
            }
        }else{
            $product_branch = ProductBranch::where([
                'product_id' => $this->model->getAttribute('id'),
            ])->where('qty','>',0)->first();
            return $product_branch['qty'];
        }
    }
}
