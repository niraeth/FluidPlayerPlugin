<?php
namespace FluidPlayerPlugin\Wordpress;

class Wordpress
{
	public static function user_has_role($user_id, $role)
	{
		// Get the user object.
		$user = get_userdata( $user_id );

		// Get all the user roles as an array.
		$user_roles = $user->roles;

		//echo "user role";
		//var_dump($user_roles);
		
		// Check if the role you're interested in, is present in the array.
		//if ( in_array( 'subscriber', $user_roles, true ) ) {
		if( in_array( $role, $user_roles, true ) ) {
			// Do something.
			return true;
		}
		return false;
	}

	public static function current_user_has_role($role)
	{
		if(!is_user_logged_in())
			return false;
			
		return static::user_has_role(get_current_user_id(), $role);
	}
}

?>