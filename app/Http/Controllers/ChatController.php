<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function index(Request $request)
    {
        // TODO: バリデーション
        $messages = $request->messages;

        $result = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
        ]);

        return response()->json(['message' => $result->choices[0]->message->content]);
    }
}


