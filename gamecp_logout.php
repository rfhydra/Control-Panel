<?php
# Set main variables
$notuser = true;
$isuser = false;

# Include main files
# We are doing this _after_ so that we dont show up as logged in!
include('./gamecp_common.php');

logoutUser();

# Main variables, set after the fact
$leftTitle = 'Logout';
$title = $program_name . ' - ' . $leftTitle;
$user_points = '';

# Display message
$out .= '<p style="text-align: center; font-weight: bold;">' . _l('You have successfully logged out') . '</p>';

# Write the rest of the page
gamecp_nav(); // From phpBB 2.x
//$navbits = construct_navbits($navbits);
//eval('$navbar = "' . fetch_template('navbar') . '";');
//eval('print_output("' . fetch_template('GameCP') . '");');
eval('print_outputs("' . gamecpTempalte('gamecp') . '");');
