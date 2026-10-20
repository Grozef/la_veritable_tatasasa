<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Absent du squelette depuis Laravel 11 : sans lui, `authorize()` et
    // `authorizeResource()` des controllers atelier n'existent pas.
    use AuthorizesRequests;
}
