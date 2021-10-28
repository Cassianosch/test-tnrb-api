<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Response;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

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
    public function balance(Request $request)
    {
        $transactions = Transaction::query()
            ->where('user_id', $this->user['id']);

        if(!empty($request->query('period'))) {
            $date_exploded = explode('-', $request->query('period'));
            $transactions = $transactions->whereMonth('date', $date_exploded[0]);
            $transactions = $transactions->whereYear('date', $date_exploded[1]);
        }

        if(!empty($request->query('type'))) {
            if($request->query('type') == 'positive') $transactions = $transactions->where('type', 'in');
            if($request->query('type') == 'negative') $transactions = $transactions->where('type', 'out');
        }

        $result = $transactions->get();

        $value_positive = 0;
        $value_negative = 0;
        $balance = 0;

        foreach ($result as $key => $value) {
            if($value['type'] == 'in' && $value['status'] == 'accepted') $value_positive += $value['amount'];
            if($value['type'] == 'out') $value_negative -= $value['amount'];
        }
        return response()->json([
            'balance' => ($value_positive + $value_negative),
            'positive' => $value_positive,
            'negative' => $value_negative,
            'transactions' => $result
        ]);
    }

    public function store(Request $request)
    {
        if ($this->user['admin'] == 1) return response()->json(['success' => false, "message" => 'bypass']);

        $rules = $this->validationCustomer();

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) return response()->json($validator->messages());

        if (!empty($request['image'])) $image_saved = $this->storeImage($request);

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
                ->limit(1)
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

                $rules = $this->validationCustomer();
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

                if (!empty($request['image'])) $image_saved = $this->storeImage($request);

                $transaction_to_update->fill([
                    'user_id' => $this->user['id'],
                    'amount' => $request->amount,
                    'date' => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
                    'description' => $request->description,
                    'type' => $request->type,
                    'image' => $image_saved
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

    public function image($id, $token) {
        if(empty($id)) return response()->json(['success' => false, "message" => 'bypass']);

        $transactions = Transaction::query()
            ->where('id', $id);

        if ($this->user['admin'] == 0) $transactions = $transactions->where('user_id', $this->user['id']);

        $transaction = $transactions->get();
        if ($transaction->isEmpty()) return response()->json(['success' => false, "message" => 'no_transaction']);

        $img = $transaction[0]['image'];
        $file = Storage::get($img);
        $type = Storage::mimeType($img);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }

    private function storeImage($request)
    {
        return $request->image->store('public/images/transactions');
    }

    private function validationCustomer()
    {
        return [
            'amount' => ['required'],
            'date' => ['required'],
            'description' => ['string'],
            'type' => ['required', 'in:in,out'],
            'image' => ['mimes:jpg,png', 'max:4096'],
        ];
    }
}
