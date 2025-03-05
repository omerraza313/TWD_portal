<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $productId;

    /**
     * Create a new job instance.
     */
    public function __construct($productId)
    {
        $this->productId = $productId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Fetch the product by ID
        $product = Product::find($this->productId);

        if (!$product) {
            Log::info("Product ID {$this->productId} not found.");
            return;
        }

        $shopifyApiKey = env('SHOPIFY_API_KEY');
        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifySecretKey = env('SHOPIFY_SECRET_KEY');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $message = null;
        $images = [];
        $position =1;
        $hits = 0;

        if (!$shopifyApiKey || !$shopifyAccessToken || !$shopifyStoreName) {
            Log::error("Shopify credentials are missing. Aborting job.");
            return;
        }

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
            
            $response = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->post($url, $productArray);

            if ($response->successful()){
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
                
                $metafieldErrors = [];

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
                    Log::info("Processing product ID: {$product->id} - {$product->name} - {$message}");
                    $product->update(['is_processed' => 1]);
                    Log::info("Product ID {$product->id} has been processed.");
                } else {
                    Log::info("Something wnet wrong while processing the Product ID {$product->id}");
                }
            }
        }

        
    }
}
