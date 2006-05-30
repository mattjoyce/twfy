<?php

$this_page = "home";

include_once "../includes/easyparliament/init.php";
include_once "../includes/easyparliament/member.php";

$PAGE->page_start();

$PAGE->stripe_start();

$message = $PAGE->recess_message();
if ($message != '') {
	print '<p id="warning">' . $message . '</p>';
}

///////////////////////////////////////////////////////////////////////////
//  SEARCH AND RECENT HANSARD

$HANSARDURL = new URL('hansard');
$MPURL = new URL('yourmp');
$PAGE->block_start(array ('id'=>'intro', 'title'=>'At TheyWorkForYou.com you can:'));
?>
						<ol>
						<li>
<?php
if ($THEUSER->isloggedin() && $THEUSER->postcode() != '' || $THEUSER->postcode_is_set()) {
	// User is logged in and has a postcode, or not logged in with a cookied postcode.
	
	// (We don't allow the user to search for a postcode if they
	// already have one set in their prefs.)
	
	if ($THEUSER->isloggedin()) {
		$CHANGEURL = new URL('useredit');
	} else {
		$CHANGEURL = new URL('userchangepc');
	}
	$MEMBER = new MEMBER(array ('postcode'=>$THEUSER->postcode()));
	$mpname = $MEMBER->first_name() . ' ' . $MEMBER->last_name();
	$former = "";
	if ($MEMBER->left_house() != '9999-12-31') {
		$former = 'former';
	}

	?>
<p><a href="<?php echo $MPURL->generate(); ?>"><strong>Find out more about <?php echo $mpname; ?>, your <?= $former ?> MP</strong></a><br />
						In <?php echo strtoupper(htmlentities($THEUSER->postcode())); ?> (<a href="<?php echo $CHANGEURL->generate(); ?>">Change your postcode</a>)</p>
<?php
	
} else {
	// User is not logged in and doesn't have a personal postcode set.
	?>
						<form action="<?php echo $MPURL->generate(); ?>" method="get">
						<p><strong>Find out more about your MP</strong><br />
						<label for="pc">Enter your UK postcode here:</label>&nbsp; <input type="text" name="pc" id="pc" size="8" maxlength="10" value="<?php echo htmlentities($THEUSER->postcode()); ?>" class="text" />&nbsp;&nbsp;<input type="submit" value=" GO " class="submit" /></p>
						</form>

<?php
if (!defined("POSTCODE_SEARCH_DOMAIN")) {
	echo "POSTCODE_SEARCH_DOMAIN not defined. Postcodes will be mapped to a random MP";
}

}
?>
						</li>
						
						<li>
<?php
	$SEARCHURL = new URL('search');
	?>
						<form action="<?php echo $SEARCHURL->generate(); ?>" method="get">
						<p><strong>Search Commons and Lords debates, written answers, and statements since 2001; for an MP, peer, constituency, or date.</strong><br />
						<label for="s">Type what you are looking for:</label>&nbsp; <input type="text" name="s" id="s" size="15" maxlength="100" class="text" />&nbsp;&nbsp;<input type="submit" value="SEARCH" class="submit" /></p>
                        <?
                            // Display popular queries
                            global $SEARCHLOG;
                            $popular_searches = $SEARCHLOG->popular_recent(10);
                            if (count($popular_searches) > 0) {
                                ?> <p>Popular searches today: <?
                                $lentotal = 0;
                                $correct_amount = array();
                                // Select a number of queries that will fit in the space
                                foreach ($popular_searches as $popular_search) {
                                    $len = strlen($popular_search['visible_name']);
                                    if ($lentotal + $len > 32) {
                                        continue;
                                    }
                                    $lentotal += $len;
                                    array_push($correct_amount, $popular_search['display']);
                                }
                                print implode(", ", $correct_amount);
                                ?> </p> <?
                            }
                        ?>
						</form>
						</li>
<?php

	?>
						<li><p><a href="/alert/"><strong>Sign up to be emailed when something relevant to you happens in Parliament</strong></a></p></li>
						<li><p><strong>Comment on:</strong></p>

<?php 
	$DEBATELIST = new DEBATELIST; $data[1] = $DEBATELIST->most_recent_day();
	$WRANSLIST = new WRANSLIST; $data[3] = $WRANSLIST->most_recent_day();
	$WHALLLIST = new WHALLLIST; $data[2] = $WHALLLIST->most_recent_day();
	$WMSLIST = new WMSLIST; $data[4] = $WMSLIST->most_recent_day();
	$LORDSDEBATELIST = new LORDSDEBATELIST; $data[101] = $LORDSDEBATELIST->most_recent_day();
	foreach (array_keys($hansardmajors) as $major) {
		if (array_key_exists($major, $data)) {
			unset($data[$major]['listurl']);
			if (count($data[$major]) == 0) 
				unset($data[$major]);
		}
	}
	major_summary($data);
	?>
						</li>
						</ol>
<?php
$PAGE->block_end();

if(defined("NEWSBLOG")){
	$includes = array(
		array (
			'type' => 'include',
			'content' => 'whatisthissite'
		),
		array (
			'type' => 'include',
			'content' => 'sitenews_recent'
		)
	);
} else {
	$includes = array(
		array (
			'type' => 'include',
			'content' => 'whatisthissite'
		),
		array (
			
			'type'=>'html',
			'content' => 'DEVSITE set. The News Blog is not included in the developer version of the code, due to MovableType licensing issues (we cannot redistribute their code)'
		)
	);
}
$includes[] = array(
	'type' => 'include',
	'content' => 'comments_recent',
);
$PAGE->stripe_end($includes);
$PAGE->page_end();

?>