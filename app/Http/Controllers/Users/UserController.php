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
use Carbon\Carbon;
use App\Models\User;
use App\Models\Pcode;
use App\Models\Address;
use App\Models\Preference;
/** mailables */
use Illuminate\Support\Facades\Mail;
use App\Mail\Code;
use App\Mail\Welcome;
/** notification */
use App\Notifications\ActivateNotification;

class UserController extends Controller
{

    public function signup(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'fname' => 'required|string',
                'lname' => 'required|string',
                'address' => 'required|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'zip' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
                'password' => 'required|string',
            ]);
            if( $validator->fails() ){
                return response([
                    'status' => 201,
                    'message' => "Invalid Data Error. All fields are required",
                    'errors' => $validator->errors()->all(),
                ], 403);
            }
            $input = $request->all();
            if( User::where('email', $input['email'])->count() )
            {
                return response([
                    'status' => 202,
                    'message' => "Duplicate Data Error. Email address is already used",
                    'errors' => $validator->errors()->all(),
                ], 403);
            }
            if( User::where('phone', $input['phone'])->count() )
            {
                return response([
                    'status' => 203,
                    'message' => "Duplicate Data Error. Phone number is already used",
                    'errors' => $validator->errors()->all(),
                ], 403);
            }
            $input['password'] = Hash::make($input['password']);
            $input['pic'] = asset('default.png');
            $user = User::create($input);
            $access_token = $user->createToken('authToken')->accessToken;
            $user['token'] = $access_token;
            if(!strlen($user['token']))
            {
                return response([
                    'status' => 201,
                    'message' => "Authentication Error. Access denied for user " . $input['email'],
                    'errors' => [],
                ], 403);
            }
            Pcode::where('email', $input['email'])->update(['used' => true]);
            $code = $this->createCode(6,1);
            $p_data = ['email' => $input['email'], 'code' => $code ];
            if(!Pcode::create($p_data))
            {
                return response([
                    'status' => 201,
                    'message' => "Verification code error ",
                    'errors' => [],
                ], 403);
            }
            Mail::to($input['email'])->send(new Code($code));
            Preference::create(['user' => $user['id']]);
            return response([
                'status' => 200,
                'message' => 'User Account created. Verification code is sent to your email',
                'payload' => $user,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response([
                'status' => 201,
                'message' => "Duplicate Data Error. Invalid data",
                'errors' => [],
            ], 403);
        } catch (PDOException $e) {
            return response([
                'status' => 201,
                'message' => "Data store connection error. Invalid data",
                'errors' => [],
            ], 403);
        }
    }
    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Invalid username and password",
                'errors' => $validator->errors()->all(),
            ], 403);
        }
        $login = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);
        if( !Auth::attempt( $login ) )
        {
            return response([
                'status' => 201,
                'message' => "Invalid username or password. Try again",
                'errors' => [],
            ], 403);
        }
        $accessToken = Auth::user()->createToken('authToken')->accessToken;
        $user = Auth::user();
        if( is_null(Auth::user()->email_verified_at) )
        {
            return response([
                'status' => 201,
                'message' => "Account not verified. Verify your account through password reset",
                'errors' => [],
            ], 403);
        }
        $user['token'] = $accessToken;
        return response([
            'status' => 200,
            'message' => 'Success. logged in',
            'payload' => $user,
        ], 200);
    }
    public function d_token($pushToken)
    {
        User::find(Auth::user()->id)->update(['device_token' => $pushToken]);
        return response([
            'status' => 200,
            'message' => "device token updated",
            'payload' => $pushToken,
        ], 200);
    }
    public function test_push()
    {
        $this->push_notify('Login success. Welcome to WEL', Auth::user()->id, Auth::user()->id . '-WELstudent');
        return response([
            'status' => 200,
            'message' => "push test",
        ], 200);
    }
    public function push_notify($msg, $userid, $channel)
    {
        try{
            $user = User::find($userid);
            if(!is_null($user) && !is_null($user->device_token))
            {
                $payload = [
                    'expoToken' => $user->device_token,
                    'message' => $msg,
                    'title' => 'Women Empowerment Link - WEL',
                    'channel' => $channel
                ];
                $payload = json_decode(json_encode($payload));
                return $user->notify(new ActivateNotification($payload));
            }
            return;
        }catch(Exception $e ){
            return $e->getMessage();
        }
    }
    public function updatepic(Request $request)
    {
        try{
            $input = [];
            if( $request->hasFile('photo') )
            {
                $content = $request->file('photo');
                $exten = strtolower($content->getClientOriginalExtension());
                $content_name = Auth::user()->id . time() . '.' . $exten;
                $request->file('photo')->move(public_path(), $content_name);
                $input['pic'] = asset($content_name);
                /** */
                $user = User::find(Auth::user()->id)->update($input);
                return response([
                    'status' => 200,
                    'message' => 'Success. Account updated',
                    'payload' => $user,
                ], 200);
            }
            return response([
                'status' => 201,
                'message' => 'Invalid photo selected. Try again',
                'payload' => [],
            ], 403);
        } catch (\Illuminate\Database\QueryException $e) {
            return response([
                'status' => 201,
                'message' => "Server error. Invalid data",
                'errors' => [],
            ], 403);
        } catch (PDOException $e) {
            return response([
                'status' => 201,
                'message' => "Storage error. Invalid data",
                'errors' => [],
            ], 403);
        }
    }
    public function userinfo()
    {
        $user = User::find(Auth::user()->id);
        $user['token'] = Auth::user()->createToken('authToken')->accessToken;
        return response([
            'status' => 200,
            'message' => "User fetched",
            'payload' => $user,
        ],200);
    }
    public function updateinfo(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'fname' => 'required|string',
                'lname' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
            ]);
            if( $validator->fails() ){
                return response([
                    'status' => 201,
                    'message' => "Invalid data Error. All fields are required",
                    'errors' => $validator->errors(),
                ], 403);
            }
            $input = $request->all();
            $this->validate_phone($input['phone']);
            User::find(Auth::user()->id)->update($input);
            /** get new */
            $user = User::find(Auth::user()->id);
            $user['token'] = Auth::user()->createToken('authToken')->accessToken;
            return response([
                'status' => 200,
                'message' => 'Success. Account updated',
                'payload' => $user,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response([
                'status' => 201,
                'message' => "Server error. Invalid data",
                'errors' => [],
            ], 403);
        } catch (PDOException $e) {
            return response([
                'status' => 201,
                'message' => "Storage error. Invalid data",
                'errors' => [],
            ], 403);
        }catch (Exception $e) {
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403);
        }
    }
    public function addressadd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip' => 'required',
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Invalid data Error. All fields are required",
                'errors' => $validator->errors(),
            ], 403);
        }
        $input = $request->all();
        $input['user'] = Auth::user()->id;
        $id = Address::create($input)->id;
        if($id)
        {
            return response([
                'status' => 200,
                'message' => "Address added",
                'errors' => [],
            ], 200); 
        }
        return response([
            'status' => 201,
            'message' => "Connection error. We could not add your address, try again later",
            'errors' => [],
        ], 403); 
    }
    public function get_pref()
    {
        $p = Preference::where('user', Auth::user()->id)->first();
        if( is_null($p) )
        {
            return response([
                'status' => 200,
                'message' => "found",
                'payload' => [],
            ], 200); 
        }
        return response([
            'status' => 200,
            'message' => "found",
            'payload' => $p->toArray(),
        ], 200); 

    }
    public function edit_pref(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'opt' => 'required',
            'case' => 'required|string',
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => "Error occured. Could not update your preferences",
                'errors' => $validator->errors(),
            ], 403);
        }
        $input = $request->all();
        $user = Auth::user()->id;
        switch($input['case']){
            case 'news':
                Preference::where('user', $user)->first()->update([
                    'news' => $input['opt']
                ]);
                return response([
                    'status' => 200,
                    'message' => "Preference updated successfully",
                    'errors' => [],
                ], 200);
            break;
            case 'tran':
                Preference::where('user', $user)->first()->update([
                    'tran' => $input['opt']
                ]);
                return response([
                    'status' => 200,
                    'message' => "Preference updated successfully",
                    'errors' => [],
                ], 200);
            break;
            case 'expiry':
                Preference::where('user', $user)->first()->update([
                    'expiry' => $input['opt']
                ]);
                return response([
                    'status' => 200,
                    'message' => "Preference updated successfully",
                    'errors' => [],
                ], 200);
            break;
            case 'failed_tran':
                Preference::where('user', $user)->first()->update([
                    'failed_tran' => $input['opt']
                ]);
                return response([
                    'status' => 200,
                    'message' => "Preference updated successfully",
                    'errors' => [],
                ], 200);
            break;
            default:
                return response([
                    'status' => 201,
                    'message' => "Preference update failed",
                    'errors' => [],
                ], 403);
        }
    }
    public function addr_get()
    {
        $address = Address::where('user', Auth::user()->id)->where('deleted', false)->get();
        if(is_null($address))
        {
            return response([
                'status' => 200,
                'message' => "fetched",
                'payload' => [],
            ], 200); 
        }
        return response([
            'status' => 200,
            'message' => "fetched",
            'payload' => $address->toArray(),
        ], 200); 
    }
    public function deladdress($id)
    {
        Address::find($id)->update(['deleted' => true ]);
        return response([
            'status' => 200,
            'message' => "deleted",
            'payload' => [],
        ], 200); 
    }
    public function resendcode()
    {
        try{
            Pcode::where('email', Auth::user()->email )->update(['used' => true]);
            $code = $this->createCode(6,1);
            $data = ['email' => Auth::user()->email, 'code' => $code ];
            if( Pcode::create($data) )
            {
                Mail::to($data['email'])->send(new Code($code));
                return response([
                    'status' => 200,
                    'message' => "A verification code has been sent to your email address",
                    'errors' => [],
                ], 200); 
            }
            return response([
                'status' => 201,
                'message' => "Error sending email. Try again later",
                'errors' => [],
            ], 403); 
            
        }catch( Exception $e){
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403); 
        }
    }
    public function reqreset($email)
    {
        try{
            $user = User::where('email', $email)->count();
            if(!$user){
                return response([
                    'status' => 201,
                    'message' => "There is no user with that email. Create account",
                    'errors' => [],
                ], 403); 
            }
            Pcode::where('email', $email)->update(['used' => true]);
            $code = $this->createCode(6,1);
            $data = ['email' => $email, 'code' => $code ];
            if( Pcode::create($data) )
            {
                Mail::to($data['email'])->send(new Code($code));
                return response([
                    'status' => 200,
                    'message' => "A one time password has been sent to your email address",
                    'errors' => [],
                ], 200); 
            }
            return response([
                'status' => 201,
                'message' => "Error sending email",
                'errors' => [],
            ], 403); 
            
        }catch( Exception $e){
            return response([
                'status' => 201,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 403); 
        }
    }
    public function verifysignup($code)
    {
        $email = Auth::user()->email;
        try{
            $data = ['email' => $email, 'code' => $code ];
            $isValid = Pcode::where('email', $email)
                ->where('code', $code)
                ->where('used', false)
                // ->where('created_at', '>=', Carbon::parse('-5 minutes'))
                ->orderBy('created_at', 'desc')
                ->first();
            if( !is_null($isValid) )
            {
                $isValid->used = true;
                User::where('email', $email)->update([ 
                    'email_verified_at' => date('Y-m-d H:i:s') 
                ]);
                $isValid->save();
                return response([
                    'status' => 200,
                    'message' => "Account verified!",
                    'payload' => $data,
                    'errors' => [],
                ], 200); 
            }
            return response([
                'status' => 201,
                'message' => "You entered Invalid Verification Code " . $code.' for user '.$email,
                'errors' => [],
            ], 403); 
        }catch( Exception $e){
            return response([
                'status' => 201,
                'message' => "Invalid Access. No data",
                'errors' => [],
            ], 403); 
        }
    }
    public function verifyreset($code, $email)
    {
        try{
            $data = ['email' => $email, 'code' => $code ];
            $isValid = Pcode::where('email', $email)
                ->where('code', $code)
                ->where('used', false)
                // ->where('created_at', '>=', Carbon::parse('-5 minutes'))
                ->orderBy('created_at', 'desc')
                ->first();
            if( !is_null($isValid) )
            {
                $isValid->used = true;
                User::where('email', $email)->update([ 
                    'email_verified_at' => date('Y-m-d H:i:s') 
                ]);
                $isValid->save();
                return response([
                    'status' => 200,
                    'message' => "Code verified!",
                    'payload' => $data,
                    'errors' => [],
                ], 200); 
            }
            return response([
                'status' => 201,
                'message' => "You entered Invalid Verification Code " . $code.' for user '.$email,
                'errors' => [],
            ], 403); 
        }catch( Exception $e){
            return response([
                'status' => 201,
                'message' => "Invalid Access. No data",
                'errors' => [],
            ], 403); 
        }
    }
    public function finishreset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
            'c_password' => 'required|same:password'
        ]);
        if( $validator->fails() ){
            return response([
                'status' => 201,
                'message' => 'Passwords do no match',
                'errors' => $validator->errors()
            ], 403);
        }
        $email = $request->get('email');
        $user = User::where('email', $email)->first();
        if(!is_null($user)){
            $user->password = Hash::make($request->get('password'));
            $user->save();
            return response([
                'status' => 200,
                'message' => 'Password was reset, Login now',
                'payload' => $email
            ], 200);
        }
        return response([
            'status' => 201,
            'message' => 'Data error. We could not updte password',
            'errors' => []
        ], 403);
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
    protected function validate_phone($phone)
    {
        $phone = trim($phone);
        if( strlen($phone) == 10 )
        {
            return true;
        }
        throw new Exception('Invalid phone format. enter exactly 10 numbers without spaces');
    }
}
