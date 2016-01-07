<?php
/*
Plugin Name: LiveJournal XML Importer
Plugin URI: https://bitbucket.org/VirtualWolf/livejournal-xml-importer
Description: Import posts and comments from XML files created with ljdump (https://github.com/ghewgill/ljdump).
Author: VirtualWolf
Author URI: https://virtualwolf.org
Version: 0.1
Stable tag: 0.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

add_action( 'wp_ajax_livejournal_xml_importer', 'livejournal_import_xml_ajax_handler' );

function livejournal_import_xml_ajax_handler() {
	global $lj_xml_import;
	check_ajax_referer( 'lj-xml-import' );
	if ( !current_user_can( 'publish_posts' ) )
		die('-1');
	if ( empty( $_POST['step'] ) )
		die( '-1' );
	define('WP_IMPORTING', true);
	$result = $lj_xml_import->{ 'step' . ( (int) $_POST['step'] ) }();
	if ( is_wp_error( $result ) )
		echo $result->get_error_message();
	die;
}

if ( !defined('WP_LOAD_IMPORTERS') && !defined( 'DOING_AJAX' ) )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

/**
 * LiveJournal XML Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class LJ_XML_Import extends WP_Importer {

	var $username;
	var $comment_meta;
	var $comments;

	// This list taken from LJ, they don't appear to have an API for it
	var $moods = array( '1' => 'aggravated',
						'10' => 'discontent',
						'100' => 'rushed',
						'101' => 'contemplative',
						'102' => 'nerdy',
						'103' => 'geeky',
						'104' => 'cynical',
						'105' => 'quixotic',
						'106' => 'crazy',
						'107' => 'creative',
						'108' => 'artistic',
						'109' => 'pleased',
						'11' => 'energetic',
						'110' => 'bitchy',
						'111' => 'guilty',
						'112' => 'irritated',
						'113' => 'blank',
						'114' => 'apathetic',
						'115' => 'dorky',
						'116' => 'impressed',
						'117' => 'naughty',
						'118' => 'predatory',
						'119' => 'dirty',
						'12' => 'enraged',
						'120' => 'giddy',
						'121' => 'surprised',
						'122' => 'shocked',
						'123' => 'rejected',
						'124' => 'numb',
						'125' => 'cheerful',
						'126' => 'good',
						'127' => 'distressed',
						'128' => 'intimidated',
						'129' => 'crushed',
						'13' => 'enthralled',
						'130' => 'devious',
						'131' => 'thankful',
						'132' => 'grateful',
						'133' => 'jealous',
						'134' => 'nervous',
						'14' => 'exhausted',
						'15' => 'happy',
						'16' => 'high',
						'17' => 'horny',
						'18' => 'hungry',
						'19' => 'infuriated',
						'2' => 'angry',
						'20' => 'irate',
						'21' => 'jubilant',
						'22' => 'lonely',
						'23' => 'moody',
						'24' => 'pissed off',
						'25' => 'sad',
						'26' => 'satisfied',
						'27' => 'sore',
						'28' => 'stressed',
						'29' => 'thirsty',
						'3' => 'annoyed',
						'30' => 'thoughtful',
						'31' => 'tired',
						'32' => 'touched',
						'33' => 'lazy',
						'34' => 'drunk',
						'35' => 'ditzy',
						'36' => 'mischievous',
						'37' => 'morose',
						'38' => 'gloomy',
						'39' => 'melancholy',
						'4' => 'anxious',
						'40' => 'drained',
						'41' => 'excited',
						'42' => 'relieved',
						'43' => 'hopeful',
						'44' => 'amused',
						'45' => 'determined',
						'46' => 'scared',
						'47' => 'frustrated',
						'48' => 'indescribable',
						'49' => 'sleepy',
						'5' => 'bored',
						'51' => 'groggy',
						'52' => 'hyper',
						'53' => 'relaxed',
						'54' => 'restless',
						'55' => 'disappointed',
						'56' => 'curious',
						'57' => 'mellow',
						'58' => 'peaceful',
						'59' => 'bouncy',
						'6' => 'confused',
						'60' => 'nostalgic',
						'61' => 'okay',
						'62' => 'rejuvenated',
						'63' => 'complacent',
						'64' => 'content',
						'65' => 'indifferent',
						'66' => 'silly',
						'67' => 'flirty',
						'68' => 'calm',
						'69' => 'refreshed',
						'7' => 'crappy',
						'70' => 'optimistic',
						'71' => 'pessimistic',
						'72' => 'giggly',
						'73' => 'pensive',
						'74' => 'uncomfortable',
						'75' => 'lethargic',
						'76' => 'listless',
						'77' => 'recumbent',
						'78' => 'exanimate',
						'79' => 'embarrassed',
						'8' => 'cranky',
						'80' => 'envious',
						'81' => 'sympathetic',
						'82' => 'sick',
						'83' => 'hot',
						'84' => 'cold',
						'85' => 'worried',
						'86' => 'loved',
						'87' => 'awake',
						'88' => 'working',
						'89' => 'productive',
						'9' => 'depressed',
						'90' => 'accomplished',
						'91' => 'busy',
						'92' => 'blah',
						'93' => 'full',
						'95' => 'grumpy',
						'96' => 'weird',
						'97' => 'nauseated',
						'98' => 'ecstatic',
						'99' => 'chipper' );

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Import LiveJournal XML' , 'livejournal-xml-importer') . '</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		?>
		<div class="narrow">
		<form action="admin.php?import=livejournal-xml" method="post">
		<?php wp_nonce_field( 'lj-xml-import' ) ?>
		<?php if ( get_option( 'ljapi_username' ) && get_option( 'ljapi_password' ) ) : ?>
			<input type="hidden" name="step" value="<?php echo esc_attr( get_option( 'ljapi_step' ) ) ?>" />
			<p><?php _e( 'It looks like you attempted to import your LiveJournal posts previously and got interrupted.' , 'livejournal-xml-importer') ?></p>
			<p class="submit">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Continue previous import' , 'livejournal-xml-importer') ?>" />
			</p>
			<p class="submitbox"><a href="<?php echo esc_url($_SERVER['PHP_SELF'] . '?import=livejournal-xml&amp;step=-1&amp;_wpnonce=' . wp_create_nonce( 'lj-xml-import' ) . '&amp;_wp_http_referer=' . esc_attr( $_SERVER['REQUEST_URI'] )) ?>" class="deletion submitdelete"><?php _e( 'Cancel &amp; start a new import' , 'livejournal-xml-importer') ?></a></p>
			<p>
		<?php else : ?>
			<input type="hidden" name="step" value="1" />
			<input type="hidden" name="login" value="true" />
			<p><?php _e( 'Howdy! This importer allows you to import all your LiveJournal entries and comments directly from the output of <a href="http://hewgill.com/ljdump/">ljdump</a>.' , 'livejournal-xml-importer') ?></p>
			<p><?php _e( 'Enter your LiveJournal username (so we can properly attribute your comments and posts to you) and the location on the filesystem of the ljdump XML files:' , 'livejournal-xml-importer') ?></p>

			<table class="form-table">

			<tr>
			<th scope="row"><label for="lj_username"><?php _e( 'LiveJournal Username' , 'livejournal-xml-importer') ?></label></th>
			<td><input type="text" name="lj_username" id="lj_username" class="regular-text" /></td>
			</tr>
            
			<tr>
			<th scope="row"><label for="lj_posts_location"><?php _e( 'Location of XML files' , 'livejournal-xml-importer') ?></label></th>
			<td><input type="text" name="lj_posts_location" id="lj_posts_location" class="regular-text" /></td>
			</tr>

			</table>

			<p><?php _e( 'If you have any entries on LiveJournal which are marked as private, they will be password-protected when they are imported so that only people who know the password can see them.' , 'livejournal-xml-importer') ?></p>
			<p><?php _e( 'If you don&#8217;t enter a password, ALL ENTRIES from your LiveJournal will be imported as public posts in WordPress.' , 'livejournal-xml-importer') ?></p>
			<p><?php _e( 'Enter the password you would like to use for all protected entries here:' , 'livejournal-xml-importer') ?></p>
			<table class="form-table">

			<tr>
			<th scope="row"><label for="protected_password"><?php _e( 'Protected Post Password' , 'livejournal-xml-importer') ?></label></th>
			<td><input type="text" name="protected_password" id="protected_password" class="regular-text" /></td>
			</tr>

			</table>

			<p class="submit">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Start Importing' , 'livejournal-xml-importer') ?>" />
			</p>

			<noscript>
				<p><?php _e( '<strong>NOTE:</strong> You appear to have JavaScript disabled, so you will need to manually click through each step of this importer. If you enable JavaScript, it will step through automatically.' , 'livejournal-xml-importer') ?></p>
			</noscript>
		<?php endif; ?>
		</form>
		</div>
		<?php
	}

	function download_post_bodies() {
		
		foreach (glob(get_option('lj_posts_location') . '/L-*.xml') as $post) {
			$event = json_decode(json_encode((array)simplexml_load_string(
				file_get_contents($post)
			)),1);
			
			$inserted = $this->import_post( $event );
			if ( is_wp_error( $inserted ) )
				return $inserted;
			
			wp_cache_flush();
		}
		
		echo '<ol>';

		echo '</ol>';
	}

	function _normalize_tag( $matches ) {
		return '<' . strtolower( $matches[1] );
	}

	function import_post( $post ) {
		global $wpdb;

		// Make sure we haven't already imported this one
		if ( $this->get_wp_post_ID( $post['itemid'] ) )
			return;

		$user = wp_get_current_user();
		$post_author      = $user->ID;
		$post['security'] = !empty( $post['security'] ) ? $post['security'] : '';
		$post_status      = ( 'private' == trim( $post['security'] ) ) ? 'private' : 'publish'; // Only me
		$post_password    = ( 'usemask' == trim( $post['security'] ) ) ? $this->protected_password : ''; // "Friends" via password

		// For some reason, LJ sometimes sends a date as "2004-04-1408:38:00" (no space btwn date/time)
		$post_date = $post['eventtime'];
		if ( 18 == strlen( $post_date ) )
			$post_date = substr( $post_date, 0, 10 ) . ' ' . substr( $post_date, 10 );

		// Cleaning up and linking the title
		$post_title = isset( $post['subject'] ) ? trim( $post['subject'] ) : '';
		$post_title = $this->translate_lj_user( $post_title ); // Translate it, but then we'll strip the link
		$post_title = strip_tags( $post_title ); // Can't have tags in the title in WP

		// Clean up content
		$post_content = $post['event'];
		$post_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content );
		// XHTMLize some tags
		$post_content = str_replace( '<br>', '<br />', $post_content );
		$post_content = str_replace( '<hr>', '<hr />', $post_content );
		// lj-cut ==>  <!--more-->
		$post_content = preg_replace( '|<lj-cut text="([^"]*)">|is', '<!--more $1-->', $post_content );
		$post_content = str_replace( array( '<lj-cut>', '</lj-cut>' ), array( '<!--more-->', '' ), $post_content );
		$first = strpos( $post_content, '<!--more' );
		$post_content = substr( $post_content, 0, $first + 1 ) . preg_replace( '|<!--more(.*)?-->|sUi', '', substr( $post_content, $first + 1 ) );
		// lj-user ==>  a href
		$post_content = $this->translate_lj_user( $post_content );
		//$post_content = force_balance_tags( $post_content );

		// Handle any tags associated with the post
		$tags_input = !empty( $post['props']['taglist'] ) ? $post['props']['taglist'] : '';

		// Check if comments are closed on this post
		$comment_status = !empty( $post['props']['opt_nocomments'] ) ? 'closed' : 'open';

		echo '<li>';
		if ( $post_id = post_exists( $post_title, $post_content, $post_date ) ) {
			printf( __( 'Post <strong>%s</strong> already exists.' , 'livejournal-xml-importer'), stripslashes( $post_title ) );
		} else {
			printf( __( 'Imported post <strong>%s</strong>...' , 'livejournal-xml-importer'), stripslashes( $post_title ) );
			$postdata = compact( 'post_author', 'post_date', 'post_content', 'post_title', 'post_status', 'post_password', 'tags_input', 'comment_status' );
			$post_id = wp_insert_post( $postdata, true );
			if ( is_wp_error( $post_id ) ) {
				if ( 'empty_content' == $post_id->get_error_code() )
					return; // Silent skip on "empty" posts
				return $post_id;
			}
			if ( !$post_id ) {
				_e( 'Couldn&#8217;t get post ID (creating post failed!)' , 'livejournal-xml-importer');
				echo '</li>';
				return new WP_Error( 'insert_post_failed', __( 'Failed to create post.' , 'livejournal-xml-importer') );
			}

			// Handle all the metadata for this post
			$this->insert_postmeta( $post_id, $post );
		}
		echo '</li>';
	}

	// Convert lj-user tags to links to that user
	function translate_lj_user( $str ) {
		return preg_replace( '|<lj\s+user\s*=\s*["\']([\w-]+)["\']>|', '<a href="http://$1.livejournal.com/" class="lj-user">$1</a>', $str );
	}

	function insert_postmeta( $post_id, $post ) {
		// Need the original LJ id for comments
		add_post_meta( $post_id, 'lj_itemid', $post['itemid'] );

		// And save the permalink on LJ in case we want to link back or something
		add_post_meta( $post_id, 'lj_permalink', $post['url'] );

		// Supports the following "props" from LJ, saved as lj_<prop_name> in wp_postmeta
		// 		Adult Content - adult_content
		// 		Location - current_coords + current_location
		// 		Mood - current_mood (translated from current_moodid)
		// 		Music - current_music
		// 		Userpic - picture_keyword
		foreach ( array( 'adult_content', 'current_coords', 'current_location', 'current_moodid', 'current_music', 'picture_keyword' ) as $prop ) {
			if ( !empty( $post['props'][$prop] ) ) {
				if ( 'current_moodid' == $prop ) {
					$prop = 'current_mood';
					$val = $this->moods[ $post['props']['current_moodid'] ];
				} else {
					$val = $post['props'][$prop];
				}
				add_post_meta( $post_id, 'lj_' . $prop, $val );
			}
		}
	}

	// Downloads actual comment bodies from LJ
	// Inserts them all directly to the DB, with additional info stored in "spare" fields
	function download_comment_bodies() {
		global $wpdb;
        
        foreach (glob(get_option('lj_posts_location') . '/C-*.xml') as $post_comments) {
            $comments = json_decode(json_encode((array)simplexml_load_string(
                file_get_contents($post_comments)
            )),1);
            
            // Get the LiveJournal post ID from the comments filename
            $lj_post_id = preg_replace('/[^0-9]/', '', basename($post_comments, '.xml'));
            
            if ( !is_array( $comments['comment'][0] ) ) {
                if ( $comments['comment']['state'] != 'D' ) {
                    $comments['comment']['lj_post_id'] = $lj_post_id;
    				$comments['comment'] = $this->parse_comment( $comments['comment'] );
    				$comments['comment'] = wp_filter_comment( $comments['comment'] );
    				$id = wp_insert_comment( $comments['comment'] );
                }
            }
            else {
                foreach($comments['comment'] as $comment) {
                    $comment['lj_post_id'] = $lj_post_id;
                    
    				$comment = $this->parse_comment( $comment );
    				$comment = wp_filter_comment( $comment );
    				$id = wp_insert_comment( $comment );
                }
            }
        }
 

		// Counter just used to show progress to user
		update_option( 'ljapi_comment_batch', ( (int) get_option( 'ljapi_comment_batch' ) + 1 ) );
		return true;
	}

	// Takes a block of XML and parses out all the elements of the comment
	function parse_comment( $comment ) {
		global $wpdb;

		$lj_comment_ID = $comment['id'];
		$lj_comment_post_ID = $comment['lj_post_id'];
		$author = $comment['user'];
		$lj_comment_parent = $comment['parentid'];
		//preg_match( '| state=\'([SDFA])\'|i', $attribs[1], $matches ); // optional
		//$lj_comment_state = isset( $matches[1] ) ? $matches[1] : 'A';

		// Clean up "subject" - this will become the first line of the comment in WP
		if ( isset( $comment['subject'] ) ) {
			$comment_subject =  trim( $comment['subject'] );
			if ( 'Re:' == $comment_subject )
				$comment_subject = '';
		}

		// Get the body and HTMLize it
		$comment_content = $comment['body'];
		//$comment_content = !empty( $comment_subject ) ? $comment_subject . "\n\n" . $matches[1] : $matches[1];
		$comment_content = @html_entity_decode( $comment_content, ENT_COMPAT, get_option('blog_charset') );
		$comment_content = str_replace( '&apos;', "'", $comment_content );
		$comment_content = wpautop( $comment_content, 1 );
		$comment_content = str_replace( '<br>', '<br />', $comment_content );
		$comment_content = str_replace( '<hr>', '<hr />', $comment_content );
        $comment_content = $this->translate_lj_user( $comment_content );
		$comment_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $comment_content );
		$comment_content = trim( $comment_content );

		// Get and convert the date
		$comment_date = trim( str_replace( array( 'T', 'Z' ), ' ', $comment['date'] ) );

		// Grab IP if available
        // preg_match( '|<property name=\'poster_ip\'>(.*)</property>|i', $comment, $matches ); // optional
        // $comment_author_IP = isset( $matches[1] ) ? $matches[1] : '';

		// Try to get something useful for the comment author, especially if it was "my" comment
		if ( get_option( 'ljapi_username' ) == $author ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
			$author  = $user->display_name;
			$url     = trailingslashit( get_option( 'home' ) );
		} else {
			$user_id = 0;
			$url     = ( __( 'Anonymous' , 'livejournal-xml-importer') == $author ) ? '' : 'http://' . $author . '.livejournal.com/';
		}

		// Send back the array of details
		return array( 'lj_comment_ID' => $lj_comment_ID,
						'lj_comment_post_ID' => $lj_comment_post_ID,
						'lj_comment_parent' => ( !empty( $lj_comment_parent ) ? $lj_comment_parent : 0 ),
						//'lj_comment_state' => $lj_comment_state,
						'comment_post_ID' => $this->get_wp_post_ID( $lj_comment_post_ID ),
						'comment_author' => $author,
						'comment_author_url' => $url,
						'comment_author_email' => '',
						'comment_content' => $comment_content,
						'comment_date' => $comment_date,
						'comment_author_IP' => ( !empty( $comment_author_IP ) ? $comment_author_IP : '' ),
						'comment_approved' => 1,
						'comment_karma' => $lj_comment_ID, // Need this and next value until rethreading is done
						'comment_agent' => ( !empty( $lj_comment_parent ) ? $lj_comment_parent : 0 ),
						'comment_type' => 'livejournal',  // Custom type, so we can find it later for processing
						'user_ID' => $user_id
					);
	}


	// Gets the post_ID that a LJ post has been saved as within WP
	function get_wp_post_ID( $post ) {
		global $wpdb;

		if ( empty( $this->postmap[$post] ) )
		 	$this->postmap[$post] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'lj_itemid' AND meta_value = %d", $post ) );

		return $this->postmap[$post];
	}

	// Gets the comment_ID that a LJ comment has been saved as within WP
	function get_wp_comment_ID( $comment ) {
		global $wpdb;
		if ( empty( $this->commentmap[$comment] ) )
		 	$this->commentmap[$comment] = $wpdb->get_var( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_karma = %d", $comment ) );
		return $this->commentmap[$comment];
	}

	function dispatch() {
		if ( empty( $_REQUEST['step'] ) )
			$step = 0;
		else
			$step = (int) $_REQUEST['step'];

		$this->header();

		switch ( $step ) {
			case -1 :
				$this->cleanup();
				// Intentional no break
			case 0 :
				$this->greet();
				break;
			case 1 :
			case 2 :
			case 3 :
				check_admin_referer( 'lj-xml-import' );
				$result = $this->{ 'step' . $step }();
				if ( is_wp_error( $result ) ) {
					$this->throw_error( $result, $step );
				}
				break;
		}

		$this->footer();
	}

	// Technically the first half of step 1, this is separated to allow for AJAX
	// calls. Sets up some variables and options and confirms authentication.
	function setup() {
		global $verified;
		// Get details from form or from DB
		if ( !empty( $_POST['lj_username'] ) && !empty( $_POST['lj_posts_location'] ) ) {
			// Store details for later
			$this->username = $_POST['lj_username'];
            $this->posts_location = $_POST['lj_posts_location'];
			update_option( 'ljapi_username', $this->username );
            update_option( 'lj_posts_location', $this->posts_location );
		} else {
			$this->username = get_option( 'ljapi_username' );
            $this->posts_location = get_option( 'lj_posts_location' );
		}

		// This is the password to set on protected posts
		if ( !empty( $_POST['protected_password'] ) ) {
			$this->protected_password = $_POST['protected_password'];
			update_option( 'ljapi_protected_password', $this->protected_password );
		} else {
			$this->protected_password = get_option( 'ljapi_protected_password' );
		}

		// Log in to confirm the details are correct
		if ( empty( $this->username ) || empty( $this->posts_location ) ) {
			?>
			<p><?php _e( 'Please enter your LiveJournal username so we can identify which posts and comments are yours, and select the location of the XML files to import.' , 'livejournal-xml-importer') ?></p>
			<p><a href="<?php echo esc_url($_SERVER['PHP_SELF'] . '?import=livejournal-xml&amp;step=-1&amp;_wpnonce=' . wp_create_nonce( 'lj-xml-import' ) . '&amp;_wp_http_referer=' . esc_attr( str_replace( '&step=1', '', $_SERVER['REQUEST_URI'] ) ) ) ?>"><?php _e( 'Start again' , 'livejournal-xml-importer') ?></a></p>
			<?php
			return false;
		}

		return true;
	}

	// Check form inputs and start importing posts
	function step1() {
		global $verified;

		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'ljapi_step', 1 );
		
		if ( empty( $_POST['login'] ) ) {
			// We're looping -- load some details from DB
			$this->username = get_option( 'ljapi_username' );
            $this->username = get_option( 'lj_posts_location' );
			$this->protected_password = get_option( 'ljapi_protected_password' );
		} else {
			// First run (non-AJAX)
			$setup = $this->setup();
			if ( !$setup ) {
				return false;
			} else if ( is_wp_error( $setup ) ) {
				$this->throw_error( $setup, 1 );
				return false;
			}
		}

		echo '<div id="ljapi-status">';
		echo '<h3>' . __( 'Importing Posts' , 'livejournal-xml-importer') . '</h3>';
		echo '<p>' . __( 'We&#8217;re downloading and importing your LiveJournal posts...' , 'livejournal-xml-importer') . '</p>';
		if ( get_option( 'ljapi_post_batch' ) && count( get_option( 'ljapi_sync_item_times' ) ) ) {
			$batch = count( get_option( 'ljapi_sync_item_times' ) );
			$batch = $batch > 300 ? ceil( $batch / 300 ) : 1;
			echo '<p><strong>' . sprintf( __( 'Imported post batch %d of <strong>approximately</strong> %d' , 'livejournal-xml-importer'), ( get_option( 'ljapi_post_batch' ) + 1 ), $batch ) . '</strong></p>';
		}
		ob_flush(); flush();

		// Download a batch of actual posts
		$result = $this->download_post_bodies();
		if ( is_wp_error( $result ) ) {
    		$this->throw_error( $result, 1 );
    		return false;
		}

		if ( get_option( 'ljapi_last_sync_count' ) > 0 ) {
		?>
			<form action="admin.php?import=livejournal-xml" method="post" id="ljapi-auto-repost">
			<?php wp_nonce_field( 'lj-xml-import' ) ?>
			<input type="hidden" name="step" id="step" value="1" />
			<p><input type="submit" class="button" value="<?php esc_attr_e( 'Import the next batch' , 'livejournal-xml-importer') ?>" /> <span id="auto-message"></span></p>
			</form>
			<?php $this->auto_ajax( 'ljapi-auto-repost', 'auto-message' ); ?>
		<?php
		} else {
			echo '<p>' . __( 'Your posts have all been imported, but wait &#8211; there&#8217;s more! Now we need to download &amp; import your comments.' , 'livejournal-xml-importer') . '</p>';
			echo $this->next_step( 2, __( 'Download my comments &raquo;' , 'livejournal-xml-importer') );
			$this->auto_submit();
		}
		echo '</div>';
	}

	// Download comments to local XML
	function step2() {
		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'ljapi_step', 2 );
		$this->username = get_option( 'ljapi_username' );


		echo '<div id="ljapi-status">';
		echo '<h3>' . __( 'Downloading Comments&#8230;' , 'livejournal-xml-importer') . '</h3>';
		echo '<p>' . __( 'Now we will download your comments so we can import them (this could take a <strong>long</strong> time if you have lots of comments)...' , 'livejournal-xml-importer') . '</p>';
		ob_flush(); flush();

		// Download a batch of actual comments
		$result = $this->download_comment_bodies();
		if ( is_wp_error( $result ) ) {
			$this->throw_error( $result, 2 );
			return false;
		}


		echo '<p>' . __( 'Your comments have all been imported now, but we still need to rebuild your conversation threads.' , 'livejournal-xml-importer') . '</p>';
		echo $this->next_step( 3, __( 'Rebuild my comment threads &raquo;' , 'livejournal-xml-importer') );
		$this->auto_submit();
		echo '</div>';
	}

	// Re-thread comments already in the DB
	function step3() {
		global $wpdb;

		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'ljapi_step', 3 );

		echo '<div id="ljapi-status">';
		echo '<h3>' . __( 'Threading Comments&#8230;' , 'livejournal-xml-importer') . '</h3>';
		echo '<p>' . __( 'We are now re-building the threading of your comments (this can also take a while if you have lots of comments)...' , 'livejournal-xml-importer') . '</p>';
		flush();

		// Only bother adding indexes if they have over 5000 comments (arbitrary number)
		$imported_comments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'livejournal'" );
		$added_indices = false;
		if ( 5000 < $imported_comments ) {
			include_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$added_indices = true;
			add_clean_index( $wpdb->comments, 'comment_type'  );
			add_clean_index( $wpdb->comments, 'comment_karma' );
			add_clean_index( $wpdb->comments, 'comment_agent' );
		}

		// Get LJ comments, which haven't been threaded yet, 5000 at a time and thread them
		while ( $comments = $wpdb->get_results( "SELECT comment_ID, comment_agent FROM {$wpdb->comments} WHERE comment_type = 'livejournal' AND comment_agent != '0' LIMIT 5000", OBJECT ) ) {
			foreach ( $comments as $comment ) {
				$wpdb->update( $wpdb->comments,
								array( 'comment_parent' => $this->get_wp_comment_ID( $comment->comment_agent ), 'comment_type' => 'livejournal-done' ),
								array( 'comment_ID' => $comment->comment_ID ) );
			}
			wp_cache_flush();
			$wpdb->flush();
		}

		// Revert the comments table back to normal and optimize it to reclaim space
		if ( $added_indices ) {
			drop_index( $wpdb->comments, 'comment_type'  );
			drop_index( $wpdb->comments, 'comment_karma' );
			drop_index( $wpdb->comments, 'comment_agent' );
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->comments}" );
		}

		// Clean up database and we're out
		$this->cleanup();
		do_action( 'import_done', 'livejournal' );
		if ( $imported_comments > 1 )
			echo '<p>' . sprintf( __( "Successfully re-threaded %s comments." , 'livejournal-xml-importer'), number_format( $imported_comments ) ) . '</p>';
		echo '<h3>';
		printf( __( 'All done. <a href="%s">Have fun!</a>' , 'livejournal-xml-importer'), get_option( 'home' ) );
		echo '</h3>';
		echo '</div>';
	}

	// Output an error message with a button to try again.
	function throw_error( $error, $step ) {
		echo '<p><strong>' . $error->get_error_message() . '</strong></p>';
		echo $this->next_step( $step, __( 'Try Again' , 'livejournal-xml-importer') );
	}

	// Returns the HTML for a link to the next page
	function next_step( $next_step, $label, $id = 'ljapi-next-form' ) {
		$str  = '<form action="admin.php?import=livejournal-xml" method="post" id="' . $id . '">';
		$str .= wp_nonce_field( 'lj-xml-import', '_wpnonce', true, false );
		$str .= wp_referer_field( false );
		$str .= '<input type="hidden" name="step" id="step" value="' . esc_attr($next_step) . '" />';
		$str .= '<p><input type="submit" class="button" value="' . esc_attr( $label ) . '" /> <span id="auto-message"></span></p>';
		$str .= '</form>';

		return $str;
	}

	// Automatically submit the specified form after $seconds
	// Include a friendly countdown in the element with id=$msg
	function auto_submit( $id = 'ljapi-next-form', $msg = 'auto-message', $seconds = 10 ) {
		?><script type="text/javascript">
			next_counter = <?php echo $seconds ?>;
			jQuery(document).ready(function(){
				ljapi_msg();
			});

			function ljapi_msg() {
				str = '<?php echo esc_js( _e( "Continuing in %d&#8230;" , 'livejournal-xml-importer') ); ?>';
				jQuery( '#<?php echo $msg ?>' ).html( str.replace( /%d/, next_counter ) );
				if ( next_counter <= 0 ) {
					if ( jQuery( '#<?php echo $id ?>' ).length ) {
						jQuery( "#<?php echo $id ?> input[type='submit']" ).hide();
						str = '<?php echo esc_js( __( "Continuing&#8230;" , 'livejournal-xml-importer') ); ?> <img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" id="processing" align="top" />';
						jQuery( '#<?php echo $msg ?>' ).html( str );
						jQuery( '#<?php echo $id ?>' ).submit();
						return;
					}
				}
				next_counter = next_counter - 1;
				setTimeout('ljapi_msg()', 1000);
			}
		</script><?php
	}

	// Automatically submit the form with #id to continue the process
	// Hide any submit buttons to avoid people clicking them
	// Display a countdown in the element indicated by $msg for "Continuing in x"
	function auto_ajax( $id = 'ljapi-next-form', $msg = 'auto-message', $seconds = 5 ) {
		?><script type="text/javascript">
			next_counter = <?php echo $seconds ?>;
			jQuery(document).ready(function(){
				ljapi_msg();
			});

			function ljapi_msg() {
				str = '<?php echo esc_js( __( "Continuing in %d&#8230;" , 'livejournal-xml-importer') ); ?>';
				jQuery( '#<?php echo $msg ?>' ).html( str.replace( /%d/, next_counter ) );
				if ( next_counter <= 0 ) {
					if ( jQuery( '#<?php echo $id ?>' ).length ) {
						jQuery( "#<?php echo $id ?> input[type='submit']" ).hide();
						jQuery.ajaxSetup({'timeout':3600000});
						str = '<?php echo esc_js( __( "Processing next batch." , 'livejournal-xml-importer') ); ?> <img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" id="processing" align="top" />';
						jQuery( '#<?php echo $msg ?>' ).html( str );
						jQuery('#ljapi-status').load(ajaxurl, {'action':'livejournal_importer',
																'import':'livejournal',
																'step':jQuery('#step').val(),
																'_wpnonce':'<?php echo wp_create_nonce( 'lj-xml-import' ) ?>',
																'_wp_http_referer':'<?php echo $_SERVER['REQUEST_URI'] ?>'});
						return;
					}
				}
				next_counter = next_counter - 1;
				setTimeout('ljapi_msg()', 1000);
			}
		</script><?php
	}

	// Remove all options used during import process and
	// set wp_comments entries back to "normal" values
	function cleanup() {
		global $wpdb;

		delete_option( 'ljapi_username' );
		delete_option( 'ljapi_password' );
        delete_option( 'ljapi_posts_location' );
		delete_option( 'ljapi_protected_password' );
		delete_option( 'ljapi_verified' );
		delete_option( 'ljapi_total' );
		delete_option( 'ljapi_count' );
		delete_option( 'ljapi_lastsync' );
		delete_option( 'ljapi_last_sync_count' );
		delete_option( 'ljapi_sync_item_times' );
		delete_option( 'ljapi_lastsync_posts' );
		delete_option( 'ljapi_post_batch' );
		delete_option( 'ljapi_imported_count' );
		delete_option( 'ljapi_maxid' );
		delete_option( 'ljapi_usermap' );
		delete_option( 'ljapi_highest_id' );
		delete_option( 'ljapi_highest_comment_id' );
		delete_option( 'ljapi_comment_batch' );
		delete_option( 'ljapi_step' );

		$wpdb->update( $wpdb->comments,
						array( 'comment_karma' => 0, 'comment_agent' => 'WP LJ Importer', 'comment_type' => '' ),
						array( 'comment_type' => 'livejournal-done' ) );
		$wpdb->update( $wpdb->comments,
						array( 'comment_karma' => 0, 'comment_agent' => 'WP LJ Importer', 'comment_type' => '' ),
						array( 'comment_type' => 'livejournal' ) );

		do_action( 'import_end' );
	}
}

} // class_exists( 'WP_Importer' )

function livejournal_xml_importer_init() {
	global $lj_xml_import;

	load_plugin_textdomain( 'livejournal-xml-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	$lj_xml_import = new LJ_XML_Import();

	register_importer( 'livejournal-xml', __( 'LiveJournal XML' , 'livejournal-xml-importer'), __( 'Import posts from LiveJournal XML files generated using ljdump.' , 'livejournal-xml-importer'), array( $lj_xml_import, 'dispatch' ) );
}
add_action( 'init', 'livejournal_xml_importer_init' );
