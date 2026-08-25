<?php

namespace App\Http\Controllers;

use App\Models\User;

class ChatUiController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'persona')->orderBy('name')->get();

        return view('chat', ['users' => $users]);
    }
}
