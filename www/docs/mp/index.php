<?php

/* For displaying info about the MP for a postcode or constituency.

	This page accepts either 'm' (a member_id), 'pid' (a person_id),
	'c' (a postcode or constituency), or 'n' (a name).
	
	First, we check to see if a person_id's been submitted.
	If so, we display that MP.
	
	Else, we check to see if a member_id's been submitted.
	If so, we display that MP.
	
	Otherwise, we then check to see if a postcode's been submitted.
	If it's valid we put it in a cookie.
	
	If no postcode, we check to see if a constituency's been submitted.
	
	If neither has been submitted, we see if either the user is logged in 
	and has a postcode set or the user has a cookied postcode from a previous
	search.
		
	If we have a valid constituency after all this, we display its MP.
	
	Either way, we print the forms.

*/

include_once "../../includes/easyparliament/init.php";
include_once INCLUDESPATH."easyparliament/member.php";

// From http://cvs.sourceforge.net/viewcvs.py/publicwhip/publicwhip/website/
include_once INCLUDESPATH."postcode.inc";
include_once INCLUDESPATH . 'technorati.php';

debug_timestamp("after includes");

$errors = array();

// Some legacy URLs use 'p' rather than 'pid' for person id. So we still
// need to detect these.
$pid = get_http_var('pid') != '' ? get_http_var('pid') : get_http_var('p');
$name = strtolower(str_replace(array('_'), array(' '), get_http_var('n')));
$cconstituency = strtolower(str_replace(array('_','.',' and '), array(' ','&amp;',' &amp; '), get_http_var('c'))); # *** postcode functions use global $constituency!!! ***
if ($cconstituency == 'mysociety test constituency') {
	header("Location: stom.html");
	exit;
}

# Special case names
if ($name == 'sion simon') $name = "si&ocirc;n simon";
if ($name == 'lembit opik') $name = "lembit &ouml;pik";
if ($cconstituency == 'ynys mon') $cconstituency = "ynys m&ocirc;n";

// Redirect for MP recent appearanecs
if (get_http_var('recent')) {
	if ($THEUSER->postcode_is_set() && !$pid) {
		$MEMBER = new MEMBER(array('postcode' => $THEUSER->postcode()));
		if ($MEMBER->person_id())
			$pid = $MEMBER->person_id();
	} 
	if ($pid) {
		$URL = new URL('search');
		$URL->insert( array('pid'=>$pid, 'pop'=>1) );
		header('Location: http://' . DOMAIN . $URL->generate('none'));
		exit;
	}
}

/////////////////////////////////////////////////////////
// CHECK SUBMITTED MEMBER (term of office) ID.

if (get_http_var('c4')) $this_page = 'c4_mp';
elseif (get_http_var('c4x')) $this_page = 'c4x_mp';
elseif (get_http_var('peer')) $this_page = 'peer';
else $this_page = 'mp';

if (is_numeric(get_http_var('m'))) {
	// Got a member id, redirect to the canonical MP page, with a person id.
	$MEMBER = new MEMBER(array('member_id' => get_http_var('m')));
	member_redirect($MEMBER);

} elseif (is_numeric($pid)) {

	// Normal, plain, displaying an MP by person ID.
	$MEMBER = new MEMBER(array('person_id' => $pid));
	member_redirect($MEMBER);

/////////////////////////////////////////////////////////
// CHECK SUBMITTED POSTCODE

} elseif (get_http_var('pc') != '') {
	// User has submitted a postcode, so we want to display that. 
	$pc = get_http_var('pc');
	$pc = preg_replace('#[^a-z0-9 ]#i', '', $pc);
	if (validate_postcode($pc)) {
		debug ('MP', "MP lookup by postcode");
		$constituency = strtolower(postcode_to_constituency($pc));
		if ($constituency == "CONNECTION_TIMED_OUT") {
			$errors['pc'] = "Sorry, we couldn't check your postcode right now. Please use the 'All Mps' link above to browse MPs";
		} elseif ($constituency == "") {
			$errors['pc'] = "Sorry, ".htmlentities($pc) ." isn't a known postcode";
			debug ('MP', "Can't display an MP, as submitted postcode didn't match a constituency");
		} else {
			// Redirect to the canonical MP page, with a person id.
			$MEMBER = new MEMBER(array('constituency' => $constituency));
			if ($MEMBER->person_id()) {
				// This will cookie the postcode.
				$THEUSER->set_postcode_cookie($pc);
			}
			member_redirect($MEMBER);
		}
	} else {
		$errors['pc'] = "Sorry, ".htmlentities($pc) ." isn't a valid postcode";
		debug ('MP', "Can't display an MP because the submitted postcode wasn't of a valid form.");
	}

/////////////////////////////////////////////////////////
// DOES THE USER HAVE A POSTCODE ALREADY SET?
// (Either in their logged-in details or in a cookie from a previous search.)

} elseif ($THEUSER->postcode_is_set() && $name == '' && $cconstituency == '') {
	$MEMBER = new MEMBER(array('postcode' => $THEUSER->postcode()));
	member_redirect($MEMBER);
} elseif ($name && $cconstituency) {
	$MEMBER = new MEMBER(array('name'=>$name, 'constituency'=>$cconstituency));
	if (!$MEMBER->canonical) {
		member_redirect($MEMBER);
	}
	if ($MEMBER->the_users_mp) {
		$this_page = 'yourmp';
	}
	debug ('MP', 'Displaying MP by name');
} elseif ($name) {
	$MEMBER = new MEMBER(array('name' => $name));
	if ($MEMBER->house() == 1 && ($MEMBER->valid || !is_array($MEMBER->person_id()))) {
		member_redirect($MEMBER);
	}
} elseif ($cconstituency) {

# non-CVS addition 2005-02-18 - matthew
if ($cconstituency == 'your &amp; my society') {
	header('Location: /mp/stom%20teinberg');
	exit;
}
	$MEMBER = new MEMBER(array('constituency' => $cconstituency));
	member_redirect($MEMBER);
} else {
	// No postcode, member_id or person_id to use.
	debug ('MP', "We don't have any way of telling what MP to display");
}

	


/////////////////////////////////////////////////////////
// DISPLAY AN MP

if (isset($MEMBER) && is_array($MEMBER->person_id())) {
	$PAGE->page_start();
	$PAGE->stripe_start();
	print '<p>That name is not unique. Please select from the following:</p><ul>';
	$cs = $MEMBER->constituency();
	$c = 0;
	foreach ($MEMBER->person_id() as $id) {
		print '<li><a href="/mp/?pid='.$id.'">' . ucwords(strtolower($name)) . ', ' . $cs[$c++] . '</a></li>';
	}
	print '</ul>';

	$MPSURL = new URL('mps');
	$sidebar = array(
		'type' => 'html',
		'content' => '
						<div class="block">
							<h4><a href="' . $MPSURL->generate() . '">Browse all MPs</a></h4>
						</div>
'
	);
	
	$PAGE->stripe_end(array($sidebar));

} elseif (isset($MEMBER) && $MEMBER->person_id()) {
	
debug_timestamp("before load_extra_info");
	$MEMBER->load_extra_info();
debug_timestamp("after load_extra_info");
	
	$member_name = ucfirst($MEMBER->full_name());

	$subtitle = $member_name;
	if ($MEMBER->house() == 1) {
		if (!$MEMBER->current_member()) {
			$subtitle .= ', former';
		}
		$subtitle .= ' MP, '.$MEMBER->constituency();
	}
	$DATA->set_page_metadata($this_page, 'subtitle', $subtitle);
	$DATA->set_page_metadata($this_page, 'heading', '');

	// So we can put a link in the <head> in $PAGE->page_start();	
	$feedurl = $DATA->page_metadata('mp_rss', 'url');
	$DATA->set_page_metadata($this_page, 'rss', $feedurl . $MEMBER->person_id() . '.rdf');

debug_timestamp("before page_start");
	
	$PAGE->page_start();
debug_timestamp("after page_start");

debug_timestamp("before stripe start");
	$PAGE->stripe_start();
debug_timestamp("after stripe start");
	
debug_timestamp("before display of MP");
	$MEMBER->display();
debug_timestamp("after display of MP");
	
	// SIDEBAR.

	// We have to generate this HTML to pass to stripe_end().
	$linkshtml = $PAGE->generate_member_links($MEMBER, $MEMBER->extra_info());
	
	$sidebars = array(
		array (
			'type'		=> 'include',
			'content'	=> 'mp_email_friend'
		),
		array (
			'type'		=> 'include',
			'content'	=> 'mp_speech_search'
		),
		array (
			'type'		=> 'html',
			'content'	=> $linkshtml
		)
	);


	if ($rssurl = $DATA->page_metadata($this_page, 'rss')) {
		$sidebars[] = array (
			'type' 		=> 'html',
			'content'	=> $PAGE->member_rss_block(array('appearances' => WEBPATH . $rssurl))
		);
	}
	
#	$MPSURL = new URL('mps');
#	$sidebars[] = array (
#		'type' => 'html',
#		'content' => '
#						<div class="block">
#							<h4><a href="' . $MPSURL->generate() . '">Browse all MPs</a></h4>
#						</div>
#'
#	);

	if ($MEMBER->house() == 1) {
		$previous_people = $MEMBER->previous_mps();
		if ($previous_people) {
			$sidebars[] = array(
				'type' => 'html',
				'content' => '<div class="block"><h4>Previous MPs in this constituency</h4><div class="blockbody"><ul>' . $previous_people . '</ul></div></div>'
			);
		}
		$future_people = $MEMBER->future_mps();
		if ($future_people) {
			$sidebars[] = array(
				'type' => 'html',
				'content' => '<div class="block"><h4>Succeeding MPs in this constituency</h4><div class="blockbody"><ul>' . $future_people . '</ul></div></div>'
			);
		}
	}

	if (array_key_exists('office', $MEMBER->extra_info())) {
		$office = $MEMBER->extra_info();
		$office = $office['office'];
		$mins = '';
		foreach ($office as $row) {
			if ($row['to_date'] != '9999-12-31') {
				$mins .= '<li>' . prettify_office($row['position'], $row['dept']) . ' ('.format_date($row['from_date'],SHORTDATEFORMAT).' to '.format_date($row['to_date'],SHORTDATEFORMAT).')</li>';
			}
		}
		if ($mins) {
			$sidebars[] = array('type'=>'html',
			'content' => '<div class="block"><h4>This MP has also been:</h4><div class="blockbody"><ul>'.$mins.'</ul></div></div>');
		}
	}

/*	$body = technorati_pretty();
	if ($body) {
		$sidebars[] = array (
			'type' => 'html',
			'content' => '<div class="block"><h4>People talking about this MP</h4><div class="blockbody">' . $body . '</div></div>'
	);
	}
	*/
	$sidebars[] = array('type'=>'html',
		'content' => '<div class="block"><h4>Journalist?</h4>
<div class="blockbody"><p>Please feel free to use the data
on this page, but if you do you must cite TheyWorkForYou.com in the
body of your articles as the source of any analysis or
data you get off this site. If you ignore this, we might have to start
keeping these sorts of records on you...</p></div></div>'
	);
	$PAGE->stripe_end($sidebars);

} else {
	// Something went wrong.
	
	/////////////////////////////////////////////////////////
	// DISPLAY FORM

	
	$PAGE->page_start();
	
	$PAGE->stripe_start();

	if (isset($errors['pc'])) {
		$PAGE->error_message($errors['pc']);
	}

	$PAGE->postcode_form();
	
	$PAGE->stripe_end();

}


$PAGE->page_end();



function member_redirect(&$MEMBER) {
	global $this_page;
	// We come here after creating a MEMBER object by various methods.
	// Now we redirect to the canonical MP page, with a person_id.
	if ($MEMBER->person_id()) {
		header('Location: ' . $MEMBER->url() );
		exit;
	}
}
?>