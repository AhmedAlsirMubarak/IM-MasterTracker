<?php

namespace App\Http\Controllers;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
}
