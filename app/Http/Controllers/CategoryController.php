<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('categories/Index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back();
    }

    public function update(Request $request, string $id)
    {
        Category::findOrFail($id)->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back();
    }

    public function destroy(string $id)
    {
        Category::findOrFail($id)->delete();

        return back();
    }
}
