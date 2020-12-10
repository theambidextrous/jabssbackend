<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Validator;
use Storage;
use Config;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Pcode;
use App\Models\Pan;
use App\Models\Bank;
use App\Models\Mpesa;
use App\Models\Transaction;
use App\Models\Support;
/** mailables */
use Illuminate\Support\Facades\Mail;
use App\Mail\Code;
use App\Mail\Welcome;
use App\Mail\FundsDelivered;
use App\Mail\CardCharged;
use App\Mail\Receipt;
/** notification */
use App\Notifications\ActivateNotification;
/** AES */
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
/** MPDF */
use PDF;

class TransactionController extends Controller
{
    public function get_faq()
    {
        $f = Support::all();
        if(is_null($f))
        {
            return response([
                'status' => 200,
                'message' => "nothing found",
                'payload' => [],
            ], 200);
        }
        return response([
            'status' => 200,
            'message' => "something found",
            'payload' => $f->toArray(),
        ], 200);
    }
    public function get_trxns()
    {
        $f = Mpesa::where('user', Auth::user()->id)
            ->where('status', true)
            ->skip(0)
            ->take(500)
            ->orderBy('id', 'desc')
            ->get();
        if(is_null($f))
        {
            return response([
                'status' => 200,
                'message' => "nothing found",
                'payload' => [],
            ], 200);
        }
        $f = $f->toArray();
        $trxns = [];
        foreach( $f as $one ):
            $one['amtusd'] = Bank::where('internal_ref', $one['bank_ref'])
            ->first()->amount;
            array_push($trxns, $one);
        endforeach;
        return response([
            'status' => 200,
            'message' => "something found",
            'payload' => $trxns,
        ], 200);
    }
    public function send_trxn($id)
    {
        try {
            $user = Auth::user();
            $mpesa = Mpesa::find($id);
            $bank_amt = Bank::where('internal_ref', $mpesa->bank_ref)
                ->first()->amount;
            $uuuid = (string) Str::uuid() . '.pdf';
            $payload = [
                'ref' => $mpesa->internal_ref,
                'name' => $user->fname,
                'attachment' => $uuuid,
                'to' => $mpesa->receiver,
                'toname' => $mpesa->receiver_name,
                'kes' => $mpesa->amount,
                'usd' => $bank_amt,
                'note' => $mpesa->note,
                'date' => $mpesa->updated_at,
            ];
            PDF::loadView('emails.receipt_attach', [ 'payload' => $payload ], [], [
                'margin_top' => 10
            ])->save(storage_path('trxns/'. $uuuid));
            Mail::to($user->email)->send(new Receipt($payload));
            return response([
                'status' => 200,
                'message' => 'An email containing receipt has been sent!',
                'payload' => [],
            ]);
        } catch ( Exception $e ) {
            return response([
                'status' => -211,
                'message' => $e->getMessage(),
                'payload' => [],
            ]);
        }
    }
    public function addcard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cardname' => 'required|string',
            'mask' => 'required|string',
            'pan' => 'required|string',
            'exp' => 'required|string',
            'fingerprint' => 'required|string',
            'pciprint' => 'required|string',
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Invalid Data Error. All fields are required",
                'errors' => $validator->errors()->all(),
            ], 403);
        }
        $input = $request->all();
        try
        {
            if( $this->has_max_cc() )
            {
                return response([
                    'status' => 201,
                    'message' => "You already have maximum allowed number of cards on your account",
                    'errors' => [],
                ], 403);
            }
            $type_meta = $this->validate_card($this->dec($input['pan']));
            $input['cardtype'] = $type_meta[0];
            $input['icon'] = $type_meta[1];
            $input['user'] = Auth::user()->id;
            if( $this->exists_card($input['pan']) )
            {
                return response([
                    'status' => 201,
                    'message' => "This card already exists",
                    'errors' => [],
                ], 403);
            }
            if( $this->has_cc() )
            {
                $input['isdefault'] = false;
            }
            $panid = Pan::create($input)->id;
            if(strlen($panid))
            {
                return response([
                    'status' => 200,
                    'message' => 'Your '.$input['cardtype'].' card has been added.',
                    'exp' => [],
                ], 200);
            }
            return response([
                'status' => 201,
                'message' => "Your card could not be added",
                'errors' => [],
            ], 403);
        }catch(Exception $e )
        {
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403);
        }
    }
    protected function has_max_cc()
    {
        if( Pan::where('user', Auth::user()->id)->count() == 3 )
        {
            return true;
        }
        return false;
    }
    public function editcard(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cardname' => 'required|string',
            'mask' => 'required|string',
            'pan' => 'required|string',
            'exp' => 'required|string',
            'isdefault' => 'required|boolean',
            'fingerprint' => 'required|string',
            'pciprint' => 'required|string',
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Invalid card data Error. All fields are required",
                'errors' => $validator->errors()->all(),
                'data' => $request->all(),
            ], 403);
        }
        $input = $request->all();
        try
        {
            $type_meta = $this->validate_card($this->dec($input['pan']));
            $input['cardtype'] = $type_meta[0];
            $input['icon'] = $type_meta[1];
            $input['user'] = Auth::user()->id;
            // if( $this->exists_card($input['pan']) )
            // {
            //     return response([
            //         'status' => 201,
            //         'message' => "This card already exists",
            //         'errors' => [],
            //     ], 403);
            // }
            if( $input['isdefault'] )
            {
                $this->set_default_false();
                $input['isdefault'] = true;
            }
            $pan = Pan::find($id);
            $pan->cardname = $input['cardname'];
            $pan->mask = $input['mask'];
            $pan->pan = $input['pan'];
            $pan->exp = $input['exp'];
            $pan->isdefault = $input['isdefault'];
            $pan->fingerprint = $input['fingerprint'];
            $pan->pciprint = $input['pciprint'];
            if($pan->save())
            {
                return response([
                    'status' => 200,
                    'message' => 'Your '.$input['cardtype'].' card has been updated.',
                    'exp' => [],
                ], 200);
            }
            return response([
                'status' => 201,
                'message' => "Your card could not be updated",
                'errors' => [],
            ], 403);
        }catch(Exception $e )
        {
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403);
        }
    }
    protected function set_default_false()
    {
        Pan::where('user', Auth::user()->id)->where('isdefault', true)->update([
            'isdefault' => false,
        ]);
        return true;
    }
    public function validcc ()
    {
        return;
    }
    public function defaultcc()
    {
        return;
    }
    public function delcard($id)
    {
        Pan::find($id)->delete();
        return response([
            'status' => 200,
            'message' => "Card was deleted successfully",
            'payload' => [],
        ], 200);
    }
    public function getcards()
    {
        $p = Pan::select(['mask', 'id', 'icon', 'isdefault'])
            ->where('user', Auth::user()->id)
            ->orderBy('isdefault', 'desc')
            ->get();
        if(is_null($p))
        {
            return response([
                'status' => 200,
                'message' => "No cards were found",
                'payload' => [],
            ], 200);
        }
        return response([
            'status' => 200,
            'message' => "Cards were found",
            'payload' => $p->toArray(),
        ], 200);
    }
    public function getcard($id)
    {
        $p = Pan::find($id);
        if(is_null($p))
        {
            return response([
                'status' => 201,
                'message' => "Card data not found ",
                'payload' => [],
            ], 403);
        }
        return response([
            'status' => 200,
            'message' => "Cards were found",
            'payload' => $p->toArray(),
        ], 200);
    }
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'send_option' => 'required|string',
            // 'receiver_phone' => 'string',
            // 'till_paybill' => 'string',
            // 'account_no' => 'string',
            'amount_usd' => 'required|string',
            'amount_kes' => 'required|string',
            'notes' => 'string',
            'forex_rate' => 'required',
            'forex_used' => 'required',
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Invalid Data Error. All fields are required",
                'errors' => $validator->errors()->all(),
                'data' => $request->all(),
            ], 403);
        }
        $input = $request->all();
        $input['amount_usd'] = trim($input['amount_usd']);
        if( round(floatval($input['amount_usd'])) < 1)
        {
            return response([
                'status' => 201,
                'message' => "Invalid amount in USD. Enter at least $1",
                'errors' => [],
            ], 403);
        }
        try
        {
            $forex_metadata = $this->internal_forex_meta();
            $phone = $receiver = $paybill_till = $account = '';
            $total_charge = 0;
            $receiver_named = $this->rand_names();
            $_bill_charge_amount = 0;
            $is_b2b = false;
            if( intval($input['send_option']) == 1 )
            {
                $receiver = $phone = $this->validate_phone($input['receiver_phone']);
                $total_charge = round(floatval($input['amount_usd']));
            }
            elseif( intval($input['send_option']) == 2 )
            {
                $paybill_till = $receiver = $this->validate_pbill($input['till_paybill']);
                $account = $this->validate_account($input['account_no']);
                $total_charge = $this->find_total_charge($input['amount_usd'], $forex_metadata['bill_charge']);
                $_bill_charge_amount = $this->bill_charge($forex_metadata['bill_charge'], $total_charge);
                $is_b2b = true;
                $receiver_named = $this->rand_names(false);
            }else
            {
                throw new Exception('Invalid send option. Make sure you select either mpesa or paybill');
            }
            if( $total_charge < 1)
            {
                throw new Exception('Invalid Amount. Enter at least $1');
            }
            $superior_amount = $input['amount_usd'];
            $inferior_amount = floor(($input['amount_usd']*$forex_metadata['applied_rate']));
            $bank_int_ref = $this->createCode(32);
            $mpesa_int_ref = $this->createCode(32);
            $bank_api_res = json_encode(['bank' => null, 'status' => 0]);
            $mpesa_api_res = json_encode(['mpesa' => null, 'status' => 0]);
            $new_bank = [
                'user' => Auth::user()->id,
                'internal_ref' => $bank_int_ref,
                'amount' => trim($total_charge),
                'bill_charges' => $_bill_charge_amount,
                'market_rate' => $forex_metadata['market_rate'],
                'applied_rate' => $forex_metadata['applied_rate'],
                'forex_offset' => $forex_metadata['forex_offset'],
                'mpesa_ref' => $mpesa_int_ref,
                'int_payload_string' => $bank_api_res,
                'ext_payload_string' => $bank_api_res
            ];
            $bank_id = Bank::create($new_bank)->id;
            if( !strlen($bank_id) )
            {
                throw new Exception('Transaction failed. Make sure information is correct');
            }
            /** card debited email */
            Mail::to(Auth::user()->email)->send(new CardCharged(json_decode(json_encode([
                'mask' => $this->get_user_d_card()->mask,
                'amount' => $total_charge,
                'kes_amount' => $inferior_amount,
                'receiver' => $receiver
            ]))));
            $new_mpesa = [
                'user' => Auth::user()->id,
                'internal_ref' => $mpesa_int_ref,
                'amount' => $inferior_amount,
                'receiver' => $receiver,
                'account' => $account,
                'send_type' => intval($input['send_option']),
                'note' => $input['notes'],
                'bank_ref' => $bank_int_ref,
                'int_payload_string' => $mpesa_api_res,
                'ext_payload_string' => $mpesa_api_res,
                'receiver_name' => $receiver_named,
                'status' => true,
            ];
            $mpesa_id = Mpesa::create($new_mpesa)->id;
            if( !strlen($mpesa_id) )
            {
                throw new Exception('Transaction failed. Make sure recipient information is correct');
            }
            $s_earnings = 'Ksh.' . $forex_metadata['forex_offset'].'/USD';
            $i_earnings=floor($superior_amount*$forex_metadata['forex_offset']);
            $new_transaction = [
                'user' => Auth::user()->id,
                'internal_ref' => $this->createCode(32),
                'sup_amount' => $superior_amount,
                'inf_amount' => $inferior_amount,
                'bill_charges' => 'USD'.$_bill_charge_amount,
                'market_rate' => $forex_metadata['market_rate'],
                'applied_rate' => $forex_metadata['applied_rate'],
                'forex_offset' => $forex_metadata['forex_offset'],
                'sup_forex_charges' => $s_earnings,
                'inf_forex_charges' => $i_earnings,
                'bank_tran_ref' => $bank_int_ref,
                'mpesa_tran_ref' => $mpesa_int_ref,
            ];
            $transaction_id = Transaction::create($new_transaction)->id;
            if( !strlen($transaction_id) )
            {
                throw new Exception('Transaction failed. Make sure transaction information is correct');
            }
            /** funds delivered email */
            Mail::to(Auth::user()->email)->send(new FundsDelivered(json_decode(json_encode([
                'name' => Auth::user()->fname,
                'kes_amount' => $inferior_amount,
                'receiver' => $receiver,
                'mpesa_code' => 'MJ56H876IL1'
            ]))));
            return response([
                'status' => 200,
                'message' => 'Transaction sent successfully',
                'errors' => [],
            ], 200);
        }catch(Exception $e)
        {
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403);
        }
    }
    protected function get_user_d_card()
    {
        $pan = Pan::where('user', Auth::user()->id)->where('isdefault', true)->first();
        if(is_null($pan))
        {
            throw new Exception('No valid payment source found');
        }
        return $pan;
    }
    protected function bill_charge($percent, $total_charge)
    {
        return round((($percent*$total_charge)/100), 2);
    }
    protected function find_total_charge($amount, $percent)
    {
        $charge = (($amount * $percent)/100);
        return round(($charge + $amount), 2);
    }
    protected function validate_phone($phone)
    {
        $phone = trim($phone);
        if( strlen($phone) == 10 && substr($phone, 0, 1) == '0')
        {
            return '254' . substr($phone, 1);
        }
        throw new Exception('Invalid recipient mpesa phone number. Use 0XXXXXXXXX format e.g. 0722000000');
    }
    protected function validate_pbill($till)
    {
        $till = trim($till);
        if( strlen($till) >= 5 )
        {
            return $till;
        }
        throw new Exception('Invalid paybill or till number. It must be numbers only and at least 5 digits long');
    }
    protected function validate_account($account)
    {
        $account = trim($account);
        if( strlen($account) > 0 )
        {
            return $account;
        }
        throw new Exception('Invalid bill account number. If you have no account no. use the phone number of the person whose bill you are paying');
    }
    public function hascard()
    {
        
        if( $this->has_cc() )
        {
            return response([
                'status' => 200,
                'message' => 'has default card',
                'errors' => [],
            ], 200);
        }
        return response([
            'status' => 201,
            'message' => 'You do not have any default payment card on your account',
            'errors' => [],
        ], 403);
    }
    protected function exists_card($pan)
    {
        $is = Pan::where('pan', $pan)->count();
        if( $is )
        {
            return true;
        }
        return false;
    }
    protected function has_cc()
    {
        $hascard = Pan::where('user', Auth::user()->id)->where('isdefault', true)->count();
        if( $hascard )
        {
            return true;
        }
        return false;
    }
    public function forex_meta()
    {
        $forex_offset = floatval(Config::get('app.forex_offset'));
        // $forex_offset = floatval(6.5);
        $api_rate = $this->fetch_rate();
        if($forex_offset <= 0 )
        {
            return response([
                'status' => 201,
                'message' => "Forex error was encountered",
                'forex_offset' => $forex_offset,
            ], 403);
        }
        if($api_rate <= 0 )
        {
            return response([
                'status' => 201,
                'message' => "Forex data unavailable for the currency",
                'api_rate' => $api_rate,
            ], 403);
        }

        $market_rate = round($api_rate, 5);
        $applied_rate = (round($api_rate, 2) - $forex_offset);
        $payload = [
            'bill_charge' => Config::get('app.bill_charge'),
            'market_rate' => $market_rate,
            'applied_rate' => $applied_rate,
            'forex_offset' => $forex_offset
        ];
        return response([
            'status' => 200,
            'message' => "Forex data fetched",
            'payload' => $payload,
        ], 200);
    }
    public function internal_forex_meta()
    {
        $forex_offset = floatval(Config::get('app.forex_offset'));
        // $forex_offset = floatval(6.5);
        $api_rate = $this->fetch_rate();
        if($forex_offset <= 0 )
        {
            throw new Exception('Forex error was encountered');
        }
        if($api_rate <= 0 )
        {
            throw new Exception('Forex data unavailable for the currency');
        }
        $market_rate = round($api_rate, 5);
        $applied_rate = (round($api_rate, 2) - $forex_offset);
        $payload = [
            'bill_charge' => Config::get('app.bill_charge'),
            'market_rate' => $market_rate,
            'applied_rate' => $applied_rate,
            'forex_offset' => $forex_offset
        ];
        return $payload;
    }
    protected function validate_card($cardNumber)
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        $len = strlen($cardNumber);
        if ($len < 13 || $len > 16) {
            throw new Exception("Invalid credit card number");
        }else{
            switch($cardNumber) {
                case(preg_match ('/^4/', $cardNumber) >= 1):
                    return ['Visa', asset('visa.png')];
                case(preg_match ('/^5[1-5]/', $cardNumber) >= 1):
                    return ['Mastercard', asset('mc.png')];
                default:
                    throw new Exception("Could not determine the credit card type.");
                break;
            }
        }
    }
    protected function fetch_rate()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::get('app.forex_api'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->api_headers());
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        return json_decode($response)->USD_KES;
    }
    protected function createCode($length = 20, $t = 0) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if( $t > 0 ){
            $characters = '0123456789';
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    protected function api_headers()
    {
        return $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
    }
    protected function enc($plain)
    {
        $key = Config::get('app.fingerprint');
        $iv = Config::get('app.thumbprint');
        $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $encrypted;
    }
    public function dec($encrypted){

        // $key = hex2bin("000102030405060708090a0b0c0d0e0f");
        // $iv =  hex2bin("101112131415161718191a1b1c1d1e1f");
        $key = hex2bin(Config::get('app.fingerprint'));
        $iv =  hex2bin(Config::get('app.thumbprint'));
        $decrypted = openssl_decrypt($encrypted, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv); 
    
        $decrypted = trim($decrypted); // you have to trim it according to https://stackoverflow.com/a/29511152
    
        return $decrypted;
    }

    protected function rand_names($k = true) 
    {
        if($k)
        {
            $r = [ 'Idd juma', 'Irine Kim', 'Mosses Kuria', 'Alita Skylar', 'Stellah O. Stellah', 'Tina Kuria','Caren Masai'];
            $i = array_rand($r);
            return $r[$i];
        }
        $r = [ 'KPLC Post Paid', 'Zuku WiFi EA LTD', 'Quickmat Stores - Lavington', 'Naivas Store Nakuru', 'Nairobi Water', 'Makini Realators','Strathmore University'];
        $i = array_rand($r);
        return $r[$i];
    }
}
