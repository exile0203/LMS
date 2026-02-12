<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class GroupChatController extends Controller
{
    public function index(){
        return Inertia::render('SideBarPages/GroupChatPage');
    }
}
