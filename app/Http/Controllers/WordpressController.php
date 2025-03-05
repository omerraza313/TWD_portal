<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

class WordpressController extends Controller
{
    public function fetchFromWordpress(Request $request){
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $wp_consumer_key = env('WP_CONSUMER_KEY');
        $wp_consumer_secret = env('WP_CONSUMER_SECRET');
        $per_page=50;
        $page=1;
        $skipped = 0;
        $added = 0;
        //$baseUrl = "https://{$shopifyApiKey}:{$shopifyAccessToken}@swissiceco.myshopify.com/admin/api/2023-10/products.json";
        $baseUrl = "https://tireswheelsdirect.com/wp-json/wc/v3/products?consumer_key={$wp_consumer_key}&consumer_secret={$wp_consumer_secret}";
        //https://areeshajewelers.com/wp-json/wc/v3/products?consumer_key=ck_da21ed6cb3d0680cfb8b03058119fcde2640873e&consumer_secret=cs_80ad4e5e7b0c7a98162fb61bc894c727a7bcf4c0&per_page=20&page=192
        // echo "I am in wordpress";
        // echo "<br>"."URL : ".$baseUrl;
        if($page == 1){
            $productsToDelete = Product::where('process_status', false)->pluck('id');
            // echo "<pre>";
            // print_r($productsToDelete);
            // echo "</pre>";
            // die();
            if ($productsToDelete->isNotEmpty()){
                DB::table('category_product')->whereIn('product_id', $productsToDelete)->delete();
                DB::table('attribute_product')->whereIn('product_id', $productsToDelete)->delete();
                DB::table('product_images')->whereIn('product_id', $productsToDelete)->delete();
                Product::whereIn('id', $productsToDelete)->delete();

            }

            // echo "I am omer";
            // die();
        }
        $page = $request->input('page');
        $page = !empty($page) ? $page:null;
        $url = $baseUrl.'&per_page='.$per_page;
        if($page){
            $url .='&page='.urlencode($page);
        }
        $url .='&orderby=date&order=desc';
        //dd($url);

        $response = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->get($url);
        $body = $response->body();
       
        $products = json_decode($body, true);
        // echo "<br>";
        // echo "<pre>";
        //         echo json_encode($products, JSON_PRETTY_PRINT);
        // echo "</pre>";
        if(empty($products)){
            sleep(1);
            $msg = "No more products found";
            return response()->json(['continue_status' =>  false , 'page' => $page, 'url' => $url, 'msg'=>$msg, 'added'=>$added, 'skipped'=>$skipped]);
        } else{
            foreach($products as $key=>$data){
                $existingProduct = Product::where('live_id', $data['id'])
                                   ->where('sku', $data['sku'])
                                   ->first();
                if($existingProduct){
                    $existingProduct->update([
                        'wordpress_permalink' => !empty($data['permalink']) ? $data['permalink'] : NULL,
                        'price' => (float)$data['price'],
                        'regular_price' => (float)$data['regular_price'],
                        'sale_price' => !empty($data['sale_price']) ? (float)$data['sale_price'] : null,
                        'date_on_sale_from' => !empty($data['date_on_sale_from']) ? $data['date_on_sale_from'] : null,
                        'date_on_sale_to' => !empty($data['date_on_sale_to']) ? $data['date_on_sale_to'] : null,
                        'on_sale' => !empty($data['on_sale']) ? $data['on_sale'] : false,
                        'purchasable' => !empty($data['purchasable']) ? $data['purchasable'] : true,
                        'total_sales' => !empty($data['total_sales']) ? $data['total_sales'] : 0,
                        'price_sync' => 0, // Mark as price updated from WordPress
                        'updated_at' => !empty($data['date_modified']) ? $data['date_modified'] : now()
                    ]);
                    $skipped++;
                    //echo "<br>do nothing";
                } else{
                    //echo "<br>add prdocut";
                    $productArray = [
                        'live_id' => $data['id'],
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                        'wordpress_permalink' => !empty($data['permalink']) ? $data['permalink'] : NULL,
                        'type' => !empty($data['type']) ? $data['type'] : 'simple',
                        'status' => !empty($data['status']) ? $data['status'] : 'publish',
                        'featured' => !empty($data['featured']) ? $data['featured'] : false,
                        'catalog_visibility' => !empty($data['catalog_visibility']) ? $data['catalog_visibility'] : null,
                        'description' => !empty($data['description']) ? $data['description'] : null,
                        'short_description' => !empty($data['short_description']) ? $data['short_description'] : null,
                        'sku' => $data['sku'],
                        'price' => (float)$data['price'],
                        'regular_price' => (float)$data['regular_price'],
                        'sale_price' => !empty($data['sale_price']) ? (float)$data['sale_price'] : null,
                        'date_on_sale_from' => !empty($data['date_on_sale_from']) ? $data['date_on_sale_from'] : null,
                        'date_on_sale_to' => !empty($data['date_on_sale_to']) ? $data['date_on_sale_to'] : null,
                        'on_sale' => !empty($data['on_sale']) ? $data['on_sale'] : false,
                        'purchasable' => !empty($data['purchasable']) ? $data['purchasable'] : true,
                        'total_sales' => !empty($data['total_sales']) ? $data['total_sales'] : 0,
                        'virtual' => !empty($data['virtual']) ? $data['virtual'] : false,
                        'downloadable' => !empty($data['downloadable']) ? $data['downloadable'] : false,
                        'tax_status' => !empty($data['tax_status']) ? $data['tax_status'] : 'taxable',
                        'manage_stock' => !empty($data['manage_stock']) ? $data['manage_stock'] : true,
                        'stock_quantity' => !empty($data['stock_quantity']) ? $data['stock_quantity'] : 0,
                        'weight' => !empty($data['weight']) ? $data['weight'] : null,
                        'length' => !empty($data['dimensions']['length']) ? $data['dimensions']['length'] : null,
                        'width' => !empty($data['dimensions']['width']) ? $data['dimensions']['width'] : null,
                        'height' => !empty($data['dimensions']['height']) ? $data['dimensions']['height'] : null,
                        'price_html' => !empty($data['price_html']) ? $data['price_html'] : null,
                        'stock_status' => !empty($data['stock_status']) ? $data['stock_status'] : 'instock',
                        'price_sync' => 0,
                        'created_at' => !empty($data['date_created']) ? $data['date_created'] : null,
                        'updated_at' => !empty($data['date_modified']) ? $data['date_modified'] : null
                    ];
                    $productId = DB::table('products')->insertGetId($productArray);

                    //category handling
                    $categories = $data['categories'];
                    if(!empty($categories)){
                        DB::table('category_product')->where('product_id', $productId)->delete();
                        foreach($categories as $cat_key=>$categoryData){
                            $existingCategory = Category::where('live_id', $categoryData['id'])
                                                ->where('slug', $categoryData['slug'])
                                                ->first();
    
                            if($existingCategory){
                                $categoryId = $existingCategory->id;
                            } else{
                                $newCategoryArray = [
                                    'live_id' => $categoryData['id'],
                                    'name' => $categoryData['name'],
                                    'slug' => $categoryData['slug'],
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                                $categoryId = DB::table('categories')->insertGetId($newCategoryArray);
                            }
                            if(!empty($categoryId)){
                                DB::table('category_product')->updateOrInsert(
                                    ['product_id' => $productId, 'category_id' => $categoryId],
                                    ['created_at' => now(), 'updated_at' => now()]
                                );
                            }
                        }
                    }

                    //attribute handling
                    $attributes = $data['attributes'];
                    $attributeProductArray = [];
                    if(!empty($attributes)){
                        foreach($attributes as $attr_key=>$attributeData){
                            $existingAttribute = Attribute::where('live_id', $attributeData['id'])
                                                 ->where('slug', $attributeData['slug'])
                                                 ->first();
                            if($existingAttribute){
                                $attributeId = $existingAttribute->id;
                            } else{
                                $newAttributeArray = [
                                    'live_id' => $attributeData['id'],
                                    'name' => $attributeData['name'],
                                    'slug' => $attributeData['slug'],
                                    'position' => $attributeData['position'],
                                    'visible' => $attributeData['visible'],
                                    'variation' => $attributeData['variation'],
                                ];
                                $attributeId = DB::table('attributes')->insertGetId($newAttributeArray);
                            }
                            if (!empty($attributeId) && !empty($attributeData['options'])) {
                                // Take only the first value from the options array
                                $optionValue = $attributeData['options'][0];
                        
                                // Check if an entry already exists in the pivot table
                                $existingPivot = DB::table('attribute_product')
                                    ->where('product_id', $productId)
                                    ->where('attribute_id', $attributeId)
                                    ->first();
                        
                                if (!$existingPivot) {
                                    // Insert into the pivot table
                                    $attributeProductArray = [
                                        'product_id' => $productId,
                                        'attribute_id' => $attributeId,
                                        'value' => $optionValue, // Store the first option value
                                        'order' => 1, // Default to 1 since there's only one value
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                        
                                    DB::table('attribute_product')->insert($attributeProductArray);
                                }
                            }
                        }
                    }
                    

                    //image handling
                    $images = $data['images'];
                    $productImageArray = [];
                    $image_position = 0;
                    if(!empty($images)){
                        DB::table('product_images')->where('product_id', $productId)->delete();
                        foreach($images as $img_key=>$image){
                            $productImageArray = [
                                'product_id' => $productId,
                                'image_path' => $image['src'],
                                'order'=> $image_position,
                                'created_at' => $image['date_created'],
                                'updated_at' => $image['date_modified']
                            ];
                            $image_position++;
                            DB::table('product_images')->insert($productImageArray);
                        }
                    }
                    
                    DB::table('products')
                    ->where('id', $productId)
                    ->update(['process_status' => true, 'updated_at' => now()]);
                    $added++;

                }
            }
            sleep(1);
            $end = $page * $per_page;
            $start = $end - $per_page;
            $start = $start+1;
            $msg = "The page = {$page} and per_page = {$per_page} and it process product from {$start} to {$end}";
            return response()->json(['continue_status' =>  true , 'page' => $page, 'url' => $url, 'msg'=>$msg, 'added'=>$added, 'skipped'=>$skipped]);
            
        }
        
        //die();
    }

    public function fetchPricesFromWordpress(Request $request)
    {
        $wp_consumer_key = env('WP_CONSUMER_KEY');
        $wp_consumer_secret = env('WP_CONSUMER_SECRET');
        $per_page = 50;
        //49
        $page = $request->input('page') ?? 1; // Default to page 1

        $baseUrl = "https://tireswheelsdirect.com/wp-json/wc/v3/products?consumer_key={$wp_consumer_key}&consumer_secret={$wp_consumer_secret}";
        $url = "{$baseUrl}&per_page={$per_page}&page={$page}";
        $url .='&orderby=date&order=desc';

        $response = Http::timeout(60)->retry(3, 2000)->withOptions(['verify' => false])->get($url);
        $products = json_decode($response->body(), true);

        // echo "<pre>";
        // print_r($products);
        // echo "</pre>";
        // die();

        if (empty($products)) {
            return response()->json([
                'continue_status' => false,
                'page' => $page,
                'url' => $url,
                'msg' => 'No more products found'
            ]);
        }

        $updated = 0;
        $skipped = 0;
        $skippedProducts = [];
        sleep(1);
        foreach ($products as $data) {
            $updatedRows = DB::table('products')
                ->where('live_id', $data['id'])
                ->where('sku', $data['sku'])
                ->whereNull('price_sync') // Ensure price_sync is NULL before updating
                ->update([
                    'price' => (float)$data['price'],
                    'regular_price' => (float)$data['regular_price'],
                    'sale_price' => !empty($data['sale_price']) ? (float)$data['sale_price'] : null,
                    'date_on_sale_from' => !empty($data['date_on_sale_from']) ? $data['date_on_sale_from'] : null,
                    'date_on_sale_to' => !empty($data['date_on_sale_to']) ? $data['date_on_sale_to'] : null,
                    'on_sale' => !empty($data['on_sale']) ? $data['on_sale'] : false,
                    'purchasable' => !empty($data['purchasable']) ? $data['purchasable'] : true,
                    'total_sales' => !empty($data['total_sales']) ? $data['total_sales'] : 0,
                    'price_sync' => 0, // Mark as price updated from WordPress
                    'updated_at' => now()
                ]);
        
            if ($updatedRows > 0) {
                $updated++;
            } else {
                $skippedProducts[] = [
                    'wordpress_product_id' => $data['id'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $skipped++;
            }
        }
        
        // Insert skipped products
        if (!empty($skippedProducts)) {
            DB::table('skipped_products')->insert($skippedProducts);
        }
        

        return response()->json([
            'continue_status' => true,
            'page' => $page + 1,
            'msg' => "{$updated} products updated, {$skipped} skipped.",
        ]);
    }

}
