<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SyncRun;
use Illuminate\View\View;

class SyncRunController extends Controller
{
    public function index(): View
    {
        return view('runs.index', [
            'runs' => SyncRun::query()->with('spreadsheet')->latest('id')->paginate(25),
        ]);
    }

    public function show(SyncRun $run): View
    {
        return view('runs.show', ['run' => $run->load('spreadsheet')]);
    }
}
