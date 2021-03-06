<?php
/**
 * Created by PhpStorm.
 * User: mnvoh
 * Date: 11/9/15
 * Time: 4:39 PM
 */

namespace App\Http\Controllers;



use App\Helpers\Utilities;
use App\KuTranslation;
use App\Topic;
use App\User;
use App\DeleteRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class MainController
 * @package App\Http\Controllers
 */
class MainController extends Controller
{
	/**
	 * Handles the '/' path. If any user is authenticated redirects to
	 * his/her corresponding home page. Otherwise redirects to the login page.
	 * @return \Illuminate\Http\RedirectResponse
	 */
	 
	public function __construct()
	{
		//$this->middleware('PasswordChange', ['except' => 'editAccount']);
		
		view()->share('delete_recommendations_num', 
			DeleteRecommendation::where('viewed', 0)
			->select('delete_recommendations.id', 'topics.topic')
			->join('topics', 'delete_recommendations.topic_id', '=', 'topics.id')
			//->whereNull('topics.deleted_at')
			->count()
		);
	}
	
	public function index()
	{
		if(Auth::check()) { // user is authenticated.
			$user = Auth::user();
			if($user->user_type == 'admin') {
				return redirect()->route('admin.home');
			}
			elseif($user->user_type == 'inspector') {
				return redirect()->route('inspector.home');
			}
			else {
				return redirect()->route('translator.home');
			}
		}
		else { // User is not authenticated
			return redirect()->route('auth.login');
		}
	}

	public function editAccount(Request $request, $user_id)
	{
		if(!Auth::check()) {
			abort(401, 'You must be logged in to access this area.');
			return null;
		}

		$current_user = Auth::user();
		$user = User::where('id', $user_id)->first();

		if(!$user_id) {
			return redirect()->route('main.root');
		}

		if(!$user) {
			abort(404, 'User not found!');
			return null;
		}

		$is_current_admin 	= $current_user->user_type == 'admin';
		$is_admin 			= $user->user_type == 'admin';
		$is_editor_owner 	= $user->id == $current_user->id;
		$is_master_admin 	= $current_user->id <= 1;

		$unauthorized = (!$is_current_admin && !$is_editor_owner) ||
						($is_admin && !$is_editor_owner && !$is_master_admin);


		if($unauthorized) {
			abort(401, 'You cannot edit this account.');
			return null;
		}

		$action_error = false;
		$action_result = null;

		if($request->has('save')) {
			$password = $request->get('password', '');
			$cpassword = $request->get('cpassword', '');
			
			if($password){
				if(strlen($password) < 8) {
					$action_error = true;
					$action_result = trans('common.password_too_short');
				}
				elseif(strcmp($password, $cpassword) != 0){
					$action_error = true;
					$action_result = trans('common.passwords_not_match');
				}
				else {
					if($is_current_admin){
						if($request->get('name'))
							$user->name = $request->get('name');
						if($request->get('surname'))
							$user->surname = $request->get('surname');
						if($request->get('email'))
							$user->email = $request->get('email');
					}
					$user->password = bcrypt($request->get('password'));
					$user->save();
					
					$action_result = trans('common.account_info_saved');
				}
			}
			else {
				if($is_current_admin){
					if($request->get('name'))
						$user->name = $request->get('name');
					if($request->get('surname'))
						$user->surname = $request->get('surname');
					if($request->get('email'))
						$user->email = $request->get('email');
				}
				
				//$user->password = bcrypt($request->get('password'));
				$user->save();
				$action_result = trans('common.account_info_saved');
			}
		}

		return view('edit_account', [
			'user' 			=> $user,
			'action_result' => $action_result,
			'action_error' 	=> $action_error,
		]);
	}

	public function setPassword(Request $request, $one_time_string)
	{
		if(Auth::check()) {
			abort(403, 'Logged in users cant access this area.');
			return null;
		}
		$action_error = false;
		$action_result = null;
		
		if($request->has('save')) {
			$user = User::where('one_time_string', '-' . $one_time_string)->first();
			if($user){
				$password = $request->get('password', '');
				$cpassword = $request->get('cpassword', '');
				
				if(strlen($password) < 8) {
					$action_error = true;
					$action_result = trans('common.password_too_short');
				}
				elseif(strcmp($password, $cpassword) != 0){
					$action_error = true;
					$action_result = trans('common.passwords_not_match');
				}
				else {
					$user->password = bcrypt($password);
					$user->one_time_string = NULL;
					$user->save();
					
					return redirect()->route('auth.login');
				}
				return view('setpassword', [
					'user' 			=> $user,
					'action_result' => $action_result,
					'action_error' 	=> $action_error,
				]);
			}else{
				abort(403, 'You cannot access this area.');
				return null;
			}
		}else{
			$user = User::where('one_time_string', $one_time_string)->first();
			
			if($user){
				$user->one_time_string = "-" . $user->one_time_string;
				$user->save();
				return view('setpassword', [
					'user' 			=> $user,
					'action_result' => $action_result,
					'action_error' 	=> $action_error,
				]);
			}else{
				abort(403, 'You cannot access this area.');
				return null;
			}
		}
	}

	public function suggestions(Request $request)
	{
		if(!Auth::check()) {
			abort(401, 'You must be logged in to access this area.');
			return null;
		}

		$partial_topic = str_replace(' ', '_', trim($request->get('topic', '')));
		$topics = Topic::where('topic', 'LIKE', "%{$partial_topic}%")
			->take(50)->get();
		$topics_array = [];
		foreach($topics as $topic) {
			$topics_array[] = $topic->topic;
		}

		return response()->json([
			'topics' => $topics_array,
		]);
	}

	public function peek(Request $request)
	{
		if(!Auth::check()) {
			abort(401, 'You must be logged in to access this area.');
			return null;
		}
		$current_user = Auth::user();
		$topic = str_replace(' ', '_', trim($request->get('topic', '')));
		if(!strlen($topic))
			$topic = null;
		Utilities::updateTopicFromWikipedia($topic);
		$topic = Topic::where('topic', $topic)->first();
		
		if(!$topic OR ($topic->user_id != NULL AND $topic->user_id != $current_user->id AND $current_user->user_type != 'admin' AND $current_user->user_type != 'inspector')) {
			return response()->json([
				'error' => true,
			]);
		}

		$ku_trans = KuTranslation::where('topic_id', $topic->id)->first();
		$ku_title = null;
		$ku_abstract = null;
		if($ku_trans) {
			$ku_title = $ku_trans->topic;
			$ku_abstract = $ku_trans->abstract;
		}
		
		$delete_recomend = '';
		if($topic->user_id === NULL AND $topic->delete_recommended == 0){
			$delete_recomend = '<a href="'. route('translator.delete_recommendation', ['topic_id' => $topic->id]). '" class="deletion-rec btn btn-warning" style="margin-left: 4px;">Recommend for Deletion</a>';
		}
		
		return response()->json([
			'error' => false,
			'translate_url' => route('translator.translate', ['topic_id' => $topic->id]),
			'topic' => $topic->topic,
			'abstract' => nl2br(preg_replace('(\[[0-9]*\])', '', $topic->abstract)),
			'abstract_len' => strlen($topic->abstract),
			'ku_topic' => $ku_title,
			'ku_abstract' => $ku_abstract,
			'delete_recomend' => $delete_recomend,
			'user_type' => $current_user->user_type,
		]);
	}
	
	public function TestCURL($topic)
	{
		if(!$topic)
			return;
		$topic = str_replace(" ", "_", $topic);
		$url = "https://en.wikipedia.org/w/api.php?format=json&action=parse&page={$topic}&prop=text&section=0";
		$ch = curl_init($url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_USERAGENT, "WIDAT | Wiki Abstract Translation Application");
		$c = curl_exec($ch);

		$json = json_decode($c);
		
		
		try {
			$content = $json->{'parse'}->{'text'}->{'*'};
		}
		catch(\Exception $ex) { return; }

		// pattern for first match of a paragraph
		$pattern = '#<p>(.*)</p>#Us';
		$abstract = "";
		if(preg_match_all($pattern, $content, $matches))
		{
			// print $matches[0]; // content of the first paragraph (including wrapping <p> tag)
			//$abstract = strip_tags($matches[1]); // Content of the first paragraph without the HTML tags.
			foreach($matches[1] as $p) {
				$abstract .= strip_tags($p) . "\n";
			}
		}
		
		echo $abstract;
	}
}