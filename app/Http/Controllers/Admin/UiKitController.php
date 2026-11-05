<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class UiKitController extends Controller
{
    /**
     * Display comprehensive visual design system showcase.
     */
    public function index(): View
    {
        return view('admin.ui-kit');
    }
}
