<?php


namespace App\Http\Controllers;

use PHPMailer;
use App\Models\User;
use App\Models\Challenge;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
//use Tymon\JWTAuth\JWTAuth;
use JWTAuth;

class UserController extends Controller {
	

	protected $jwt;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }
	
	/*
	0 = No such user
	*/
	
	// first_name, last_name, email, phone, password, confirm_password
	
		public function test(Request $request)
		{		
		
		$parameters = $request->all();
		$rules1 = [
				'firstname' => 'required',
				'lastname' => 'required',
		        'email' => 'required',
		        'phone' => 'required',
		        'password' => 'required'
		    ];
		$validator1 = Validator::make($parameters, $rules1);

		if ($validator1->fails()) {
			$errors = ['error' => 'validation_failed', 'code' => '0'];

			foreach ((array)$validator1->errors()->messages() as $key => $value) {
				$errors['fields'][] = $key;
			}
			return response()->json($errors);
		}

		$user = User::firstOrNew(['email' => $parameters['email']]);
		$user->firstname=$parameters['firstname'];
		$user->lastname=$parameters['lastname'];
		$user->phone=$parameters['phone'];
		$user->salt=bin2hex(random_bytes(32));
		$password=$parameters['password'];
		$user->password_hash=password_hash($user->salt.$password,PASSWORD_BCRYPT);
		$code = random_int(100000, 999999);
		$user->verification_code=$code;
		$user->save();
		//--------------------------------------------------------
			// PHP Mail Function	
		//ini_set("SMTP","ssl://smtp.gmail.com");
		ini_set("smtp_port","587");
		ini_set("sendmail_path ","D:\wamp64\sendmail\sendmail.exe");
		
		$to = "suya123.suyash@gmail.com";
        $subject = "Incognito : Verification Code";
         
        $message = "<b>Incognito : Secure Chat .</b>";
        $message .= "Verification code for Incognito = ";
		$message .=$code;
         
        $header = "From:suya123.suyash@gmail.com \r\n";
        $header .= "Cc:\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html\r\n";

		$retval=mail($to,$subject,$message,$header);
		if( $retval == true ) {
            return response()->json(['message'=>'User Registered Successfully! Verification Code sent!']);
         }else {
            return response()->json(['message'=>'User Registered Failed!']);
         }
		
		//$token = $this->jwt->attempt($request->only('email', 'password'));
		//$token = $user->fromUser($user);
		//$user->token = compact('token')['token'];
		
		//return response()->json(['message'=>'User Registered Successfully!']);
	}
	
	
	public function verifyUser(Request $request)
	{
		$params = $request->all();
		$email =$params['email'];
		//$user = User::find(1);
		$user = User::where('email','=',$email)->first();
		if(empty($user)) {
			
			$errors = ['error' => 'User Not Verified', 'code' => '6'];
		   	return response()->json($errors);
		}else{
			//echo($user->verification_code);
			if(strcmp($user->verification_code, $params['verification_code']) == 0) {
				$user->verification_code = '';
				$user->is_verified = true;
				$user->save();
				$token=JWTAuth::fromUser($user);
				//$token = $this->jwt->attempt($params);
				$user->token = compact('token')['token'];

				return response()->json($token);
			} else {
				// Invalid verification code.
				$errors = ['error' => 'Couldn\'t verify', 'code' => '5'];
				return response()->json($errors);
			
			}
			

		}
				
	}
	
	
	public function remoteLogin(Request $request, $part)
	{
		if($part==1)
		{
			$params = $request->all();
			$rules1 = [
			        'email' => 'required'
			    ];
			$validator1 = Validator::make($params, $rules1);
			if($validator1->fails()) {
				$errors = ['error' => 'Missing parameters', 'code' => '0'];
				return response()->json($errors);
			} else {
				$user = User::where('email', $params['email'])->first();
				if(empty($user)) {
					$errors = ['error' => 'Invalid Email', 'code' => '0'];
					return response()->json($errors);
				} else {
					$cval = Challenge::firstOrCreate(['email' => $params['email']]);
					$cval->challenge = bin2hex(random_bytes(32));
					$cval->save();
					
					$response =['c'=>$cval->challenge , 'salt'=>$user->salt];
					
					return response()->json($response);
				}
			}
			
		}else if($part==2)
		{
			$params = $request->all();
			$rules2 = [
			        'email' => 'required',
			        'tag' => 'required'
			    ];
			
			$validator1 = Validator::make($params, $rules2);

			if($validator1->fails()) {
				$errors = ['error' => 'Missing parameters', 'code' => '0'];
				return response()->json($errors);
			} else {
				$user = User::where('email', $parameters['email'])->first();
				$challenge = Challenge::where('email', $parameters['email'])->first();
				$tag=$params['tag'];
				$mytag=hash_hmac('sha512',$user->password_hash,$challenge->challenge);
				if(strcmp($mytag,$tag)==0)
				{
					$challenge->delete();
					$token=JWTAuth::fromUser($user);
					$user->token = compact('token')['token'];
					return response()->json($token);
				}else {
						$errors = ['error' => 'Invalid Password', 'code' => '0'];
						return response()->json($errors);
					}
			}
		}
		
	}
				
			
	//}
}
