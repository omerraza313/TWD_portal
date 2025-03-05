<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyController extends Controller
{

    public function updateToShopify2()
    {
        $product = Product::where('id', 33736)->first();
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.']);
        }

        $shopifyApiKey = env('SHOPIFY_API_KEY');
        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifySecretKey = env('SHOPIFY_SECRET_KEY');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $message = null;

        $images = [];
        $position =1;
        foreach ($product->images as $image) {

            $images[] = array(
                'position' => $position,
                'src' => $image->image_path,
            );
           
            $position++;
        }
        
        // 1. Create Product with Variants in a Single API Call
        $vendorBrand = $product->singleAttribute('pa_brand')->pivot->value ?? 'tirewheelsdirect';
        $productPayload = [
            'product' => [
                'title' => $product->name,
                'body_html' => $product->description,
                'vendor' => $vendorBrand,
                'status' => $product->status === 'publish' ? 'active' : 'draft',
                'variants' => [
                    [
                        'title' => 'Default Title',
                        'price' => $product->regular_price,
                        'compare_at_price' => $product->regular_price != $product->price ? $product->price : null,
                        'sku' => $product->sku
                    ],
                ],
                'images' => $images,
                'options' => [
                    ['name' => 'Title', 'values' => ['Default Title']],
                ],
            ],
        ];
        $productUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/products.json";
        $productResponse = Http::withOptions(['verify' => false])->post($productUrl, $productPayload);

        if (!$productResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to Shopify.',
                'error' => $productResponse->json(),
            ], 500);
        }

        $shopifyProductId = $productResponse->json()['product']['id'];
        sleep(1);
        // 2. Batch Add Metafields
        $metafields = [];
        foreach ($product->attributes as $attribute) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => $attribute->shopify_slug,
                'value' => $attribute->pivot->value,
                'type' => 'single_line_text_field',
                'owner_resource' => 'product',
                'owner_id' => $shopifyProductId

            ];
        }

        if ($product->short_description) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'short_description',
                'value' => $product->short_description,
                'type' => 'multi_line_text_field',
                'owner_resource' => 'product',
                'owner_id' => $shopifyProductId
            ];
        }

        $metafieldPayload = [
            'metafields' => $metafields,
        ];

        // $metafieldResponse = Http::withHeaders([
        //     'X-Shopify-Access-Token' => $shopifyAccessToken,
        // ])->post("{$url}/products/{$shopifyProductId}/metafields.json", $metafieldPayload);
        $metafieldUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/metafields.json";
        $metafieldResponse = Http::withOptions(['verify' => false])->post($metafieldUrl, $metafieldPayload);

        if (!$metafieldResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add metafields.',
                'error' => $metafieldResponse->json(),
            ], 500);
        }
        sleep(1);
        // 3. Batch Add Images
        

        // $imageResponse = Http::withHeaders([
        //     'X-Shopify-Access-Token' => $shopifyAccessToken,
        // ])->put("{$url}/products/{$shopifyProductId}.json", $imagePayload);
        // $imageUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/products/{$shopifyProductId}/images.json";
        // $imageResponse = Http::withOptions(['verify' => false])->post($imageUrl, $imagePayload);

        // if (!$imageResponse->successful()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Failed to upload images.',
        //         'error' => $imageResponse->json(),
        //     ], 500);
        // }

        // // 4. Update Product in Database
        // $product->update([
        //     'shopify_product_id' => $shopifyProductId,
        //     'is_processed' => 1,
        // ]);

        return response()->json(['success' => true, 'message' => 'Product successfully added to Shopify.']);
    }

    public function updateToShopify(){
        
        $product = Product::whereNull('shopify_product_id')
                            ->where('is_processed', 0)
                            ->where('price_sync',0)
                            ->first();
       // dd($product);
        //$product = Product::where('is_processed', 0)->first();
        $shopifyApiKey = env('SHOPIFY_API_KEY');
        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifySecretKey = env('SHOPIFY_SECRET_KEY');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $message = null;
        $images = [];
        $position =1;
        $hits = 0;

        foreach ($product->images as $image) {

            $images[] = array(
                'position' => $position,
                'src' => $image->image_path,
            );
           
            $position++;
        }

        $url = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/products.json";
       
        if($product){
            $collectionIds = [];
            foreach($product->categories as $cat){
                $collectionIds[] = $cat->shopify_category_id;
            }
            //dd('omer',$product->categories, $collectionIds);
            $productId = $product->id;
            $vendorBrand = !empty($product->singleAttribute('pa_brand')->pivot->value) ? $product->singleAttribute('pa_brand')->pivot->value:null;
            //dd($product->images[0], $product->images, $product->attributes);
            $productArray = [
                'product' => [
                    'title' => $product->name,
                    'body_html' => $product->description,
                    'vendor' => !empty($vendorBrand) ? $vendorBrand : "tirewheelsdirect",
                    'created_at' => Carbon::parse($product->created_at)->toIso8601String(),
                    'updated_at' => Carbon::parse($product->updated_at)->toIso8601String(),
                    'published_scope' => $product->status == "publish" ? "global" : "web",
                    'tags' => "", // Add tags if applicable
                    'status' => $product->status == "publish" ? "active" : "draft",
                    'variants' => [
                        [
                            'title' => 'Default Title',
                            'price' => $product->regular_price,
                            'compare_at_price' => $product->regular_price != $product->price ? $product->price : null,
                            'sku' => $product->sku
                        ],
                    ],
                    'images' => $images,
                    'options' => [
                        ['name' => 'Title', 'values' => ['Default Title']],
                    ],
                ],
            ];
            //dd($productArray, $product);
            $response = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->post($url, $productArray);

            if ($response->successful()) {
                
                $message = "Product Added, ";
                $hits++;
                $shopifyProduct = $response->json()['product'];
                DB::table('products')
                ->where('id', $productId)
                ->update([
                    'shopify_product_id' => $shopifyProduct['id'],
                ]);
                //Assigning  Collection to product
                if(!empty($collectionIds)){
                    $collectsUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/collects.json";
                    $collectionErrors = [];
                    foreach ($collectionIds as $collectionId){
                        $CollectionPayload = [
                            'collect' => [
                                'product_id' => $shopifyProduct['id'],
                                'collection_id' => $collectionId,
                            ],
                        ];
                        $collectionResponse = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->post($collectsUrl, $CollectionPayload);
                        if (!$collectionResponse->successful()) {
                            $collectionErrors[] = [
                                'collection_id' => $collectionId,
                                'status' => $collectionResponse->status(),
                                'response' => $collectionResponse->json(),
                            ];
                        } else{
                            $hits++;
                        }
                    }
                    if(empty($collectionErrors)){
                        $message .= "Collection Assigned, ";
                    }
                }
                $metafieldUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/2023-10/metafields.json";
            
                $metafieldErrors = []; // To track errors
                foreach ($product->attributes as $attribute) {
                    $metafieldPayload = [
                        'metafield' => [
                            'namespace' => 'custom',
                            'key' => $attribute->shopify_slug,
                            'value' => $attribute->pivot->value,
                            'type' => 'single_line_text_field',
                            'owner_resource' => "product",
                            'owner_id' => $shopifyProduct['id']
                        ],
                    ];
            
                    // Add a slight delay between requests to avoid hitting API limits
                    sleep(1);
            
                    $createResponse = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->post($metafieldUrl, $metafieldPayload);
            
                    if (!$createResponse->successful()) {
                        $metafieldErrors[] = [
                            'attribute' => $attribute->shopify_slug,
                            'response' => $createResponse->body()
                        ];
                    } else{
                        $hits++;
                    }
                }
                if(!empty($product->short_description)){
                    $newDescription = $product->short_description;
                    $metafieldData = [
                        'metafield' => [
                            'namespace' => 'custom',
                            'key' => 'short_description',
                            'value' => $newDescription,
                            'type' => 'multi_line_text_field',
                            'owner_id' => $shopifyProduct['id'],
                            'owner_resource' => 'product',
                        ],
                    ];
                    sleep(1);
                    $shortDescriptionResponse = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->post($metafieldUrl, $metafieldData);     
                    if ($shortDescriptionResponse->successful()) {
                        $message .= "Short Description Added, ";
                        $hits++;
                    }
                }
                
                
                
                // Check for errors after processing all metafields
                if (empty($metafieldErrors)) {
                  
                    DB::table('products')
                    ->where('id', $productId)
                    ->update([
                        'is_processed' => 1,
                    ]);
                    $message .= "Attributes as metfields added, ";
                    return response()->json(['success' => true, 'message' => $message, 'hits' => $hits]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Some metafields failed to add',
                        'details' => $metafieldErrors
                    ], 500);
                }

                
            }
            
            
        }
        

    }

    public function pushCategoriesToShopify()
    {
        $shopifyApiKey = env('SHOPIFY_API_KEY');
        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $shopifyApiVersion = "2023-10";

        $categoryUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@{$shopifyStoreName}/admin/api/{$shopifyApiVersion}/custom_collections.json";

        // Fetch categories without shopify_category_id
        $categories = Category::whereNull('shopify_category_id')->take(2)->get();

        foreach ($categories as $category) {
            $categoryId = $category->id;
            // Prepare payload to create Shopify Collection
            $payload = [
                'custom_collection' => [
                    'title' => $category->name,
                    'body_html' => $category->description ?? null,
                ],
            ];

            // Make API request to Shopify
            $response = Http::withOptions(['verify' => false])->post($categoryUrl, $payload);

            if (!$response->successful()) {
                // Log error if API request fails
                Log::error('Shopify Category Creation Failed', [
                    'category_id' => $category->id,
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create category on Shopify.',
                    'error' => $response->json(),
                ], 500);
            }

            // Retrieve Shopify Collection ID
            $shopifyCollectionId = $response->json()['custom_collection']['id'];
            $shopifyCollectionHandle = $response->json()['custom_collection']['handle'];
            //dd($shopifyCollectionId);
            // Update Local Category with Shopify Collection ID
            if(!empty($shopifyCollectionId)){
                DB::table('categories')
                ->where('id', $categoryId)
                ->update([
                    'shopify_category_id' => $shopifyCollectionId,
                    'shopify_handle' => $shopifyCollectionHandle
                ]);
            }
           
            // $category->update([
            //     'shopify_category_id' => $shopifyCollectionId,
            // ]);
            sleep(1);
        }

        return response()->json(['success' => true, 'message' => 'Categories successfully pushed to Shopify.']);
    }

    
}
