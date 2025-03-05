<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TestController extends Controller
{
    public function bulkCreateProducts()
    {
        // Fetch Products
        $products = Product::whereNull('shopify_product_id')
            ->where('is_processed', 0)
            ->where('price_sync', 0)
            ->limit(2) // Adjust as needed
            ->get();
        // $products = Product::where('id', 180717)
        //     ->first();
        //dd($products);

        if ($products->isEmpty()) {
            return response()->json(['error' => 'No products found'], 404);
        }

        // Shopify API Credentials
        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $shopifyApiVersion = "2023-10";
        $url = "https://{$shopifyStoreName}/admin/api/{$shopifyApiVersion}/graphql.json";

        // Prepare Product Data
        $productInputs = [];
        foreach ($products as $product) {
            $metafields = [];
            foreach ($product->attributes as $attribute) {
                $metafields[] = [
                    'namespace' => 'custom',
                    'key' => $attribute->shopify_slug,
                    'type' => 'single_line_text_field',
                    'value' => $attribute->pivot->value
                ];
            }
            if (!empty($product->short_description)) {
                $metafields[] = [
                    'namespace' => 'custom',
                    'key' => 'short_description',
                    'type' => 'multi_line_text_field',
                    'value' => $product->short_description
                ];
            }

            $vendorBrand = !empty($product->singleAttribute('pa_brand')->pivot->value) ? $product->singleAttribute('pa_brand')->pivot->value:null;
            $productModel = !empty($product->singleAttribute('pa_model')->pivot->value) ? $product->singleAttribute('pa_model')->pivot->value:null;
            $productDiameter = !empty($product->singleAttribute('pa_diameter')->pivot->value) ? $product->singleAttribute('pa_diameter')->pivot->value:null;
            $productWidth = !empty($product->singleAttribute('pa_width')->pivot->value) ? $product->singleAttribute('pa_width')->pivot->value:null;
            $productOffset = !empty($product->singleAttribute('pa_offset-2')->pivot->value) ? $product->singleAttribute('pa_offset-2')->pivot->value:null;

            if (!empty($vendorBrand) && !empty($productModel) && !empty($productDiameter) && !empty($productWidth)) {
                // Convert brand and model to lowercase and replace spaces with '-'
                $vendorBrand = strtolower(str_replace(' ', '-', $vendorBrand));
                $productModel = strtolower(str_replace(' ', '-', $productModel));
            
                // Extract numeric value from diameter
                preg_match('/[\d\.]+/', $productDiameter, $diameterMatch);
                $cleanDiameter = isset($diameterMatch[0]) ? str_replace('.', '_', $diameterMatch[0]) : '';
            
                // Extract numeric value from width
                preg_match('/[\d\.]+/', $productWidth, $widthMatch);
                $cleanWidth = isset($widthMatch[0]) ? str_replace('.', '_', $widthMatch[0]) : '';
            
                // Format diameter x width
                $size = !empty($cleanDiameter) && !empty($cleanWidth) ? "{$cleanDiameter}x{$cleanWidth}" : '';
            
                // Normalize offset (remove negative sign if present)
                $offset = !empty($productOffset) ? str_replace('-', '', $productOffset) : '';
            
                // Construct handle
                $handleParts = array_filter([$vendorBrand, $productModel, $size, $offset]); // Remove empty values
                $handle = implode('-', $handleParts);
            } else {
                $handle = strtolower(str_replace(' ', '-', $product->name));
            }            
            
            $productInputs[] = [
                'title' => $product->name,
                'bodyHtml' => $product->description,
                'vendor' => !empty($vendorBrand) ? $vendorBrand : "tirewheelsdirect",
                'handle' => $handle,
                'created_at' => Carbon::parse($product->created_at)->toIso8601String(),
                'updated_at' => Carbon::parse($product->updated_at)->toIso8601String(),
                'published_scope' => $product->status == "publish" ? "global" : "web",
                'status' => $product->status == "publish" ? "active" : "draft",
                'variants' => [[
                    'title' => 'Default Title',
                    'price' => $product->regular_price,
                    'compare_at_price' => $product->regular_price != $product->price ? $product->price : null,
                    'sku' => $product->sku
                ]],
                'metafields' => $metafields
            ];
        }

        // Bulk Product Creation Mutation
        $query = [
            'query' => 'mutation {
                bulkOperationRunMutation(mutation: """
                    mutation productCreateBulk {
                        ' . implode("\n", array_map(function ($input, $index) {
                            return "p$index: productCreate(input: " . json_encode($input) . ") { product { id } userErrors { message } }";
                        }, $productInputs, array_keys($productInputs))) . '
                    }
                """) {
                    bulkOperation {
                        id
                        status
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }'

        ];

        // Send Request to Shopify
        $response = Http::timeout(120)
            ->retry(3, 2000)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'X-Shopify-Access-Token' => $shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $query);

        $responseData = $response->json();
        dd($responseData);
        // Update Local Database with Shopify Product IDs
        if (!empty($responseData['data']['bulkOperationRunMutation'])) {
            foreach ($products as $index => $product) {
                $shopifyProductId = $responseData['data']['bulkOperationRunMutation']['p' . $index]['product']['id'] ?? null;
                if ($shopifyProductId) {
                    $product->update(['shopify_product_id' => $shopifyProductId]);

                    // Assign Metafields
                    $metafieldsMutation = [
                        'query' => 'mutation {
                            metafieldsSet(metafields: ' . json_encode(array_map(function ($metafield) use ($shopifyProductId) {
                                return [
                                    'ownerId' => $shopifyProductId,
                                    'namespace' => $metafield['namespace'],
                                    'key' => $metafield['key'],
                                    'type' => $metafield['type'],
                                    'value' => $metafield['value']
                                ];
                            }, $productInputs[$index]['metafields'])) . ') {
                                metafields { id }
                                userErrors { field message }
                            }
                        }'
                    ];

                    Http::withHeaders([
                        'X-Shopify-Access-Token' => $shopifyAccessToken,
                        'Content-Type' => 'application/json',
                    ])->post($url, $metafieldsMutation);

                    // Upload Images
                    $images = [];
                    foreach ($product->images as $image) {
                        $images[] = ['altText' => $product->name, 'src' => $image->url];
                    }

                    $imageMutation = [
                        'query' => 'mutation {
                            productCreateMedia(productId: "' . $shopifyProductId . '", media: ' . json_encode($images) . ') {
                                media { id status }
                                userErrors { field message }
                            }
                        }'
                    ];

                    Http::withHeaders([
                        'X-Shopify-Access-Token' => $shopifyAccessToken,
                        'Content-Type' => 'application/json',
                    ])->post($url, $imageMutation);

                    // Add to Collection (Replace Collection ID)
                    $collectionMutation = [
                        'query' => 'mutation {
                            collectionAddProducts(collectionId: "gid://shopify/Collection/XXXXXXXXXX", productIds: ["' . $shopifyProductId . '"]) {
                                job { id }
                                userErrors { field message }
                            }
                        }'
                    ];

                    Http::withHeaders([
                        'X-Shopify-Access-Token' => $shopifyAccessToken,
                        'Content-Type' => 'application/json',
                    ])->post($url, $collectionMutation);
                }
            }
        }

        return response()->json($responseData);
    }


    public function sendProductGraphiQL()
    {
        // $product = Product::whereNull('shopify_product_id')
        //     ->where('is_processed', 0)
        //     ->where('price_sync', 0)
        //     ->first();
        $product = Product::where('id', 15716)
        ->first();

        if (!$product) {
            return response()->json(['error' => 'No product found'], 404);
        }

        $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
        $shopifyStoreName = env('SHOPIFY_STORE_NAME');
        $shopifyApiVersion = "2023-10";

        $url = "https://{$shopifyStoreName}/admin/api/{$shopifyApiVersion}/graphql.json";

        // Prepare Metafields
        $metafields = [];
        foreach ($product->attributes as $attribute) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => $attribute->shopify_slug,
                'type' => 'single_line_text_field',
                'value' => $attribute->pivot->value
            ];
        }

        if (!empty($product->short_description)) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'short_description',
                'type' => 'multi_line_text_field',
                'value' => $product->short_description
            ];
        }

        $vendorBrand = !empty($product->singleAttribute('pa_brand')->pivot->value) ? $product->singleAttribute('pa_brand')->pivot->value:null;
        $productModel = !empty($product->singleAttribute('pa_model')->pivot->value) ? $product->singleAttribute('pa_model')->pivot->value:null;
        $productDiameter = !empty($product->singleAttribute('pa_diameter')->pivot->value) ? $product->singleAttribute('pa_diameter')->pivot->value:null;
        $productWidth = !empty($product->singleAttribute('pa_width')->pivot->value) ? $product->singleAttribute('pa_width')->pivot->value:null;
        $productOffset = !empty($product->singleAttribute('pa_offset-2')->pivot->value) ? $product->singleAttribute('pa_offset-2')->pivot->value:null;

        $productVendor = $vendorBrand;

        if (!empty($vendorBrand) && !empty($productModel) && !empty($productDiameter) && !empty($productWidth)) {
            // Convert brand and model to lowercase and replace spaces with '-'
            $vendorBrand = strtolower(str_replace(' ', '-', $vendorBrand));
            $productModel = strtolower(str_replace(' ', '-', $productModel));
        
            // Extract numeric value from diameter
            preg_match('/[\d\.]+/', $productDiameter, $diameterMatch);
            $cleanDiameter = isset($diameterMatch[0]) ? str_replace('.', '_', $diameterMatch[0]) : '';
        
            // Extract numeric value from width
            preg_match('/[\d\.]+/', $productWidth, $widthMatch);
            $cleanWidth = isset($widthMatch[0]) ? str_replace('.', '_', $widthMatch[0]) : '';
        
            // Format diameter x width
            $size = !empty($cleanDiameter) && !empty($cleanWidth) ? "{$cleanDiameter}x{$cleanWidth}" : '';
        
            // Normalize offset (remove negative sign if present)
            $offset = !empty($productOffset) ? str_replace('-', '', $productOffset) : '';
        
            // Construct handle
            $handleParts = array_filter([$vendorBrand, $productModel, $size, $offset]); // Remove empty values
            $handle = implode('-', $handleParts);
        } else {
            $handle = strtolower(str_replace(' ', '-', $product->name));
        }      

        // GraphQL Mutation for Product Creation
        $query = [
            'query' => 'mutation CreateProductWithDetails($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
                        id
                        title
                        handle
                        status
                        metafields(first: 10) {
                            edges {
                                node {
                                    id
                                    namespace
                                    key
                                    value
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }',
            'variables' => [
                'input' => [
                    'title' => $product->name,
                    'bodyHtml' => $product->description,
                    'vendor' => !empty($productVendor) ? $productVendor : "tirewheelsdirect",
                    'handle' => $handle,
                    // 'published' => $product->status == "publish" ? "global" : "web",
                    'status' => $product->status == "publish" ? "ACTIVE" : "DRAFT",
                    'variants' => [[
                        'title' => 'Default Title',
                        'price' => $product->regular_price,
                        'compareAtPrice' => ($product->regular_price != $product->price) ? $product->price : null,
                        'sku' => $product->sku
                    ]],
                    'metafields' => $metafields
                ]
            ]
        ];

        // Send Request to Shopify
        $response = Http::timeout(60)
            ->retry(3, 2000)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'X-Shopify-Access-Token' => $shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $query);

        $result = $response->json();
        //dd($result);
        // Check for GraphQL Errors
        if (!empty($result['data']['productCreate']['userErrors'])) {
            Log::error("Shopify Product Creation Error: ", $result['data']['productCreate']['userErrors']);
            return response()->json(['errors' => $result['data']['productCreate']['userErrors']], 400);
        }

        $shopifyProductId = $result['data']['productCreate']['product']['id'];
        $shopifyProductId = preg_replace('/[^0-9]/', '', $shopifyProductId);
       
        // Step 2: Upload Images (if any exist)
        if ($product->images()->exists()) {
            $imageData = [];
        
            foreach ($product->images as $image) {
                $imageData[] = [
                    'originalSource' => $image->image_path,
                    'mediaContentType' => 'IMAGE'
                ];
            }
        
            if (!empty($imageData)) { // Ensure there are images before sending request
                $query = [
                    'query' => 'mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
                        productCreateMedia(productId: $productId, media: $media) {
                            media {
                                ... on MediaImage {
                                    id
                                    image {
                                        originalSrc
                                    }
                                }
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }',
                    'variables' => [
                        'productId' => "gid://shopify/Product/{$shopifyProductId}",
                        'media' => $imageData
                    ]
                ];
        
                $response = Http::timeout(60)
                    ->retry(3, 2000)
                    ->withOptions(['verify' => false])
                    ->withHeaders([
                        'X-Shopify-Access-Token' => $shopifyAccessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $query);
        
                $result = $response->json();
                // dd($imageData, $result); // Debugging (Remove in production)
        
                if (!empty($result['data']['productCreateMedia']['userErrors'])) {
                    Log::error("Shopify Image Upload Error: ", $result['data']['productCreateMedia']['userErrors']);
                }
            }
        }
        
        // Step 3: Assign Collections (if categories exist)
        if ($product->categories()->exists()) {

            $collectionIds = [];
            foreach ($product->categories as $cat) {
                $collectionIds[] = "gid://shopify/Collection/{$cat->shopify_category_id}";
            }
            $query = [
                'query' => 'mutation productUpdate($input: ProductInput!) {
                    productUpdate(input: $input) {
                        product {
                            id
                            collections(first: 10) {
                                edges {
                                    node {
                                        id
                                        title
                                    }
                                }
                            }
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }',
                'variables' => [
                    'input' => [
                        'id' => "gid://shopify/Product/{$shopifyProductId}",
                        'collectionsToJoin' => $collectionIds
                    ]
                ]
            ];
    
            $response = Http::timeout(60)
                ->retry(3, 2000)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shopifyAccessToken,
                    'Content-Type' => 'application/json',
                ])->post($url, $query);
    
            $result = $response->json();
            //dd($collectionIds, $result);
            if (!empty($result['data']['productUpdate']['userErrors'])) {
                Log::error("Shopify Collection Assignment Error: ", $result['data']['productUpdate']['userErrors']);
            }
        }

        // Save Shopify Product ID
        if (!empty($shopifyProductId)) {
            $product->shopify_product_id = $shopifyProductId;
            $product->is_processed = 1;
            $product->save();
        }

        return response()->json([
            'message' => 'Product created successfully!',
            'shopify_product_id' => $shopifyProductId
        ]);
    }

    // private function uploadProductImages($shopifyProductId, $images)
    // {
    //     $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
    //     $shopifyStoreName = env('SHOPIFY_STORE_NAME');
    //     $shopifyApiVersion = "2023-10";

    //     $url = "https://{$shopifyStoreName}/admin/api/{$shopifyApiVersion}/graphql.json";

    //     $imageData = [];
    //     foreach ($images as $image) {
    //         $imageData[] = ['src' => $image->image_path];
    //     }

    //     $query = [
    //         'query' => 'mutation productImagesUpdate($productId: ID!, $images: [ImageInput!]!) {
    //             productImagesUpdate(productId: $productId, images: $images) {
    //                 product {
    //                     id
    //                     images(first: 10) {
    //                         edges {
    //                             node {
    //                                 id
    //                                 originalSrc
    //                             }
    //                         }
    //                     }
    //                 }
    //                 userErrors {
    //                     field
    //                     message
    //                 }
    //             }
    //         }',
    //         'variables' => [
    //             'productId' => $shopifyProductId,
    //             'images' => $imageData
    //         ]
    //     ];

    //     $response = Http::timeout(60)
    //         ->retry(3, 2000)
    //         ->withOptions(['verify' => false])
    //         ->withHeaders([
    //             'X-Shopify-Access-Token' => $shopifyAccessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post($url, $query);
    //     //dd($imageData, $response);
    //     $result = $response->json();
    //     if (!empty($result['data']['productImagesUpdate']['userErrors'])) {
    //         Log::error("Shopify Image Upload Error: ", $result['data']['productImagesUpdate']['userErrors']);
    //     }
    // }

    // private function assignCollectionsToProduct($shopifyProductId, $categories)
    // {
    //     $shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
    //     $shopifyStoreName = env('SHOPIFY_STORE_NAME');
    //     $shopifyApiVersion = "2023-10";

    //     $url = "https://{$shopifyStoreName}/admin/api/{$shopifyApiVersion}/graphql.json";

    //     $collectionIds = [];
    //     foreach ($categories as $cat) {
    //         $collectionIds[] = "gid://shopify/Collection/{$cat->shopify_category_id}";
    //     }

    //     $query = [
    //         'query' => 'mutation productUpdate($input: ProductInput!) {
    //             productUpdate(input: $input) {
    //                 product {
    //                     id
    //                     collections(first: 10) {
    //                         edges {
    //                             node {
    //                                 id
    //                                 title
    //                             }
    //                         }
    //                     }
    //                 }
    //                 userErrors {
    //                     field
    //                     message
    //                 }
    //             }
    //         }',
    //         'variables' => [
    //             'input' => [
    //                 'id' => $shopifyProductId,
    //                 'collectionsToJoin' => $collectionIds
    //             ]
    //         ]
    //     ];

    //     $response = Http::timeout(60)
    //         ->retry(3, 2000)
    //         ->withOptions(['verify' => false])
    //         ->withHeaders([
    //             'X-Shopify-Access-Token' => $shopifyAccessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post($url, $query);

    //     $result = $response->json();
    //     if (!empty($result['data']['productUpdate']['userErrors'])) {
    //         Log::error("Shopify Collection Assignment Error: ", $result['data']['productUpdate']['userErrors']);
    //     }
    // }
}
