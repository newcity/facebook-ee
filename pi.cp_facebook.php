<?php

	if ( ! defined('BASEPATH')) exit('No direct script access allowed');
	
	$plugin_info = array(
		'pi_name' => 'CP Facebook',
		'pi_version' => '1.0',
		'pi_author' => 'Caleb Pierce',
		'pi_author_url' => 'http://insidenewcity.com/team/caleb',
		'pi_description' => 'Uses the Facebook SDK to pull a wall feed in JSON format.',
		'pi_usage' => Cp_facebook::usage()
	);

	class Cp_facebook{
		
		
		/*
			Constructor
		*/
		function Cp_facebook(){
			
			$this->EE =& get_instance();
			
		} //constructor
		

		// ----------------------------------------
		//  Get the JSON feed of a Facebook wall
		// ----------------------------------------
		// Returns a JSON feed of the user's wall
		public function get_wall_feed() {
			
			// require the PHP SDK
			require_once 'facebook.php';
			
			// prepare the return variable
			$this->return_data = new stdClass;
			
			//prepare the "posts" component of the return variable. This will contain the actual wall posts from Facebook
			$this->return_data->posts = array();
			
			// get params, return false on error
			if ( ! $this->_get_params())
				return json_encode($this->return_data);

			// get the Facebook feed, return false on error
			if ( ! $this->_get_feed())
				return json_encode($this->return_data);
			
			//wall feed loop counter
			$count = 0;
			
			//reduce max_posts to the maximum available posts, if necessary
			$this->max_posts = min($this->max_posts, count($this->wall_feed->data));
						
			//loop through each post. NOTE: Facebook sorts by date, descending. First post is the most recent.
			foreach ($this->wall_feed->data as $i){
				
				//exit once max posts to output have been reached
				if ($count >= $this->max_posts)
					break;
				
				// Prepare the timestamps as a human-readable format
				$i->created_time = date('g:i A M j', strtotime($i->created_time));
				$i->updated_time = date('g:i A M j', strtotime($i->updated_time));
								
				//add the current post to the return variable if this post is actually from the user (posts can also be fans posting a comment)
				if ($i->from->id == $this->user_id && ! in_array($i->type, $this->exclude_post_types)){
					$this->return_data->posts[$count] = $i;
					$count++;
				} //if

			} //foreach
			
			$json = json_encode($this->return_data->posts);
			
			//wrap the json in a callback method
			if ($this->jsonp_callback) {
				die($this->jsonp_callback.'('.$json.');');
			}
			
			die($json);
			
		} //fn


		/*
			Gets the plugin params, returns false if not all required params are found
		*/
		private function _get_params() {
			
			// get the required params
			$this->app_id = $this->EE->TMPL->fetch_param('app_id');
			$this->secret = $this->EE->TMPL->fetch_param('secret');
			$this->user_id = $this->EE->TMPL->fetch_param('user_id');
			
			// get the optional params
			$temp_types = explode(',', $this->EE->TMPL->fetch_param('exclude_post_types'));
			$this->exclude_post_types = $temp_types;
			
			$this->jsonp_callback = $this->EE->TMPL->fetch_param('jsonp_callback');
			
			$this->max_posts = ($this->EE->TMPL->fetch_param('limit') ? $this->EE->TMPL->fetch_param('limit') : 1); // limit posts to 1 or "limit" param

			// check for the two required params :: appId and secret
			if ( ! $this->app_id) {
				$this->return_data->status = 'error';
				$this->return_data->message = 'Required parameter app_id is missing.';
			}
			if ( ! $this->secret) {
				$this->return_data->status = 'error';
				$this->return_data->message = 'Required parameter secret is missing.';
			}
			if ( ! $this->user_id) {
				$this->return_data->status = 'error';
				$this->return_data->message = 'Required parameter user_id is missing.';
			}

			
			return $this->app_id && $this->secret && $this->user_id;
			
		} //fn
		
		
		/*
			Instantiates the Facebook object and attempts to pull and parse the JSON feed
		*/
		private function _get_feed() {
			
			// create the Facebook instance
			$facebook = new Facebook(array(
			    'appId'  => $this->app_id, //'208023985905910', //FACEBOOK_APP_ID
			    'secret' => $this->secret, //'e5085df51c9899b30486e8c947e296d9', //FACEBOOK_APP_SECRET
			));

			try { // request JSON feed and attempt to parse result
				
				$raw_feed = file_get_contents('https://graph.facebook.com/' . $this->user_id . '/feed?access_token=' . $facebook->getAccessToken());
				$this->wall_feed = json_decode($raw_feed);
				
				$success = true;
				$this->return_data->status = 'success';
				$this->return_data->message = 'Success. Received valid JSON feed from Facebook.';

			} catch (Exception $e) { // failed to either pull or parse the feed
				
				$this->return_data->status = 'error';
				$this->return_data->message = 'Could not successfully get the Facebook wall feed.';
				$success = false;
				
			} //catch
			
			// no errors and expected wall feed format
			return $success && isset($this->wall_feed->data);
		
		} //fn
		
		// ----------------------------------------
		//  Plugin Usage
		// ----------------------------------------
		// This function describes how the plugin is used.
		function usage(){
			ob_start();
		?>
			
			To get a wall feed, call the get_wall_feed function, passing in the following parameters:
			
				(required) app_id: The Facebook application ID (provided by Facebook)
				
				(required) secret: The Facebook application secret (provided by Facebook)
				
				(required) user_id: The Facebook ID of the wall whose feed you want to pull
				
				(optional - defaults to 1) limit: The max number of posts to display from the Facebook wall feed
				
				(optional) exclude_post_types: A comma-delimited list of Facebook post types to exclude
				
				(optional) jsonp_callback: a JSONP callback function to wrap the resulting JSON in
			
			Example:
			{exp:cp_facebook:get_wall_feed app_id="fbApplicationId" secret="fbSecret" user_id="fbUserId" limit="5"}
			
		<?php
			$buffer = ob_get_contents();

			ob_end_clean(); 

			return $buffer;
		} // usage		
				
	} //class
		

	/* End of file pi.cp_facebook.php */ 
	/* Location: ./manage/expressionengine/third_party/cp_facebook/pi.cp_facebook.php */

?>