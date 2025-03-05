<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\Product;

class UserController extends Controller
{
   public function AssignRole(){
    // $user = User::find(1);
    // $user->assignRole('admin');

    // $editor = User::find(2);
    // $editor->assignRole('editor'); 
    //Permission::create(['name' => 'view dashboard']);
    // $admin = Role::findByName('admin');
    // $admin->givePermissionTo(['view dashboard']);
    $editor = Role::findByName('editor');
    $editor->givePermissionTo(['view dashboard']);
    // dd($admin);

   }

   public function dashboard(){
      $attributeCount = Attribute::count();
      $categoryCount = Category::count();
      $productCount = Product::count();
      return view('backend.dashboard', compact('attributeCount', 'categoryCount', 'productCount'));
   }

}
