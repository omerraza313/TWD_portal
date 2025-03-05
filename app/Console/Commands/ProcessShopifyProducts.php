<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessShopifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:process-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send products to Shopify and update their status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::whereNull('shopify_product_id')
        ->where('is_processed', 0)
        ->limit(2)
        ->get();

        if ($products->isEmpty()) {
            $this->info('No products found for processing.');
            return;
        }

        foreach ($products as $product) {
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
                $response = Http::withOptions(['verify' => false])->post($url, $productArray);

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
                            $collectionResponse = Http::timeout(60)->withOptions(['verify' => false])->post($collectsUrl, $CollectionPayload);
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
                
                        $createResponse = Http::timeout(60)->withOptions(['verify' => false])->post($metafieldUrl, $metafieldPayload);
                
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
                        $shortDescriptionResponse = Http::timeout(60)->withOptions(['verify' => false])->post($metafieldUrl, $metafieldData);     
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
                            'updated_at' =>now()
                        ]);
                        $message .= "Attributes as metfields added, ";
                        // return response()->json(['success' => true, 'message' => $message, 'hits' => $hits]);
                        $this->info("Success: $message");
                        Log::info('Success Response', ['message' => $message, 'hits' => $hits]);
                        //return 0;
                    } else {
                       
                        $this->error('Some metafields failed to add.');
                        Log::error('Error Details', ['errors' => $metafieldErrors]);

                        //return 1;
                    }

                    
                }
                
                
            }

        }
        $this->info("Processed $hits products.");
        return 0;   
    }
}
