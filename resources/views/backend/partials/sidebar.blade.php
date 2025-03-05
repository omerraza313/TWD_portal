<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="#" class="brand-link">
      <img src="{{ asset('dist/img/AdminLTELogo.png')}}" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>CR Portal</b></span>
      <!-- <span class="brand-text font-weight-light o-brand-name"><b>Html Generator</b></span> -->
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="{{ asset('dist/img/user2-160x160.jpg')}}" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block">
            {{ Auth::user()->name }}
            <br>
            
          </a>
        </div>
      </div>

    
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <li class="nav-item menu-open">
            <a href="{{route('admin.dash')}}" class="nav-link @yield('dashboard_select')">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard 
              </p>
            </a>           
          </li>
          <li class="nav-item">
            <a href="" class="nav-link @yield('attributes_select')">
              <i class="nav-icon fas fa-cogs"></i>
              <p>
                Attribute
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('attributes.view')}}" class="nav-link">
                  <i class="fas fa-list nav-icon"></i>
                  <p>See All</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a href="" class="nav-link @yield('categories_select')">
              <i class="nav-icon fas fa-folder"></i>
              <p>
                Category
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('categories.view')}}" class="nav-link">
                  <i class="fas fa-list nav-icon"></i>
                  <p>See All</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a href="" class="nav-link @yield('products_select')">
              <i class="nav-icon fas fa-box"></i>
              <p>
                Product
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('product.view')}}" class="nav-link">
                  <i class="fas fa-list nav-icon"></i>
                  <p>See All</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a href="" class="nav-link @yield('operations_select')">
              <i class="nav-icon fas fa-sync-alt"></i>
              <p>
                Synchronize
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('synch.view')}}" class="nav-link">
                  <i class="fas fa-sync-alt nav-icon"></i>
                  <p>Operation</p>
                </a>
              </li>
            </ul>
          </li>

          {{--<li class="nav-item">
            <a href="" class="nav-link @yield('users_select')">
              <i class="nav-icon fas fa-user"></i>
              <p>
                Users
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('admin.users')}}" class="nav-link">
                  <i class="fas fa-list nav-icon"></i>
                  <p>See All</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('admin.users.add')}}" class="nav-link">
                  <i class="fas fa-plus nav-icon"></i>
                  <p>Add New</p>
                </a>
              </li>
            </ul>
          </li>--}}
          
          

        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>