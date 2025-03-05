@extends('backend.layouts.master')
@section('operations_select', 'active')
@push('custom-css')
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css')}}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css')}}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-buttons/css/buttons.bootstrap4.min.css')}}">
@endpush
<style>
    .pagination li a {
        font-size: 14px; /* Adjust the font size as needed */
    }

    svg{
      display: inline-block !important;
      max-height: 20px;
    }
    nav[role="navigation"] > div:first-child {
      display: none;
    }
/*    css for hiding the dataTables pagination*/
    #example1_info{
      display: none;
    }
</style>
@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Synchronize Operations</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="{{route('admin.dash')}}">Home</a></li>
              <li class="breadcrumb-item active">Synchronize</li>
            </ol>
          </div>

         
          
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!----Main Body Content---->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
           <div class="col-md-6 col-sm-6 col-12">
              <div class="info-box">
                 <button id="fetch_new" class="info-box-icon bg-info" style="border: none;" data-toggle="modal" data-target="#confirmModal"><i class="fas fa-sync-alt"></i></button>
                 <div class="info-box-content">
                    <span class="info-box-text">Fetch New Products From Wordpress</span>
                    <span class="info-box-number"></span>
                 </div>
              </div>
            </div>
            
            <div class="col-md-6 col-sm-6 col-12">
                <div class="info-box">
                    <button id="update_prices" class="info-box-icon bg-warning" style="border: none;" data-toggle="modal" data-target="#confirmPriceModal">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                    <div class="info-box-content">
                        <span class="info-box-text">Update Prices from WordPress</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-12">
              <div class="info-box">
                 <button id="sync_shopify" class="info-box-icon bg-success" style="border:none" data-toggle="modal" data-target="#confirmShopifyModal"><i class="fas fa-sync-alt"></i></button>
                 <div class="info-box-content">
                    <span class="info-box-text">Sync to Shopify</span>
                    <span class="info-box-number"></span>
                 </div>

              </div>
            </div>


            <div class="overlay-wrapper" id="show_loading" style="display: none;">
              <div class="overlay">
                <i class="fas fa-3x fa-sync-alt fa-spin" style="margin-left: 15%;"></i>
                <div class="text-bold pt-2">Loading...
                </div>
              </div>
            </div>
           
        </div>
      </div>
    </section>
    <!----End Main Body Content---->

    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to start the process?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                    <button type="button" id="confirmButton" class="btn btn-primary">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Sync Modal -->
    <div class="modal fade" id="confirmShopifyModal" tabindex="-1" role="dialog" aria-labelledby="confirmShopifyModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmShopifyModalLabel">Confirm Sync</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                Are you sure you want to start the process2?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                    <button type="button" id="confirmSyncButton" class="btn btn-primary">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!----Modal for updating the prices---->
    <div class="modal fade" id="confirmPriceModal" tabindex="-1" role="dialog" aria-labelledby="confirmPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmPriceModalLabel">Confirm Price Update</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to update the product prices from WordPress?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                    <button type="button" id="confirmPriceButton" class="btn btn-primary">Yes</button>
                </div>
            </div>
        </div>
    </div>

   
</div>



@push('custom-js')
    <!-- DataTables  & Plugins -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js')}}"></script>
    <script src="{{ asset('plugins/jszip/jszip.min.js')}}"></script>
    <script src="{{ asset('plugins/pdfmake/pdfmake.min.js')}}"></script>
    <script src="{{ asset('plugins/pdfmake/vfs_fonts.js')}}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.min.js')}}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.colVis.min.js')}}"></script>
    <script>
        $(document).ready(function(){
            /****Operations for fetching New products*****/
            var shopifyLimit = 2000;
            var shopifyCurrentLimit = 1;


           // var limit = 1208;
        //    var limit = 1530;
        // var limit = 2219;
        // var limit = 2776;
        // var limit = 3210;
        // var limit = 3309;
        // var limit = 3543;
        var limit = 3521;


            //var limit = 513; //it is for wordpress
            //var limit = 16640;
            //var limit = 16123;
            //var limit = 14991;
            //var limit = 12771;
            //var limit = 12768;
            //  var limit = 12720;
            //var limit = 10627;
            //var limit = 10245;
            //var limit = 8467;
            //var limit = 7600;

            var wordpress_price_limit = 1453;


            $('#fetch_new').on('click', function() {
                $('#show_loading').hide();
                $('#confirmModal').modal('show');

            });

            // Handle click on the confirm button in the modal
            $('#confirmButton').on('click', function() {
                console.log('Process started');
                $('#confirmModal').modal('hide');

                $('#show_loading').show();
                fetchNewProduct(limit);
                
            });
            

            function fetchNewProduct(limit){
                console.log("The limits is "+limit);
            
                $.ajax({
                    url : "{{route('wordpress.fetch')}}",
                    type : 'GET',
                    dataType : 'json',
                    data : {
                        page: limit
                    },
                    success: function(response){
                        limit++;
                        if(response.continue_status === true){
                            console.log(response.msg);
                            fetchNewProduct(limit);
                        } else{
                            $('#show_loading').hide();
                        }
                    },
                    error: function(xhr, status, error){

                    }
                });
                
            }

            /*****New products operation ENDS*****/
            
            /******Operations for syncing prices*******/
            $('#update_prices').on('click', function() {
                $('#confirmPriceModal').modal('show');
            });

            $('#confirmPriceButton').on('click', function() {
                console.log('Price update process started');
                $('#confirmPriceModal').modal('hide');
                $('#show_loading').show();
                updateProductPrices(wordpress_price_limit); // Start with page 1
            });

            function updateProductPrices(page) {
                console.log("Fetching prices, Page: " + page);
                
                $.ajax({
                    url: "{{ route('wordpress.updatePrices') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { page: page },
                    success: function(response) {
                        page++; // Move to the next page
                        
                        if (response.continue_status === true) {
                            console.log(response.msg);
                            updateProductPrices(page);
                        } else {
                            $('#show_loading').hide();
                            console.log("Price update completed!");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error fetching prices:", error);
                    }
                });
            }
             /******Operations for syncing prices ends*******/


                //     /****Operations for Sync to Shopify*****/
                    $('#sync_shopify').on('click', function() {
                        // Show the confirmation modal when the button is clicked
                        $('#confirmShopifyModal').modal('show');
                    });

                //     // Handle click on the confirm button in the sync modal
                $('#confirmSyncButton').on('click', function () {
                    console.log('Sync all products started'); // Print message to console
                    $('#confirmShopifyModal').modal('hide'); // Hide the modal
                    $('#show_loading').show();

                    // Call the sync function
                    syncShopifyProduct(shopifyLimit, shopifyCurrentLimit);
                });

                function syncShopifyProduct(shopifyLimit, shopifyCurrentLimit) {
                    // Ensure current limit is within the allowed range
                    if (shopifyCurrentLimit <= shopifyLimit) {
                        $.ajax({
                            url: "{{route('shopify.update')}}",
                            type: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                if (response.success === true && response.message !== '') {
                                    console.log(response.message); // Log the response message
                                    shopifyCurrentLimit++; // Increment the current limit
                                    syncShopifyProduct(shopifyLimit, shopifyCurrentLimit); // Recursive call
                                } else {
                                    console.log('Sync completed or no further products to sync.');
                                    $('#show_loading').hide();
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('Error:', error);
                                $('#show_loading').hide(); // Hide loading if there's an error
                            }
                        });
                    } else {
                        console.log('Reached Shopify limit or no more products to sync.');
                        $('#show_loading').hide(); // Hide the loading when the limit is reached
                    }
                }
                //     /*****Sync inventory operation ENDS*****/
        });
    </script>
@endpush

@endsection