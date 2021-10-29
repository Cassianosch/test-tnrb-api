<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Response;

use App\Models\Transaction;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
        if ($this->user['admin'] == 1)
            return response()->json(['success' => false, "message" => 'bypass'], 401);

        $rules = $this->validationCustomer();

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails())
            return response()->json($validator->messages(), 400);

        if($request->type == 'out') {
            $transactions_in_accepted = Transaction::where('user_id', $this->user['id'])
                                                    ->where('type', 'in')
                                                    ->where('status', 'accepted')
                                                    ->sum('amount');

            $user_new_balance = $transactions_in_accepted - $request->amount;
            if($user_new_balance < 0)
                return response()->json(['success' => false, "message" => ['You don`t have enough funds']], 400);
        }

        if (!empty($request['image'])) $image_saved = $this->storeImage($request);

        $newTransaction   = Transaction::create([
            'user_id'     => $this->user['id'],
            'amount'      => $request->amount,
            'date'        => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
            'description' => $request->description,
            'type'        => $request->type,
            'image'       => !empty($image_saved) ? $image_saved : null
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
            if ($transaction->isEmpty())
                return response()->json(['success' => false, "message" => 'bypass'], 401);

            return response()->json($transaction);
        }
        return response()->json(['success' => false, "message" => 'no_id']);
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

                if ($transaction->isEmpty())
                    return response()->json(['success' => false, "message" => 'bypass'], 401);

                $rules = $this->validationCustomer();
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails())
                return response()->json($validator->messages(), 400);

            $transaction_to_update = Transaction::findOrFail($request->id);

            if ($this->user['admin'] == 1) {

                $transaction_to_update->fill([
                    'status' => $request->status
                ]);
                $transaction_to_update->save();

            } else {

                $image_saved = false;
                if (!empty($request['image'])) $image_saved = $this->storeImage($request);

                if($request->type == 'out') {

                    $user_new_balance = ($this->getCurrentBalance() + $transaction_to_update['amount']) - $request->amount;

                    if($user_new_balance < 0)
                        return response()->json(['success' => false, "message" => ['You don`t have enough funds']], 400);
                }

                if($request->filled('image')) {
                    $transaction_to_update->fill([
                        'user_id'     => $this->user['id'],
                        'amount'      => $request->amount,
                        'date'        => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
                        'description' => $request->description,
                        'type'        => $request->type,
                        'image'       => $image_saved
                    ]);
                } else {
                    $transaction_to_update->fill([
                        'user_id'     => $this->user['id'],
                        'amount'      => $request->amount,
                        'date'        => Carbon::parse($request->date)->format('Y-m-d H:m:s'),
                        'description' => $request->description,
                        'type'        => $request->type,
                    ]);
                }

                if ($transaction_to_update->save()) {
                    return response()->json($transaction_to_update);
                }
            }
        } else {
            return response()->json(['success' => false, 'message' => ['error']], 400);
        }
    }

    public function delete($id = false)
    {
        if ($id) {
            if (!$this->user['id']) {
                $transaction = Transaction::where('id', $id)
                                            ->where('user_id', $this->user['id'])
                                            ->get();

                if ($transaction->isEmpty())
                    return response()->json(['success' => false, "message" => 'no_transaction'], 401);
            }

            $transaction_to_delete = Transaction::find($id);
            $transaction_to_delete->delete();
            return response()->json(['success' => true]);
        }
    }

    public function getImage($id, $token)
    {
        if(empty($id))
            return response()->json(['success' => false, "message" => 'bypass'], 401);

        $transactions = Transaction::query()
                                    ->where('id', $id);

        if ($this->user['admin'] == 0)
            $transactions = $transactions->where('user_id', $this->user['id']);

        $transaction = $transactions->get();

        if ($transaction->isEmpty())
            return response()->json(['success' => false, "message" => 'no_transaction'], 401);

        $img  = $transaction[0]['image'];
        $file = Storage::get($img);
        $type = Storage::mimeType($img);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }

    public function getBalance(Request $request)
    {
        if ($this->user['admin'] == 1)
            return response()->json(['success' => false, "message" => 'no_need_access'], 401);

        $transactions = Transaction::query()
                                    ->where('user_id', $this->user['id']);

        if(!empty($request->query('period'))) {
            $date_exploded = explode('-', $request->query('period'));
            $transactions  = $transactions->whereYear('date', $date_exploded[0]);
            $transactions  = $transactions->whereMonth('date', $date_exploded[1]);
        }

        if(!empty($request->query('type'))) {
            if($request->query('type') == 'in')  $transactions = $transactions->where('type', 'in');
            if($request->query('type') == 'out') $transactions = $transactions->where('type', 'out');
        }

        $result = $transactions->get();

        $value_positive  = 0;
        $value_negative  = 0;
        $current_balance = 0;

        foreach ($result as $key => $value) {
            if($value['type'] == 'in' && $value['status'] == 'accepted') $value_positive += $value['amount'];
            if($value['type'] == 'out')                                  $value_negative -= $value['amount'];
        }

        return response()->json([
            'balance'      => $this->getCurrentBalance(),
            'positive'     => $value_positive,
            'negative'     => $value_negative,
            'transactions' => $result
        ]);
    }

    public function getIncomes(Request $request)
    {
        if ($this->user['admin'] == 1)
            return response()->json(['success' => false, "message" => 'no_need_access'], 401);

        $transactions = Transaction::query()
                                    ->where('user_id', $this->user['id']);

        if(!empty($request->query('period'))) {
            $date_exploded = explode('-', $request->query('period'));
            $transactions  = $transactions->whereYear('date', $date_exploded[0]);
            $transactions  = $transactions->whereMonth('date', $date_exploded[1]);
        }

        $transactions = $transactions->where('type', 'in');

        $result = $transactions->get();

        $elements['pending']  = [];
        $elements['accepted'] = [];
        $elements['rejected'] = [];

        foreach ($result as $key => $value) {
            if($value['status'] == 'pending')  $elements['pending'][]  = $value;
            if($value['status'] == 'accepted') $elements['accepted'][] = $value;
            if($value['status'] == 'rejected') $elements['rejected'][] = $value;
        }
        return response()->json([
            'pending'  => $elements['pending'],
            'accepted' => $elements['accepted'],
            'rejected' => $elements['rejected'],
        ]);
    }

    private function storeImage($request)
    {
        return $request->image->store('public/images/transactions');
    }

    private function validationCustomer()
    {
        return [
            'amount'      => ['required'],
            'date'        => ['required'],
            'description' => ['string'],
            'type'        => ['required', 'in:in,out'],
            'image'       => ['sometimes','mimes:jpg,png', 'max:4096'],
        ];
    }

    private function getCurrentBalance()
    {
        $transactions_in_accepted = Transaction::where('user_id', $this->user['id'])
        ->where('type', 'in')
        ->where('status', 'accepted')
        ->sum('amount');

        $transactions_out = Transaction::where('user_id', $this->user['id'])
                                        ->where('type', 'out')
                                        ->sum('amount');

        return $transactions_in_accepted - $transactions_out;
    }
}
