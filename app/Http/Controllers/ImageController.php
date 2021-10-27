<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Images;
use App\Http\Requests\ImageRequest;
use App\Models\Transactions;

class ImageController extends Controller
{

    public function store(ImageRequest $request)
    {
        $user = Auth::user();

        $transaction = Transactions::where('id', $request->transaction_id)
            ->where('user_id', $user['id'])
            ->get();

        if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'bypass']);

        $file = $request->file->store('public/documents/transactions');

        $newImage = Images::create([
            'transaction_id' => $request->transaction_id,
            'url' => $file,
        ]);
        return response()->json($newImage);
    }
}