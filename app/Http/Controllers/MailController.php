<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class MailController extends Controller
{
    public function index(){
        return Inertia::render('SideBarPages/MailPage');
    }
}
