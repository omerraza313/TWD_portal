<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Jobs\ProcessShopifyProducts;
use Illuminate\Console\Command;

class DispatchShopifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:dispatch';
    //protected $signature = 'products:dispatch {chunkSize=10}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch products to process them one by one in jobs';

    /**
     * Execute the console command.
     */

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $chunkSize = 10;
        //$chunkSize = $this->argument('chunkSize');

        // Get products where 'shopify_product_id' is null and 'is_processed' is 0
        Product::whereNull('shopify_product_id')
               ->where('is_processed', 0)
               ->chunk($chunkSize, function ($products) {
                   foreach ($products as $product) {
                       // Dispatch a job for each individual product
                       ProcessShopifyProducts::dispatch($product->id);
                       $this->info("Dispatched job for product ID: {$product->id}");
                   }
               });

        $this->info('All jobs have been dispatched.');
    }
}
