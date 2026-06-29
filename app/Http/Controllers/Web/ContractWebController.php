<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contract;

class ContractWebController extends Controller
{
    public function index()
    {
        return view('contracts.index');
    }

    public function show(Contract $contract)
    {
        return view('contracts.show', compact('contract'));
    }

    public function letters(Contract $contract)
    {
        return view('contracts.letters', compact('contract'));
    }
}
