<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    public function index()
    {
        if ($this->user['admin'] == 1) {
            $transactions = Transaction::where('status', 'pending')
                ->where('type', 'in')
                ->get()
                ->load('user');
        } else {
            $transactions = Transaction::where('user_id', $this->user['id'])->get();
        }
        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $rules = [
            'amount' => ['required'],
            'date' => ['required'],
            'description' => ['string'],
            'type' => ['required', 'in:in,out'],
            'image' => ['mimes:jpg,png', 'max:4096'],
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) return response()->json($validator->messages());


        if (!empty($request['image'])) {
            $image_saved = $this->storeImage($request);
        }

        $newTransaction = Transaction::create([
            'user_id' => $this->user['id'],
            'amount' => $request->amount,
            'date' => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
            'description' => $request->description,
            'type' => $request->type,
            'image' => !empty($image_saved) ? $image_saved : null
        ]);
        return response()->json($newTransaction);
    }

    public function show($id = false)
    {
        if ($id) {
            $transaction = Transaction::where('user_id', $this->user['id'])
                ->where('id', $id)
                ->get();
            if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'bypass']);
            return response()->json($transaction);
        }
    }

    public function update(Request $request)
    {
        if ($request->id) {
            if ($this->user['admin'] == 1) {
                $rules = [
                    'status' => ['required', 'in:accepted,rejected'],
                ];
            } else {
                $transaction = Transaction::where('id', $request->id)
                    ->where('user_id', $this->user['id'])
                    ->get();
                if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'bypass']);

                $rules = [
                    'amount' => ['required'],
                    'date' => ['required'],
                    'description' => ['string'],
                    'type' => ['required', 'in:in,out'],
                    'image_id' => ['integer'],
                ];
            }

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) return response()->json($validator->messages());

            $transaction_to_update = Transaction::findOrFail($request->id);

            if ($this->user['admin'] == 1) {
                $transaction_to_update->fill([
                    'status' => $request->status
                ]);
                $transaction_to_update->save();
            } else {
                $transaction_to_update->fill([
                    'user_id' => $this->user['id'],
                    'amount' => $request->amount,
                    'date' => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
                    'description' => $request->description,
                    'type' => $request->type,
                    'image_id' => $request->image_id
                ]);
                $transaction_to_update->save();
            }
            return response()->json($transaction_to_update);
        }
        return response()->json(['success' => false, 'message' => 'error']);
    }

    public function delete($id = false)
    {
        if ($id) {
            if (!$this->user['id']) {
                $transaction = Transaction::where('id', $id)
                    ->where('user_id', $this->user['id'])
                    ->get();
                if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'no_transaction']);
            }

            $transaction_to_delete = Transaction::find($id);
            $transaction_to_delete->delete();
            return response()->json(['success' => true]);
        }
    }

    private function storeImage($request)
    {
        return $request->image->store('public/documents/transactions');
    }
}