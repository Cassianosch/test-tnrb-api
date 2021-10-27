<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Transactions;
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
            $transactions = Transactions::where('status', 'pending')
                ->where('type', 'in')
                ->get()
                ->load('user');
        } else {
            $transactions = Transactions::where('user_id', $this->user['id'])->get();
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
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) return response()->json($validator->messages());

        $newTransaction = Transactions::create([
            'user_id' => $this->user['id'],
            'amount' => $request->amount,
            'date' => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
            'description' => $request->description,
            'type' => $request->type
        ]);
        return response()->json($newTransaction);
    }
    public function show($id = false)
    {
        if ($id) {
            $transactions = Transactions::where('id', $id)
                ->where('user_id', $this->user['id'])
                ->get();
            return response()->json($transactions);
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
                $transaction = Transactions::where('id', $request->id)
                    ->where('user_id', $this->user['id'])
                    ->get();
                if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'bypass']);

                $rules = [
                    'amount' => ['required'],
                    'date' => ['required'],
                    'description' => ['string'],
                    'type' => ['required', 'in:in,out'],
                ];
            }

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) return response()->json($validator->messages());

            $transaction_to_update = Transactions::findOrFail($request->id);

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
                    'type' => $request->type
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
            $transaction = Transactions::where('id', $id)
                ->where('user_id', $this->user['admin'])
                ->get();
            if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'no_transaction']);

            $transaction_to_delete = Transactions::find($id);
            $transaction_to_delete->delete();
            return response()->json(['success' => true]);
        }
    }
}